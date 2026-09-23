import { useCallback, useEffect, useRef, useState } from "react";
import {
  DEFAULT_POMODORO_SETTINGS,
  type PomodoroPhase,
  type PomodoroSettings,
} from "../../constants/pomodoro";
import {
  parsePomodoroSyncPayload,
  type PomodoroSyncPayload,
} from "../../application/parsePomodoroSyncPayload";
import {
  fetchPomodoroState,
  pausePomodoro,
  pingPomodoroFocus,
  resetPomodoro,
  skipPomodoro,
  startPomodoro,
  updatePomodoroActiveTodo,
  updatePomodoroSettings,
} from "../../infrastructure/pomodoroApi";
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
import { ensurePomodoroNotificationPermission } from "../../infrastructure/pomodoroNotifications";
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
  sessionUuid: string | null;
  selectTodo: (todoId: number | null) => void;
  clearActiveTodo: () => void;
  start: () => void;
  pause: () => void;
  toggle: () => void;
  skipPhase: () => void;
  saveSettings: (next: PomodoroSettings) => void;
  applyRemotePayload: (payload: PomodoroSyncPayload) => void;
};

type UsePomodoroUrls = {
  show: string;
  start: string;
  pause: string;
  skip: string;
  reset: string;
  settings: string;
  activeTodo: string;
  focus: string;
};

type UsePomodoroArgs = {
  initialPayload?: unknown;
  urls?: UsePomodoroUrls;
};

const resolveRemaining = (runtime: PomodoroRuntimeSnapshot, now: number): number => {
  // Running: only endsAt (job fire time). Never trust API remainingMs (server clock).
  if (runtime.isRunning && runtime.endsAt !== null) {
    return Math.max(0, runtime.endsAt - now);
  }
  return Math.max(0, runtime.remainingMs);
};

const toDisplayRuntime = (runtime: PomodoroRuntimeSnapshot): PomodoroRuntimeSnapshot => ({
  ...runtime,
  remainingMs: resolveRemaining(runtime, Date.now()),
});

export const usePomodoro = (args: UsePomodoroArgs = {}): UsePomodoroResult => {
  const { initialPayload, urls } = args;
  const [settings, setSettings] = useState<PomodoroSettings>(DEFAULT_POMODORO_SETTINGS);
  const [runtime, setRuntime] = useState<PomodoroRuntimeSnapshot>(() => ({
    phase: "focus",
    remainingMs: DEFAULT_POMODORO_SETTINGS.focusMinutes * 60_000,
    endsAt: null,
    focusCount: 0,
    activeTodoId: null,
    isRunning: false,
    updatedAt: 0,
    sessionUuid: null,
  }));
  const [nowTick, setNowTick] = useState(() => Date.now());
  const hydratedRef = useRef(false);
  const settingsRef = useRef(settings);
  const runtimeRef = useRef(runtime);
  const lastCountdownSecondRef = useRef<number | null>(null);
  const lastAppliedUpdatedAtRef = useRef(0);
  const lastPhaseRef = useRef<PomodoroPhase>("focus");
  const urlsRef = useRef(urls);
  const commandAbortRef = useRef<AbortController | null>(null);
  const hydrateAbortRef = useRef<AbortController | null>(null);
  const awaitingPhaseEndRef = useRef(false);
  const initialPayloadRef = useRef(initialPayload);

  settingsRef.current = settings;
  runtimeRef.current = runtime;
  urlsRef.current = urls;

  const applyServerPayload = useCallback((payload: PomodoroSyncPayload): void => {
    if (payload.runtime.updatedAt < lastAppliedUpdatedAtRef.current) {
      return;
    }

    const prevPhase = lastPhaseRef.current;
    const nextRuntime = toDisplayRuntime(payload.runtime);
    const phaseChanged = nextRuntime.phase !== prevPhase;

    lastAppliedUpdatedAtRef.current = payload.runtime.updatedAt;
    lastPhaseRef.current = nextRuntime.phase;

    setSettings(payload.settings);
    savePomodoroSettings(payload.settings);
    settingsRef.current = payload.settings;
    runtimeRef.current = nextRuntime;
    setRuntime(nextRuntime);
    savePomodoroRuntime(nextRuntime);
    lastCountdownSecondRef.current = null;

    if (phaseChanged && hydratedRef.current) {
      playPomodoroPhaseEndSound();
      awaitingPhaseEndRef.current = false;
    }
  }, []);

  const runCommand = useCallback(
    async (request: (signal: AbortSignal) => Promise<PomodoroSyncPayload>): Promise<void> => {
      const currentUrls = urlsRef.current;
      if (!currentUrls || !hydratedRef.current) {
        return;
      }
      commandAbortRef.current?.abort();
      const controller = new AbortController();
      commandAbortRef.current = controller;
      try {
        const result = await request(controller.signal);
        applyServerPayload(result);
      } catch {
        // Keep last known server snapshot; hydrate on next visibility.
      }
    },
    [applyServerPayload],
  );

  const hydrateFromServer = useCallback((): void => {
    const currentUrls = urlsRef.current;
    if (!currentUrls || !hydratedRef.current) {
      return;
    }
    hydrateAbortRef.current?.abort();
    const controller = new AbortController();
    hydrateAbortRef.current = controller;
    void fetchPomodoroState(currentUrls.show, controller.signal)
      .then((remote) => {
        applyServerPayload(remote);
      })
      .catch(() => {
        // ignore
      });
  }, [applyServerPayload]);

  const applyRemotePayload = useCallback(
    (payload: PomodoroSyncPayload): void => {
      if (!hydratedRef.current) {
        return;
      }
      applyServerPayload(payload);
    },
    [applyServerPayload],
  );

  useEffect(() => {
    const localSettings = loadPomodoroSettings();
    const localRuntime = loadPomodoroRuntime();
    let nextSettings = localSettings;
    let nextRuntime = localRuntime;
    const bootstrapPayload = initialPayloadRef.current;

    if (bootstrapPayload !== undefined) {
      try {
        const remote = parsePomodoroSyncPayload(bootstrapPayload);
        // Server bootstrap wins over stale local cache.
        if (remote.runtime.updatedAt >= localRuntime.updatedAt) {
          nextSettings = remote.settings;
          nextRuntime = remote.runtime;
        }
      } catch {
        // Keep local snapshot when initial payload is invalid.
      }
    }

    const display = toDisplayRuntime(nextRuntime);
    setSettings(nextSettings);
    savePomodoroSettings(nextSettings);
    settingsRef.current = nextSettings;
    runtimeRef.current = display;
    setRuntime(display);
    savePomodoroRuntime(display);
    lastAppliedUpdatedAtRef.current = display.updatedAt;
    lastPhaseRef.current = display.phase;
    hydratedRef.current = true;

    // Always reconcile with GET so overdue phases are advanced by BE job / next command.
    hydrateFromServer();
  }, [hydrateFromServer]);

  useEffect(() => {
    if (!hydratedRef.current) {
      return;
    }
    savePomodoroRuntime({
      ...runtime,
      remainingMs: resolveRemaining(runtime, Date.now()),
    });
  }, [runtime]);

  useEffect(() => {
    if (!runtime.isRunning) {
      lastCountdownSecondRef.current = null;
      awaitingPhaseEndRef.current = false;
      return;
    }
    const id = window.setInterval(() => {
      const current = runtimeRef.current;
      const remaining = resolveRemaining(current, Date.now());
      if (remaining <= 0) {
        lastCountdownSecondRef.current = null;
        setNowTick(Date.now());
        if (!awaitingPhaseEndRef.current) {
          awaitingPhaseEndRef.current = true;
          // Soft poll — SSE may be delayed; BE owns phase change.
          hydrateFromServer();
        }
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
  }, [runtime.isRunning, runtime.phase, runtime.endsAt, hydrateFromServer]);

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

  // Global focus heartbeat for push suppress (any focused device stamps BE).
  useEffect(() => {
    const currentUrls = urlsRef.current;
    const sessionUuid = runtime.sessionUuid;
    if (!currentUrls || !runtime.isRunning || sessionUuid === null) {
      return;
    }

    const beat = (focused: boolean): void => {
      if (!focused) {
        return;
      }
      void pingPomodoroFocus(currentUrls.focus, sessionUuid, true).catch(() => {
        // ignore
      });
    };

    const syncFocus = (): void => {
      const focused =
        document.visibilityState === "visible" && document.hasFocus();
      beat(focused);
    };

    syncFocus();
    const intervalId = window.setInterval(syncFocus, 10_000);
    const onVis = (): void => syncFocus();
    const onFocus = (): void => beat(true);

    document.addEventListener("visibilitychange", onVis);
    window.addEventListener("focus", onFocus);

    return () => {
      window.clearInterval(intervalId);
      document.removeEventListener("visibilitychange", onVis);
      window.removeEventListener("focus", onFocus);
    };
  }, [runtime.isRunning, runtime.sessionUuid]);

  useEffect(() => {
    const onVisibility = (): void => {
      if (document.visibilityState === "visible") {
        unlockPomodoroAudio();
        hydrateFromServer();
      }
    };
    document.addEventListener("visibilitychange", onVisibility);
    window.addEventListener("focus", unlockPomodoroAudio);
    return () => {
      document.removeEventListener("visibilitychange", onVisibility);
      window.removeEventListener("focus", unlockPomodoroAudio);
      clearPomodoroTabPresence();
      commandAbortRef.current?.abort();
      hydrateAbortRef.current?.abort();
    };
  }, [hydrateFromServer]);

  const selectTodo = useCallback(
    (todoId: number | null): void => {
      void runCommand((signal) =>
        updatePomodoroActiveTodo(urlsRef.current!.activeTodo, todoId, signal),
      );
    },
    [runCommand],
  );

  const clearActiveTodo = useCallback((): void => {
    void runCommand((signal) => resetPomodoro(urlsRef.current!.reset, signal));
  }, [runCommand]);

  const start = useCallback((): void => {
    unlockPomodoroAudio();
    ensurePomodoroNotificationPermission();
    if (!runtimeRef.current.isRunning) {
      playPomodoroStartSound();
    }
    void runCommand((signal) =>
      startPomodoro(
        urlsRef.current!.start,
        runtimeRef.current.activeTodoId,
        signal,
      ),
    );
  }, [runCommand]);

  const pause = useCallback((): void => {
    if (runtimeRef.current.isRunning) {
      playPomodoroPauseSound();
    }
    void runCommand((signal) => pausePomodoro(urlsRef.current!.pause, signal));
  }, [runCommand]);

  const toggle = useCallback((): void => {
    if (runtimeRef.current.isRunning) {
      pause();
    } else {
      start();
    }
  }, [pause, start]);

  const skipPhase = useCallback((): void => {
    void runCommand((signal) => skipPomodoro(urlsRef.current!.skip, signal));
  }, [runCommand]);

  const saveSettings = useCallback(
    (next: PomodoroSettings): void => {
      savePomodoroSettings(next);
      settingsRef.current = next;
      setSettings(next);
      void runCommand((signal) =>
        updatePomodoroSettings(urlsRef.current!.settings, next, signal),
      );
    },
    [runCommand],
  );

  return {
    settings,
    phase: runtime.phase,
    remainingMs,
    focusCount: runtime.focusCount,
    activeTodoId: runtime.activeTodoId,
    isRunning: runtime.isRunning,
    sessionUuid: runtime.sessionUuid,
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
