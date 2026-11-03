import { it, expect } from "vitest";
import { parseEvents } from "../infrastructure/matrix-stream";
it("buffers incomplete events and supports CRLF, comments and multiline data", () => {
  const first = parseEvents(
    ': ping\r\n\r\nevent: matrix\r\ndata: {"title":"Công',
  );
  expect(first.events).toEqual([]);
  const second = parseEvents(
    first.rest +
      ' việc"}\r\n\r\nevent: pomodoro\ndata: first\ndata: second\n\ntrailing',
  );
  expect(second.events).toEqual([
    { event: "matrix", data: '{"title":"Công việc"}' },
    { event: "pomodoro", data: "first\nsecond" },
  ]);
  expect(second.rest).toBe("trailing");
});
