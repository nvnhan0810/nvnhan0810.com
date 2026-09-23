import { jsonFetch } from "@/ts/utils/jsonFetch";
import type { PomodoroSettings } from "../constants/pomodoro";
import {
  parsePomodoroSyncPayload,
  type PomodoroSyncPayload,
} from "../application/parsePomodoroSyncPayload";

export const fetchPomodoroState = async (
  url: string,
  signal?: AbortSignal,
): Promise<PomodoroSyncPayload> => {
  const raw = await jsonFetch<unknown>(url, { method: "GET", signal });
  return parsePomodoroSyncPayload(raw);
};

export const startPomodoro = async (
  url: string,
  activeTodoId?: number | null,
  signal?: AbortSignal,
): Promise<PomodoroSyncPayload> => {
  const body =
    activeTodoId === undefined ? {} : { activeTodoId: activeTodoId ?? null };
  const raw = await jsonFetch<unknown>(url, { method: "POST", body, signal });
  return parsePomodoroSyncPayload(raw);
};

export const pausePomodoro = async (
  url: string,
  signal?: AbortSignal,
): Promise<PomodoroSyncPayload> => {
  const raw = await jsonFetch<unknown>(url, { method: "POST", signal });
  return parsePomodoroSyncPayload(raw);
};

export const skipPomodoro = async (
  url: string,
  signal?: AbortSignal,
): Promise<PomodoroSyncPayload> => {
  const raw = await jsonFetch<unknown>(url, { method: "POST", signal });
  return parsePomodoroSyncPayload(raw);
};

export const resetPomodoro = async (
  url: string,
  signal?: AbortSignal,
): Promise<PomodoroSyncPayload> => {
  const raw = await jsonFetch<unknown>(url, { method: "POST", signal });
  return parsePomodoroSyncPayload(raw);
};

export const updatePomodoroSettings = async (
  url: string,
  settings: PomodoroSettings,
  signal?: AbortSignal,
): Promise<PomodoroSyncPayload> => {
  const raw = await jsonFetch<unknown>(url, {
    method: "PUT",
    body: settings,
    signal,
  });
  return parsePomodoroSyncPayload(raw);
};

export const updatePomodoroActiveTodo = async (
  url: string,
  activeTodoId: number | null,
  signal?: AbortSignal,
): Promise<PomodoroSyncPayload> => {
  const raw = await jsonFetch<unknown>(url, {
    method: "PATCH",
    body: { activeTodoId },
    signal,
  });
  return parsePomodoroSyncPayload(raw);
};

export const pingPomodoroFocus = async (
  url: string,
  sessionUuid: string,
  focused: boolean,
  signal?: AbortSignal,
): Promise<void> => {
  await jsonFetch<unknown>(url, {
    method: "POST",
    body: { sessionUuid, focused },
    signal,
  });
};
