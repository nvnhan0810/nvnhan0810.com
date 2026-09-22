import { jsonFetch } from "@/ts/utils/jsonFetch";
import type { PomodoroSettings } from "../constants/pomodoro";
import type { PomodoroRuntimeSnapshot } from "./pomodoroStorage";
import {
  parsePomodoroSyncPayload,
  type PomodoroSyncPayload,
} from "../application/parsePomodoroSyncPayload";

export type PomodoroPushBody = {
  settings: PomodoroSettings;
  runtime: PomodoroRuntimeSnapshot;
};

export const fetchPomodoroState = async (
  url: string,
  signal?: AbortSignal,
): Promise<PomodoroSyncPayload> => {
  const raw = await jsonFetch<unknown>(url, { method: "GET", signal });
  return parsePomodoroSyncPayload(raw);
};

export const pushPomodoroState = async (
  url: string,
  body: PomodoroPushBody,
  signal?: AbortSignal,
): Promise<PomodoroSyncPayload> => {
  const raw = await jsonFetch<unknown>(url, {
    method: "PUT",
    body,
    signal,
  });
  return parsePomodoroSyncPayload(raw);
};
