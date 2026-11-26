import { describe, expect, it } from "vitest";
import {
  agentBadgeState,
  needsMergeBeforeDone,
  safeHttpUrl,
} from "../domain/todo";

describe("agent run badges", () => {
  it("maps claim and run states to one badge, and idle to none", () => {
    expect(agentBadgeState({ agent_state: "claimed" })).toBe("running");
    expect(agentBadgeState({ agent_state: "running" })).toBe("running");
    expect(agentBadgeState({ agent_state: "succeeded" })).toBe("succeeded");
    expect(agentBadgeState({ agent_state: "failed" })).toBe("failed");
    expect(agentBadgeState({ agent_state: "cancelled" })).toBe("cancelled");
    expect(agentBadgeState({ agent_state: "paused" })).toBe("paused");
    expect(agentBadgeState({ agent_state: "idle" })).toBeNull();
    expect(agentBadgeState({})).toBeNull();
  });
});

describe("safeHttpUrl", () => {
  it("keeps http(s) links and drops everything else", () => {
    expect(safeHttpUrl("https://github.com/o/r/pull/1")).toBe(
      "https://github.com/o/r/pull/1",
    );
    expect(safeHttpUrl("http://localhost:3000/chats/1")).toBe(
      "http://localhost:3000/chats/1",
    );
    expect(safeHttpUrl("javascript:alert(1)")).toBeNull();
    expect(safeHttpUrl("data:text/html,x")).toBeNull();
    expect(safeHttpUrl("not a url")).toBeNull();
    expect(safeHttpUrl(null)).toBeNull();
    expect(safeHttpUrl("")).toBeNull();
  });
});

describe("done warning", () => {
  it("warns only when a task with a PR is moved to done", () => {
    expect(
      needsMergeBeforeDone({ agent_pr_url: "https://x/pr/1" }, "done"),
    ).toBe(true);
    expect(
      needsMergeBeforeDone({ agent_pr_url: "https://x/pr/1" }, "in_review"),
    ).toBe(false);
    expect(needsMergeBeforeDone({ agent_pr_url: null }, "done")).toBe(false);
  });
});
