import {
  parseMatrixStreamPayload,
  type MatrixStreamPayload,
} from "../application/parseMatrixStreamPayload";
import {
  parsePomodoroSyncPayload,
  type PomodoroSyncPayload,
} from "../application/parsePomodoroSyncPayload";

export type MatrixSseHandlers = {
  onMatrix: (payload: MatrixStreamPayload) => void;
  onPomodoro?: (payload: PomodoroSyncPayload) => void;
  onError?: (error: unknown) => void;
};

/**
 * Opens an EventSource to the matrix stream. Caller must invoke the returned disposer.
 */
export const connectMatrixSse = (url: string, handlers: MatrixSseHandlers): (() => void) => {
  const source = new EventSource(url, { withCredentials: true });

  const onMatrix = (event: MessageEvent<string>): void => {
    try {
      const raw: unknown = JSON.parse(event.data);
      handlers.onMatrix(parseMatrixStreamPayload(raw));
    } catch (error: unknown) {
      handlers.onError?.(error);
    }
  };

  const onPomodoro = (event: MessageEvent<string>): void => {
    try {
      const raw: unknown = JSON.parse(event.data);
      handlers.onPomodoro?.(parsePomodoroSyncPayload(raw));
    } catch (error: unknown) {
      handlers.onError?.(error);
    }
  };

  const onError = (): void => {
    // EventSource reconnects automatically; surface only for diagnostics.
    handlers.onError?.(new Error("matrix SSE connection error"));
  };

  source.addEventListener("matrix", onMatrix as EventListener);
  source.addEventListener("pomodoro", onPomodoro as EventListener);
  source.addEventListener("error", onError);

  return (): void => {
    source.removeEventListener("matrix", onMatrix as EventListener);
    source.removeEventListener("pomodoro", onPomodoro as EventListener);
    source.removeEventListener("error", onError);
    source.close();
  };
};
