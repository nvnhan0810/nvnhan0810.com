import { mount, flushPromises } from "@vue/test-utils";
import { it, expect, vi } from "vitest";
import Matrix from "../presentation/pages/Matrix.vue";
const mocks = vi.hoisted(() => ({
  submit: vi.fn(),
  stream: vi.fn().mockResolvedValue(undefined),
}));
vi.mock("../application/navigation", () => ({ submit: mocks.submit }));
vi.mock("../infrastructure/matrix-stream", () => ({
  matrixStream: mocks.stream,
}));
vi.mock("../infrastructure/todos-api", () => ({ action: vi.fn() }));
const todo = {
  id: 9,
  project_id: null,
  title: "Prepare report",
  description: "Details",
  status: "todo" as const,
  priority: "high" as const,
  started_at: null,
  due_at: null,
  closed_at: null,
  is_urgent: true,
  is_important: true,
};
const props = {
  quadrants: { do: [todo], schedule: [], delegate: [], eliminate: [] },
  backlog: [],
  projects: [],
  version: 1,
  pomodoro: {
    settings: {
      focusMinutes: 20,
      shortBreakMinutes: 5,
      longBreakMinutes: 15,
      sessionsBeforeLongBreak: 4,
    },
    runtime: {
      phase: "focus" as const,
      remainingMs: 1200000,
      endsAt: null,
      isRunning: false,
      focusCount: 0,
      activeTodoId: null,
      updatedAt: 0,
      sessionUuid: null,
    },
    version: 0,
    serverNow: 1,
  },
};
it("moves a task with the keyboard-accessible quadrant selector and preserves current content", async () => {
  mocks.submit.mockResolvedValue(undefined);
  const wrapper = mount(Matrix, {
    props,
    global: { stubs: { PomodoroBar: true, Modal: true } },
  });
  expect(wrapper.text()).toContain("Prepare report");
  await wrapper.get(".task-card-actions select").setValue("schedule");
  await flushPromises();
  expect(mocks.submit).toHaveBeenCalledWith("/matrix/todos/9", "PATCH", {
    is_urgent: false,
    is_important: true,
    status: "todo",
  });
  expect(wrapper.text()).toContain("Prepare report");
  wrapper.unmount();
  expect(mocks.stream.mock.calls.at(-1)?.[0].aborted).toBe(true);
});
it("hides the Pomodoro button on Agent tasks and animates the ones it is working on", async () => {
  const agentTask = (id: number, extra: object) => ({
    ...todo,
    id,
    title: `Agent ${id}`,
    agent_auto: true,
    ...extra,
  });
  const wrapper = mount(Matrix, {
    props: {
      ...props,
      quadrants: {
        do: [
          todo,
          agentTask(10, { status: "in_progress", agent_state: "running" }),
          agentTask(11, { status: "in_progress", agent_state: "paused" }),
          agentTask(12, { status: "todo" }),
        ],
        schedule: [],
        delegate: [],
        eliminate: [],
      },
    },
    global: { stubs: { PomodoroBar: true, Modal: true } },
  });
  const card = (id: number) => wrapper.get(`[data-todo-id="${id}"]`);
  expect(card(9).find(".focus-task").exists()).toBe(true);
  for (const id of [10, 11, 12])
    expect(card(id).find(".focus-task").exists()).toBe(false);
  expect(card(10).find("img.agent-working").attributes("src")).toBe(
    "/images/todos/agent-working.gif",
  );
  expect(card(11).find("img.agent-working").exists()).toBe(false);
  expect(card(12).find("img.agent-working").exists()).toBe(false);
});
