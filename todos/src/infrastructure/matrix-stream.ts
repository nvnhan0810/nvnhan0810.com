import { session } from "../app/services";
export interface StreamEvent {
  event: string;
  data: string;
}
export function parseEvents(buffer: string): {
  events: StreamEvent[];
  rest: string;
} {
  buffer = buffer.replaceAll("\r\n", "\n");
  const parts = buffer.split("\n\n");
  const rest = parts.pop() ?? "";
  return {
    rest,
    events: parts.flatMap((block) => {
      const lines = block.split("\n");
      const data = lines
        .filter((l) => l.startsWith("data:"))
        .map((l) => l.slice(5).trimStart())
        .join("\n");
      return data
        ? [
            {
              event:
                lines
                  .find((l) => l.startsWith("event:"))
                  ?.slice(6)
                  .trim() ?? "message",
              data,
            },
          ]
        : [];
    }),
  };
}
export async function matrixStream(
  signal: AbortSignal,
  onData: (data: unknown) => void,
  onConnected: (connected: boolean) => void,
) {
  let force = false;
  while (!signal.aborted) {
    try {
      const token = await session.accessToken(force);
      force = false;
      const response = await fetch(
        `${session.settings.baseUrl}/todos/matrix/stream`,
        {
          headers: { Authorization: `Bearer ${token}` },
          signal,
          cache: "no-store",
        },
      );
      if (response.status === 401) {
        force = true;
        throw new Error("expired");
      }
      if (!response.ok || !response.body) throw new Error("stream_failed");
      const reader = response.body.getReader(),
        decoder = new TextDecoder();
      let buffer = "";
      onConnected(true);
      try {
        while (!signal.aborted) {
          const { value, done } = await reader.read();
          if (done) break;
          buffer += decoder.decode(value, { stream: true });
          const parsed = parseEvents(buffer);
          buffer = parsed.rest;
          for (const event of parsed.events)
            if (event.event === "matrix") onData(JSON.parse(event.data));
        }
      } finally {
        await reader.cancel().catch(() => {});
        reader.releaseLock();
      }
    } catch {
      onConnected(false);
    }
    if (!signal.aborted)
      await new Promise<void>((resolve) => {
        const finish = () => {
          clearTimeout(timer);
          signal.removeEventListener("abort", finish);
          resolve();
        };
        const timer = setTimeout(finish, 2000);
        signal.addEventListener("abort", finish, { once: true });
      });
  }
}
