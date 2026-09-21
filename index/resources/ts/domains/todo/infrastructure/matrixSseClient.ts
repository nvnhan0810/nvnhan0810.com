import { parseMatrixStreamPayload, type MatrixStreamPayload } from "../application/parseMatrixStreamPayload";

export type MatrixSseHandlers = {
  onMatrix: (payload: MatrixStreamPayload) => void;
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

  const onError = (): void => {
    // EventSource reconnects automatically; surface only for diagnostics.
    handlers.onError?.(new Error("matrix SSE connection error"));
  };

  source.addEventListener("matrix", onMatrix as EventListener);
  source.addEventListener("error", onError);

  return (): void => {
    source.removeEventListener("matrix", onMatrix as EventListener);
    source.removeEventListener("error", onError);
    source.close();
  };
};
