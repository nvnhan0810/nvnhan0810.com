export type PomodoroPhase = "focus" | "short_break" | "long_break";

export type PomodoroSettings = {
  /** (1) Focus duration in minutes */
  focusMinutes: number;
  /** (2) Short break duration in minutes */
  shortBreakMinutes: number;
  /** (3) Number of focus sessions before long break */
  sessionsBeforeLongBreak: number;
  /** (4) Long break duration in minutes */
  longBreakMinutes: number;
};

export const DEFAULT_POMODORO_SETTINGS: PomodoroSettings = {
  focusMinutes: 20,
  shortBreakMinutes: 5,
  sessionsBeforeLongBreak: 4,
  longBreakMinutes: 15,
};

export const POMODORO_PHASE_LABEL: Record<PomodoroPhase, string> = {
  focus: "Tập trung",
  short_break: "Nghỉ ngắn",
  long_break: "Nghỉ dài",
};

export const POMODORO_STORAGE_SETTINGS_KEY = "todo.pomodoro.settings";
export const POMODORO_STORAGE_RUNTIME_KEY = "todo.pomodoro.runtime";

export const phaseDurationMs = (
  phase: PomodoroPhase,
  settings: PomodoroSettings,
): number => {
  switch (phase) {
    case "focus":
      return settings.focusMinutes * 60_000;
    case "short_break":
      return settings.shortBreakMinutes * 60_000;
    case "long_break":
      return settings.longBreakMinutes * 60_000;
  }
};

/**
 * After a completed focus session, decide next phase.
 * `completedFocusCount` is how many focuses finished in this cycle (1..N).
 */
export const nextPhaseAfterFocus = (
  completedFocusCount: number,
  sessionsBeforeLongBreak: number,
): PomodoroPhase => {
  if (completedFocusCount >= sessionsBeforeLongBreak) {
    return "long_break";
  }
  return "short_break";
};

export const formatTimer = (remainingMs: number): string => {
  const totalSeconds = Math.max(0, Math.ceil(remainingMs / 1000));
  const minutes = Math.floor(totalSeconds / 60);
  const seconds = totalSeconds % 60;
  return `${String(minutes).padStart(2, "0")}:${String(seconds).padStart(2, "0")}`;
};
