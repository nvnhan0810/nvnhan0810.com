import { expect, it } from "vitest";
import { pendingDependencies } from "../domain/todo";
it("only counts dependencies that are not done as pending", () => {
  const todo = {
    depends_on: [
      { id: 1, title: "a", status: "done" as const },
      { id: 2, title: "b", status: "in_review" as const },
      { id: 3, title: "c", status: "todo" as const },
    ],
  };
  expect(pendingDependencies(todo).map((d) => d.id)).toEqual([2, 3]);
  expect(pendingDependencies({})).toEqual([]);
});
