import { useCallback, useEffect, useRef, useState } from "react";
import {
  DEFAULT_POMODORO_SETTINGS,
  nextPhaseAfterFocus,
  phaseDurationMs,
  type PomodoroPhase,
  type PomodoroSettings,
} from "../../constants/pomodoro";
import {
  parsePomodoroSyncPayload,
  type PomodoroSyncPayload,
} from "../../application/parsePomodoroSyncPayload";
import { pushPomodoroState, fetchPomodoroState } from "../../infrastructure/pomodoroApi";
import {
  loadPomodoroRuntime,
  loadPomodoroSettings,
  savePomodoroRuntime,
  savePomodoroSettings,
  type PomodoroRuntimeSnapshot,
} from "../../infrastructure/pomodoroStorage";
import {
  playPomodoroCountdownTick,
  playPomodoroPauseSound,
  playPomodoroPhaseEndSound,
  playPomodoroStartSound,
  POMODORO_COUNTDOWN_WARN_SECONDS,
  unlockPomodoroAudio,
} from "../../infrastructure/pomodoroAudio";
import {
  ensurePomodoroNotificationPermission,
  notifyPomodoroPhaseEnd,
} from "../../infrastructure/pomodoroNotifications";
import {
  clearPomodoroTabPresence,
  syncPomodoroTabPresence,
} from "../../infrastructure/pomodoroTabPresence";
import { useWakeLock } from "./useWakeLock";

export type UsePomodoroResult = {
  settings: PomodoroSettings;
  phase: PomodoroPhase;
  remainingMs: number;
  focusCount: number;
  activeTodoId: number | null;
  isRunning: boolean;
  selectTodo: (todoId: number | null) => void;
  clearActiveTodo: () => void;
  start: () => void;
  pause: () => void;
  toggle: () => void;
  skipPhase: () => void;
  saveSettings: (next: PomodoroSettings) => void;
  applyRemotePayload: (payload: PomodoroSyncPayload) => void;
};

type UsePomodoroArgs = {
  initialPayload?: unknown;
  syncUrl?: string;
};

const resolveRemaining = (runtime: PomodoroRuntimeSnapshot, now: number): number => {
  if (runtime.isRunning && runtime.endsAt !== null) {
    return Math.max(0, runtime.endsAt - now);
  }
  return Math.max(0, runtime.remainingMs);
};

const stampRuntime = (
  runtime: Omit<PomodoroRuntimeSnapshot, "updatedAt"> & { updatedAt?: number },
): PomodoroRuntimeSnapshot => ({
  ...runtime,
  updatedAt: Date.now(),
});

const resetRuntime = (settings: PomodoroSettings): PomodoroRuntimeSnapshot =>
  stampRuntime({
    phase: "focus",
    remainingMs: phaseDurationMs("focus", settings),
    endsAt: null,
    focusCount: 0,
    activeTodoId: null,
    isRunning: false,
  });

const advanceFromPhase = (
  prev: PomodoroRuntimeSnapshot,
  settings: PomodoroSettings,
): PomodoroRuntimeSnapshot => {
  const wasRunning = prev.isRunning;

  if (prev.phase === "focus") {
    const completedCount = prev.focusCount + 1;
    const nextPhase = nextPhaseAfterFocus(
      completedCount,
      settings.sessionsBeforeLongBreak,
    );
    const focusCount = nextPhase === "long_break" ? 0 : completedCount;
    const duration = phaseDurationMs(nextPhase, settings);

    return stampRuntime({
      ...prev,
      phase: nextPhase,
      focusCount,
      remainingMs: duration,
      endsAt: wasRunning ? Date.now() + duration : null,
      isRunning: wasRunning,
    });
  }

  const duration = phaseDurationMs("focus", settings);
  return stampRuntime({
    ...prev,
    phase: "focus",
    remainingMs: duration,
    endsAt: wasRunning ? Date.now() + duration : null,
    isRunning: wasRunning,
  });
};

const normalizeHydratedRuntime = (
  loadedRuntime: PomodoroRuntimeSnapshot,
  loadedSettings: PomodoroSettings,
): {
  runtime: PomodoroRuntimeSnapshot;
  didAdvance: boolean;
  fromPhase: PomodoroPhase;
} => {
  const currentRemaining = resolveRemaining(loadedRuntime, Date.now());
  if (loadedRuntime.isRunning && currentRemaining <= 0) {
    let cursor = loadedRuntime;
    let guard = 0;
    while (
      cursor.isRunning &&
      resolveRemaining(cursor, Date.now()) <= 0 &&
      guard < 8
    ) {
      cursor = advanceFromPhase(cursor, loadedSettings);
      guard += 1;
    }
    return {
      runtime: cursor,
      didAdvance: guard > 0,
      fromPhase: loadedRuntime.phase,
    };
  }

  return {
    runtime: {
      ...loadedRuntime,
      remainingMs: currentRemaining,
      endsAt:
        loadedRuntime.isRunning && currentRemaining > 0
          ? Date.now() + currentRemaining
          : null,
    },
    didAdvance: false,
    fromPhase: loadedRuntime.phase,
  };
};

const pickNewer = (
  localSettings: PomodoroSettings,
  localRuntime: PomodoroRuntimeSnapshot,
  remote: PomodoroSyncPayload,
): { settings: PomodoroSettings; runtime: PomodoroRuntimeSnapshot } => {
  if (remote.runtime.updatedAt > localRuntime.updatedAt) {
    return { settings: remote.settings, runtime: remote.runtime };
  }
  return { settings: localSettings, runtime: localRuntime };
};

export const usePomodoro = (args: UsePomodoroArgs = {}): UsePomodoroResult => {
  const { initialPayload, syncUrl } = args;
  const [settings, setSettings] = useState<PomodoroSettings>(DEFAULT_POMODORO_SETTINGS);
  const [runtime, setRuntime] = useState<PomodoroRuntimeSnapshot>(() => ({
    phase: "focus",
    remainingMs: DEFAULT_POMODORO_SETTINGS.focusMinutes * 60_000,
    endsAt: null,
    focusCount: 0,
    activeTodoId: null,
    isRunning: false,
    updatedAt: 0,
  }));
  const [nowTick, setNowTick] = useState(() => Date.now());
  const hydratedRef = useRef(false);
  const pendingSettingsRef = useRef<PomodoroSettings | null>(null);
  const settingsRef = useRef(settings);
  const runtimeRef = useRef(runtime);
  const lastCountdownSecondRef = useRef<number | null>(null);
  const lastPushedUpdatedAtRef = useRef(0);
  const syncUrlRef = useRef(syncUrl);
  const pushTimerRef = useRef<number | null>(null);
  const pushAbortRef = useRef<AbortController | null>(null);
  const initialPayloadRef = useRef(initialPayload);

  settingsRef.current = settings;
  runtimeRef.current = runtime;
  syncUrlRef.current = syncUrl;

  const scheduleRemotePush = useCallback((): void => {
    const url = syncUrlRef.current;
    if (!url || !hydratedRef.current) {
      return;
    }
    if (pushTimerRef.current !== null) {
      window.clearTimeout(pushTimerRef.current);
    }
    pushTimerRef.current = window.setTimeout(() => {
      pushTimerRef.current = null;
      const activeSettings = pendingSettingsRef.current ?? settingsRef.current;
      const currentRuntime = {
        ...runtimeRef.current,
        remainingMs: resolveRemaining(runtimeRef.current, Date.now()),
      };
      if (currentRuntime.updatedAt <= lastPushedUpdatedAtRef.current) {
        return;
      }
      pushAbortRef.current?.abort();
      const controller = new AbortController();
      pushAbortRef.current = controller;
      const pushedUpdatedAt = currentRuntime.updatedAt;
      void pushPomodoroState(
        url,
        { settings: activeSettings, runtime: currentRuntime },
        controller.signal,
      )
        .then((result) => {
          lastPushedUpdatedAtRef.current = Math.max(
            lastPushedUpdatedAtRef.current,
            pushedUpdatedAt,
          );
          if (result.accepted === false && result.runtime.updatedAt > runtimeRef.current.updatedAt) {
            setSettings(result.settings);
            savePomodoroSettings(result.settings);
            setRuntime(result.runtime);
            savePomodoroRuntime(result.runtime);
            pendingSettingsRef.current = null;
          }
        })
        .catch(() => {
          // Offline / aborted — localStorage remains source until next push.
        });
    }, 400);
  }, []);

  const reconcileOnVisible = useCallback((): void => {
    const url = syncUrlRef.current;
    if (!url || !hydratedRef.current) {
      return;
    }

    pushAbortRef.current?.abort();
    const controller = new AbortController();
    pushAbortRef.current = controller;

    void fetchPomodoroState(url, controller.signal)
      .then((remote) => {
        const localSettings = pendingSettingsRef.current ?? settingsRef.current;
        const localRuntime = {
          ...runtimeRef.current,
          remainingMs: resolveRemaining(runtimeRef.current, Date.now()),
        };
        const picked = pickNewer(localSettings, localRuntime, remote);

        if (picked.runtime.updatedAt > runtimeRef.current.updatedAt) {
          pendingSettingsRef.current = null;
          setSettings(picked.settings);
          savePomodoroSettings(picked.settings);
          const normalized = normalizeHydratedRuntime(picked.runtime, picked.settings);
          runtimeRef.current = normalized.runtime;
          setRuntime(normalized.runtime);
          savePomodoroRuntime(normalized.runtime);
          lastCountdownSecondRef.current = null;
          lastPushedUpdatedAtRef.current = Math.max(
            lastPushedUpdatedAtRef.current,
            picked.runtime.updatedAt,
          );
        }

        // If local is still newer (edits while visible), push; otherwise stay.
        scheduleRemotePush();
      })
      .catch(() => {
        scheduleRemotePush();
      });
  }, [scheduleRemotePush]);

  const applyRemotePayload = useCallback((payload: PomodoroSyncPayload): void => {
    if (!hydratedRef.current) {
      return;
    }
    if (payload.runtime.updatedAt <= runtimeRef.current.updatedAt) {
      return;
    }
    if (payload.runtime.updatedAt <= lastPushedUpdatedAtRef.current) {
      return;
    }
    pendingSettingsRef.current = null;
    lastPushedUpdatedAtRef.current = payload.runtime.updatedAt;
    setSettings(payload.settings);
    savePomodoroSettings(payload.settings);
    const normalized = normalizeHydratedRuntime(payload.runtime, payload.settings);
    runtimeRef.current = normalized.runtime;
    setRuntime(normalized.runtime);
    savePomodoroRuntime(normalized.runtime);
    lastCountdownSecondRef.current = null;
  }, []);
  useEffect(() => {
    const localSettings = loadPomodoroSettings();
    const localRuntime = loadPomodoroRuntime();
    let nextSettings = localSettings;
    let nextRuntime = localRuntime;
    const bootstrapPayload = initialPayloadRef.current;

    if (bootstrapPayload !== undefined) {
      try {
        const remote = parsePomodoroSyncPayload(bootstrapPayload);
        const picked = pickNewer(localSettings, localRuntime, remote);
        nextSettings = picked.settings;
        nextRuntime = picked.runtime;
      } catch {
        // Keep local snapshot when initial payload is invalid.
      }
    }

    const normalized = normalizeHydratedRuntime(nextRuntime, nextSettings);
    setSettings(nextSettings);
    savePomodoroSettings(nextSettings);
    settingsRef.current = nextSettings;
    runtimeRef.current = normalized.runtime;
    setRuntime(normalized.runtime);
    savePomodoroRuntime(normalized.runtime);
    if (normalized.didAdvance) {
      // Catch-up after opening a backgrounded PWA / tab. Web Push should have
      // notified already — skip local Notification popup (especially on iOS).
      playPomodoroPhaseEndSound();
    }
    hydratedRef.current = true;
    scheduleRemotePush();
  }, [scheduleRemotePush]);

  useEffect(() => {
    if (!hydratedRef.current) {
      return;
    }
    savePomodoroRuntime({
      ...runtime,
      remainingMs: resolveRemaining(runtime, Date.now()),
    });
    scheduleRemotePush();
  }, [runtime, scheduleRemotePush]);

  useEffect(() => {
    if (!runtime.isRunning) {
      lastCountdownSecondRef.current = null;
      return;
    }
    const id = window.setInterval(() => {
      const current = runtimeRef.current;
      const remaining = resolveRemaining(current, Date.now());
      if (remaining <= 0) {
        lastCountdownSecondRef.current = null;

        const activeSettings = pendingSettingsRef.current ?? settingsRef.current;
        if (pendingSettingsRef.current) {
          setSettings(pendingSettingsRef.current);
          savePomodoroSettings(pendingSettingsRef.current);
          pendingSettingsRef.current = null;
        }
        const advanced = advanceFromPhase(current, activeSettings);
        runtimeRef.current = advanced;
        setRuntime(advanced);
        playPomodoroPhaseEndSound();
        notifyPomodoroPhaseEnd({
          fromPhase: current.phase,
          toPhase: advanced.phase,
        });
        return;
      }

      const secondsLeft = Math.ceil(remaining / 1000);
      if (
        secondsLeft >= 1 &&
        secondsLeft <= POMODORO_COUNTDOWN_WARN_SECONDS &&
        lastCountdownSecondRef.current !== secondsLeft
      ) {
        lastCountdownSecondRef.current = secondsLeft;
        playPomodoroCountdownTick(secondsLeft);
      }

      setNowTick(Date.now());
    }, 250);
    return () => window.clearInterval(id);
  }, [runtime.isRunning, runtime.phase, runtime.endsAt]);

  const remainingMs = resolveRemaining(runtime, nowTick);

  useWakeLock({ shouldKeepScreenOn: runtime.isRunning });

  useEffect(() => {
    if (!hydratedRef.current) {
      return;
    }
    syncPomodoroTabPresence({
      phase: runtime.phase,
      remainingMs,
      isRunning: runtime.isRunning,
    });
  }, [runtime.phase, runtime.isRunning, remainingMs]);

  useEffect(() => {
    const onVisibility = (): void => {
      if (document.visibilityState === "visible") {
        unlockPomodoroAudio();
        reconcileOnVisible();
      }
    };
    document.addEventListener("visibilitychange", onVisibility);
    window.addEventListener("focus", unlockPomodoroAudio);
    return () => {
      document.removeEventListener("visibilitychange", onVisibility);
      window.removeEventListener("focus", unlockPomodoroAudio);
      clearPomodoroTabPresence();
      if (pushTimerRef.current !== null) {
        window.clearTimeout(pushTimerRef.current);
      }
      pushAbortRef.current?.abort();
    };
  }, [reconcileOnVisible]);

  const selectTodo = useCallback((todoId: number | null): void => {
    setRuntime((prev) =>
      stampRuntime({
        ...prev,
        activeTodoId: todoId,
        remainingMs: resolveRemaining(prev, Date.now()),
      }),
    );
  }, []);

  const clearActiveTodo = useCallback((): void => {
    const activeSettings = pendingSettingsRef.current ?? settingsRef.current;
    if (pendingSettingsRef.current) {
      setSettings(pendingSettingsRef.current);
      savePomodoroSettings(pendingSettingsRef.current);
      pendingSettingsRef.current = null;
    }
    lastCountdownSecondRef.current = null;
    const reset = resetRuntime(activeSettings);
    runtimeRef.current = reset;
    setRuntime(reset);
  }, []);

  const start = useCallback((): void => {
    unlockPomodoroAudio();
    ensurePomodoroNotificationPermission();
    setRuntime((prev) => {
      const remaining = resolveRemaining(prev, Date.now());
      if (remaining <= 0) {
        return prev;
      }
      if (!prev.isRunning) {
        lastCountdownSecondRef.current = null;
        playPomodoroStartSound();
      }
      return stampRuntime({
        ...prev,
        isRunning: true,
        remainingMs: remaining,
        endsAt: Date.now() + remaining,
      });
    });
  }, []);

  const pause = useCallback((): void => {
    setRuntime((prev) => {
      const remaining = resolveRemaining(prev, Date.now());
      if (prev.isRunning) {
        playPomodoroPauseSound();
      }
      return stampRuntime({
        ...prev,
        isRunning: false,
        remainingMs: remaining,
        endsAt: null,
      });
    });
  }, []);

  const toggle = useCallback((): void => {
    if (runtimeRef.current.isRunning) {
      pause();
    } else {
      start();
    }
  }, [pause, start]);

  const skipPhase = useCallback((): void => {
    const activeSettings = pendingSettingsRef.current ?? settingsRef.current;
    if (pendingSettingsRef.current) {
      setSettings(pendingSettingsRef.current);
      savePomodoroSettings(pendingSettingsRef.current);
      pendingSettingsRef.current = null;
    }
    lastCountdownSecondRef.current = null;
    const fromPhase = runtimeRef.current.phase;
    const advanced = advanceFromPhase(runtimeRef.current, activeSettings);
    playPomodoroPhaseEndSound();
    notifyPomodoroPhaseEnd({
      fromPhase,
      toPhase: advanced.phase,
    });
    runtimeRef.current = advanced;
    setRuntime(advanced);
  }, []);

  const saveSettings = useCallback(
    (next: PomodoroSettings): void => {
      savePomodoroSettings(next);
      pendingSettingsRef.current = null;
      settingsRef.current = next;
      setSettings(next);

      setRuntime((prev) => {
        const now = Date.now();
        const currentRemaining = resolveRemaining(prev, now);
        const maxMs = phaseDurationMs(prev.phase, next);
        // Shrink if remaining exceeds new phase length; never extend mid-phase.
        const nextRemaining = Math.min(currentRemaining, maxMs);

        if (prev.isRunning) {
          return stampRuntime({
            ...prev,
            remainingMs: nextRemaining,
            endsAt: now + nextRemaining,
            isRunning: true,
          });
        }

        return stampRuntime({
          ...prev,
          remainingMs: nextRemaining,
          endsAt: null,
          isRunning: false,
        });
      });
    },
    [],
  );

  return {
    settings: pendingSettingsRef.current ?? settings,
    phase: runtime.phase,
    remainingMs,
    focusCount: runtime.focusCount,
    activeTodoId: runtime.activeTodoId,
    isRunning: runtime.isRunning,
    selectTodo,
    clearActiveTodo,
    start,
    pause,
    toggle,
    skipPhase,
    saveSettings,
    applyRemotePayload,
  };
};
