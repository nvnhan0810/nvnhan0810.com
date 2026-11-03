import { describe, it, expect } from "vitest";
import {
  clock,
  remainingMs,
  isPomodoro,
  type PomodoroPayload,
} from "../domain/pomodoro";
const payload: PomodoroPayload = {
  settings: {
    focusMinutes: 20,
    shortBreakMinutes: 5,
    sessionsBeforeLongBreak: 4,
    longBreakMinutes: 15,
  },
  runtime: {
    phase: "focus",
    remainingMs: 1200000,
    endsAt: 1300000,
    focusCount: 0,
    activeTodoId: null,
    isRunning: true,
    updatedAt: 10,
    sessionUuid: "session",
  },
  version: 10,
  serverNow: 100000,
};
describe("server-based Pomodoro clock", () => {
  it("derives remaining time from the deadline, independent of tick frequency", () => {
    expect(remainingMs(payload, 100000)).toBe(1200000);
    expect(remainingMs(payload, 1250000)).toBe(50000);
    expect(remainingMs(payload, 1400000)).toBe(0);
  });
  it("freezes a paused duration", () => {
    const paused = {
      ...payload,
      runtime: {
        ...payload.runtime,
        isRunning: false,
        endsAt: null,
        remainingMs: 54321,
      },
    };
    expect(remainingMs(paused, 99999999)).toBe(54321);
    expect(clock(54321)).toBe("00:55");
    expect(clock(1200000)).toBe("20:00");
  });
  it("rejects malformed stream runtime", () => {
    expect(isPomodoro(payload)).toBe(true);
    expect(
      isPomodoro({
        ...payload,
        runtime: { ...payload.runtime, phase: "bogus" },
      }),
    ).toBe(false);
    expect(isPomodoro({})).toBe(false);
  });
});
