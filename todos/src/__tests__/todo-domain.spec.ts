import { expect, it } from "vitest";
import { pendingDependencies } from "../domain/todo";
it("only counts dependencies that are not done or rejected as pending", () => {
  const todo = {
    depends_on: [
      { id: 1, identifier: "DEF-1", title: "a", status: "done" as const },
      { id: 2, identifier: "DEF-2", title: "b", status: "in_review" as const },
      { id: 3, identifier: "DEF-3", title: "c", status: "todo" as const },
      { id: 4, identifier: "DEF-4", title: "d", status: "rejected" as const },
    ],
  };
  expect(pendingDependencies(todo).map((d) => d.id)).toEqual([2, 3]);
  expect(pendingDependencies({})).toEqual([]);
});
