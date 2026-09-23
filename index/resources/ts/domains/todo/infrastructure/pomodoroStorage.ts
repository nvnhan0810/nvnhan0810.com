import {
  DEFAULT_POMODORO_SETTINGS,
  POMODORO_STORAGE_RUNTIME_KEY,
  POMODORO_STORAGE_SETTINGS_KEY,
  type PomodoroPhase,
  type PomodoroSettings,
} from "../constants/pomodoro";

export type PomodoroRuntimeSnapshot = {
  phase: PomodoroPhase;
  /**
   * Frozen remaining while paused (`endsAt === null`).
   * Ignored for display while running — use `endsAt - Date.now()`.
   */
  remainingMs: number;
  /** Absolute epoch ms when the phase job fires; null when paused/idle */
  endsAt: number | null;
  /** Completed focus sessions in current cycle (0..N-1 while in focus, resets after long break) */
  focusCount: number;
  activeTodoId: number | null;
  isRunning: boolean;
  updatedAt: number;
  /** Server phase session — used for focus heartbeat + job guard */
  sessionUuid: string | null;
};

export const DEFAULT_POMODORO_RUNTIME: PomodoroRuntimeSnapshot = {
  phase: "focus",
  remainingMs: DEFAULT_POMODORO_SETTINGS.focusMinutes * 60_000,
  endsAt: null,
  focusCount: 0,
  activeTodoId: null,
  isRunning: false,
  updatedAt: 0,
  sessionUuid: null,
};

const isRecord = (value: unknown): value is Record<string, unknown> =>
  typeof value === "object" && value !== null;

const clampInt = (value: unknown, min: number, max: number, fallback: number): number => {
  const n = typeof value === "number" ? value : Number(value);
  if (!Number.isFinite(n)) {
    return fallback;
  }
  return Math.min(max, Math.max(min, Math.round(n)));
};

export const loadPomodoroSettings = (): PomodoroSettings => {
  if (typeof window === "undefined") {
    return DEFAULT_POMODORO_SETTINGS;
  }
  try {
    const raw = window.localStorage.getItem(POMODORO_STORAGE_SETTINGS_KEY);
    if (!raw) {
      return DEFAULT_POMODORO_SETTINGS;
    }
    const parsed: unknown = JSON.parse(raw);
    if (!isRecord(parsed)) {
      return DEFAULT_POMODORO_SETTINGS;
    }
    return {
      focusMinutes: clampInt(parsed.focusMinutes, 1, 180, DEFAULT_POMODORO_SETTINGS.focusMinutes),
      shortBreakMinutes: clampInt(
        parsed.shortBreakMinutes,
        1,
        60,
        DEFAULT_POMODORO_SETTINGS.shortBreakMinutes,
      ),
      sessionsBeforeLongBreak: clampInt(
        parsed.sessionsBeforeLongBreak,
        1,
        12,
        DEFAULT_POMODORO_SETTINGS.sessionsBeforeLongBreak,
      ),
      longBreakMinutes: clampInt(
        parsed.longBreakMinutes,
        1,
        60,
        DEFAULT_POMODORO_SETTINGS.longBreakMinutes,
      ),
    };
  } catch {
    return DEFAULT_POMODORO_SETTINGS;
  }
};

export const savePomodoroSettings = (settings: PomodoroSettings): void => {
  if (typeof window === "undefined") {
    return;
  }
  window.localStorage.setItem(POMODORO_STORAGE_SETTINGS_KEY, JSON.stringify(settings));
};

export const loadPomodoroRuntime = (): PomodoroRuntimeSnapshot => {
  if (typeof window === "undefined") {
    return DEFAULT_POMODORO_RUNTIME;
  }
  try {
    const raw = window.localStorage.getItem(POMODORO_STORAGE_RUNTIME_KEY);
    if (!raw) {
      return {
        ...DEFAULT_POMODORO_RUNTIME,
        remainingMs: loadPomodoroSettings().focusMinutes * 60_000,
      };
    }
    const parsed: unknown = JSON.parse(raw);
    if (!isRecord(parsed)) {
      return DEFAULT_POMODORO_RUNTIME;
    }
    const phase =
      parsed.phase === "focus" ||
      parsed.phase === "short_break" ||
      parsed.phase === "long_break"
        ? parsed.phase
        : "focus";

    return {
      phase,
      remainingMs: clampInt(parsed.remainingMs, 0, 24 * 60 * 60_000, DEFAULT_POMODORO_RUNTIME.remainingMs),
      endsAt:
        typeof parsed.endsAt === "number" && Number.isFinite(parsed.endsAt)
          ? parsed.endsAt
          : null,
      focusCount: clampInt(parsed.focusCount, 0, 12, 0),
      activeTodoId:
        typeof parsed.activeTodoId === "number" && Number.isFinite(parsed.activeTodoId)
          ? parsed.activeTodoId
          : null,
      isRunning: parsed.isRunning === true,
      updatedAt:
        typeof parsed.updatedAt === "number" && Number.isFinite(parsed.updatedAt)
          ? parsed.updatedAt
          : 0,
      sessionUuid:
        typeof parsed.sessionUuid === "string" && parsed.sessionUuid.length > 0
          ? parsed.sessionUuid
          : null,
    };
  } catch {
    return DEFAULT_POMODORO_RUNTIME;
  }
};

export const savePomodoroRuntime = (runtime: PomodoroRuntimeSnapshot): void => {
  if (typeof window === "undefined") {
    return;
  }
  // Keep server updatedAt — never stamp Date.now() or hydrate GET gets rejected as "stale".
  window.localStorage.setItem(POMODORO_STORAGE_RUNTIME_KEY, JSON.stringify(runtime));
};
