export interface PomodoroSettings {
  focusMinutes: number;
  shortBreakMinutes: number;
  sessionsBeforeLongBreak: number;
  longBreakMinutes: number;
}
export interface PomodoroPayload {
  settings: PomodoroSettings;
  runtime: {
    phase: "focus" | "short_break" | "long_break";
    remainingMs: number;
    endsAt: number | null;
    focusCount: number;
    activeTodoId: number | null;
    isRunning: boolean;
    updatedAt: number;
    sessionUuid: string | null;
  };
  version: number;
  serverNow: number;
}
export function remainingMs(payload: PomodoroPayload, now: number) {
  return payload.runtime.isRunning && payload.runtime.endsAt !== null
    ? Math.max(0, payload.runtime.endsAt - now)
    : Math.max(0, payload.runtime.remainingMs);
}
export function clock(ms: number) {
  const seconds = Math.ceil(Math.max(0, ms) / 1000);
  return `${String(Math.floor(seconds / 60)).padStart(2, "0")}:${String(seconds % 60).padStart(2, "0")}`;
}
export function isPomodoro(value: unknown): value is PomodoroPayload {
  if (!value || typeof value !== "object") return false;
  const p = value as Partial<PomodoroPayload>;
  return (
    !!p.settings &&
    !!p.runtime &&
    ["focus", "short_break", "long_break"].includes(p.runtime.phase) &&
    Number.isFinite(p.runtime.updatedAt) &&
    Number.isFinite(p.runtime.remainingMs) &&
    Number.isFinite(p.serverNow)
  );
}
