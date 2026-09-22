import { jsonFetch } from "@/ts/utils/jsonFetch";
import type { PomodoroSettings } from "../constants/pomodoro";
import type { PomodoroRuntimeSnapshot } from "./pomodoroStorage";
import {
  parsePomodoroSyncPayload,
  type PomodoroSyncPayload,
} from "../application/parsePomodoroSyncPayload";
import { getCurrentPushSubscription } from "./webPushClient";

export type PomodoroPushBody = {
  settings: PomodoroSettings;
  runtime: PomodoroRuntimeSnapshot;
  /** Whether Matrix is currently focused on this device (for Web Push suppress). */
  focused?: boolean;
  /** Push subscription endpoint for this device (pairs with focused). */
  endpoint?: string | null;
};

const isMatrixFocused = (): boolean => {
  if (typeof document === "undefined") {
    return false;
  }
  return document.visibilityState === "visible" && document.hasFocus();
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
  const focused = body.focused ?? isMatrixFocused();
  let endpoint: string | null = body.endpoint ?? null;
  if (endpoint === null) {
    try {
      const subscription = await getCurrentPushSubscription();
      endpoint = subscription?.endpoint ?? null;
    } catch {
      endpoint = null;
    }
  }

  const raw = await jsonFetch<unknown>(url, {
    method: "PUT",
    body: {
      settings: body.settings,
      runtime: body.runtime,
      focused,
      endpoint,
    },
    signal,
  });
  return parsePomodoroSyncPayload(raw);
};
