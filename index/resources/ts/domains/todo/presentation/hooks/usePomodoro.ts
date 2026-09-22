import { useCallback, useEffect, useRef, useState } from "react";
import {
  DEFAULT_POMODORO_SETTINGS,
  nextPhaseAfterFocus,
  phaseDurationMs,
  type PomodoroPhase,
  type PomodoroSettings,
} from "../../constants/pomodoro";
import {
  loadPomodoroRuntime,
  loadPomodoroSettings,
  savePomodoroRuntime,
  savePomodoroSettings,
  type PomodoroRuntimeSnapshot,
} from "../../infrastructure/pomodoroStorage";
import {
  playPomodoroPauseSound,
  playPomodoroPhaseEndSound,
  playPomodoroStartSound,
} from "../../infrastructure/pomodoroAudio";

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
};

const resolveRemaining = (runtime: PomodoroRuntimeSnapshot, now: number): number => {
  if (runtime.isRunning && runtime.endsAt !== null) {
    return Math.max(0, runtime.endsAt - now);
  }
  return Math.max(0, runtime.remainingMs);
};

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

    return {
      ...prev,
      phase: nextPhase,
      focusCount,
      remainingMs: duration,
      endsAt: wasRunning ? Date.now() + duration : null,
      isRunning: wasRunning,
    };
  }

  const duration = phaseDurationMs("focus", settings);
  return {
    ...prev,
    phase: "focus",
    remainingMs: duration,
    endsAt: wasRunning ? Date.now() + duration : null,
    isRunning: wasRunning,
  };
};

export const usePomodoro = (): UsePomodoroResult => {
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

  settingsRef.current = settings;
  runtimeRef.current = runtime;

  useEffect(() => {
    const loadedSettings = loadPomodoroSettings();
    const loadedRuntime = loadPomodoroRuntime();
    setSettings(loadedSettings);

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
      setRuntime(cursor);
      savePomodoroRuntime(cursor);
      playPomodoroPhaseEndSound();
    } else {
      setRuntime({
        ...loadedRuntime,
        remainingMs: currentRemaining,
        endsAt:
          loadedRuntime.isRunning && currentRemaining > 0
            ? Date.now() + currentRemaining
            : null,
      });
    }
    hydratedRef.current = true;
  }, []);

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
      return;
    }
    const id = window.setInterval(() => {
      const current = runtimeRef.current;
      const remaining = resolveRemaining(current, Date.now());
      if (remaining <= 0) {
        const activeSettings = pendingSettingsRef.current ?? settingsRef.current;
        if (pendingSettingsRef.current) {
          setSettings(pendingSettingsRef.current);
          pendingSettingsRef.current = null;
        }
        const advanced = advanceFromPhase(current, activeSettings);
        runtimeRef.current = advanced;
        setRuntime(advanced);
        playPomodoroPhaseEndSound();
        return;
      }
      setNowTick(Date.now());
    }, 250);
    return () => window.clearInterval(id);
  }, [runtime.isRunning, runtime.phase, runtime.endsAt]);

  const remainingMs = resolveRemaining(runtime, nowTick);

  const selectTodo = useCallback((todoId: number | null): void => {
    setRuntime((prev) => ({
      ...prev,
      activeTodoId: todoId,
      remainingMs: resolveRemaining(prev, Date.now()),
    }));
  }, []);

  const clearActiveTodo = useCallback((): void => {
    setRuntime((prev) => ({
      ...prev,
      activeTodoId: null,
      remainingMs: resolveRemaining(prev, Date.now()),
    }));
  }, []);

  const start = useCallback((): void => {
    setRuntime((prev) => {
      const remaining = resolveRemaining(prev, Date.now());
      if (remaining <= 0) {
        return prev;
      }
      if (!prev.isRunning) {
        playPomodoroStartSound();
      }
      return {
        ...prev,
        isRunning: true,
        remainingMs: remaining,
        endsAt: Date.now() + remaining,
      };
    });
  }, []);

  const pause = useCallback((): void => {
    setRuntime((prev) => {
      const remaining = resolveRemaining(prev, Date.now());
      if (prev.isRunning) {
        playPomodoroPauseSound();
      }
      return {
        ...prev,
        isRunning: false,
        remainingMs: remaining,
        endsAt: null,
      };
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
      pendingSettingsRef.current = null;
    }
    playPomodoroPhaseEndSound();
    setRuntime((prev) => advanceFromPhase(prev, activeSettings));
  }, []);

  const saveSettings = useCallback((next: PomodoroSettings): void => {
    savePomodoroSettings(next);
    if (runtimeRef.current.isRunning) {
      pendingSettingsRef.current = next;
      return;
    }
    setSettings(next);
    setRuntime((prev) => ({
      ...prev,
      remainingMs: phaseDurationMs(prev.phase, next),
      endsAt: null,
      isRunning: false,
    }));
  }, []);

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
  };
};
