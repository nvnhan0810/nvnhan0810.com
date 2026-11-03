import { mount, flushPromises } from "@vue/test-utils";
import { it, expect, vi } from "vitest";
import PomodoroBar from "../presentation/components/PomodoroBar.vue";
const mocks = vi.hoisted(() => ({ action: vi.fn() }));
vi.mock("../infrastructure/todos-api", () => ({
  action: mocks.action,
  request: vi.fn(),
}));
vi.mock("../infrastructure/web-push", () => ({
  currentSubscription: vi.fn().mockResolvedValue(null),
  enableNotifications: vi.fn(),
  disableNotifications: vi.fn(),
}));
it("starts the server timer even when browser audio is unavailable", async () => {
  const payload = {
    settings: {
      focusMinutes: 20,
      shortBreakMinutes: 5,
      sessionsBeforeLongBreak: 4,
      longBreakMinutes: 15,
    },
    runtime: {
      phase: "focus" as const,
      remainingMs: 1200000,
      endsAt: null,
      focusCount: 0,
      activeTodoId: null,
      isRunning: false,
      updatedAt: 0,
      sessionUuid: null,
    },
    version: 0,
    serverNow: Date.now(),
  };
  mocks.action.mockResolvedValue({
    ...payload,
    runtime: {
      ...payload.runtime,
      isRunning: true,
      endsAt: Date.now() + 1200000,
      sessionUuid: "session",
      updatedAt: 1,
    },
  });
  const wrapper = mount(PomodoroBar, {
    props: { payload, tasks: [] },
    global: { stubs: { Modal: true } },
  });
  await wrapper.get("button.primary").trigger("click");
  await flushPromises();
  expect(mocks.action).toHaveBeenCalledWith(
    "/matrix/pomodoro/start",
    "POST",
    {},
  );
  expect(wrapper.get("button.primary").text()).toContain("Tạm dừng");
  wrapper.unmount();
});
