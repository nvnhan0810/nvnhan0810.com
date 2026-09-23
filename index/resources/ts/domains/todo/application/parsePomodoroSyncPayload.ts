import type { PomodoroSettings } from "../constants/pomodoro";
import type { PomodoroRuntimeSnapshot } from "../infrastructure/pomodoroStorage";

const isRecord = (value: unknown): value is Record<string, unknown> =>
  typeof value === "object" && value !== null && !Array.isArray(value);

const clampInt = (value: unknown, min: number, max: number, fallback: number): number => {
  const n = typeof value === "number" ? value : Number(value);
  if (!Number.isFinite(n)) {
    return fallback;
  }
  return Math.min(max, Math.max(min, Math.round(n)));
};

export type PomodoroSyncPayload = {
  settings: PomodoroSettings;
  runtime: PomodoroRuntimeSnapshot;
  version: number;
  accepted?: boolean;
};

export const parsePomodoroSyncPayload = (value: unknown): PomodoroSyncPayload => {
  if (!isRecord(value) || !isRecord(value.settings) || !isRecord(value.runtime)) {
    throw new Error("Invalid pomodoro sync payload");
  }

  const phase =
    value.runtime.phase === "focus" ||
    value.runtime.phase === "short_break" ||
    value.runtime.phase === "long_break"
      ? value.runtime.phase
      : "focus";

  return {
    settings: {
      focusMinutes: clampInt(value.settings.focusMinutes, 1, 180, 20),
      shortBreakMinutes: clampInt(value.settings.shortBreakMinutes, 1, 60, 5),
      sessionsBeforeLongBreak: clampInt(
        value.settings.sessionsBeforeLongBreak,
        1,
        12,
        4,
      ),
      longBreakMinutes: clampInt(value.settings.longBreakMinutes, 1, 60, 15),
    },
    runtime: {
      phase,
      remainingMs: clampInt(value.runtime.remainingMs, 0, 24 * 60 * 60_000, 20 * 60_000),
      endsAt:
        typeof value.runtime.endsAt === "number" && Number.isFinite(value.runtime.endsAt)
          ? value.runtime.endsAt
          : null,
      focusCount: clampInt(value.runtime.focusCount, 0, 12, 0),
      activeTodoId:
        typeof value.runtime.activeTodoId === "number" &&
        Number.isFinite(value.runtime.activeTodoId)
          ? value.runtime.activeTodoId
          : null,
      isRunning: value.runtime.isRunning === true,
      updatedAt: clampInt(value.runtime.updatedAt, 0, Number.MAX_SAFE_INTEGER, 0),
      sessionUuid:
        typeof value.runtime.sessionUuid === "string" &&
        value.runtime.sessionUuid.length > 0
          ? value.runtime.sessionUuid
          : null,
    },
    version: clampInt(value.version, 0, Number.MAX_SAFE_INTEGER, 0),
    accepted: value.accepted === undefined ? undefined : value.accepted === true,
  };
};
