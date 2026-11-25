import { flushPromises, mount } from "@vue/test-utils";
import { beforeEach, expect, it, vi } from "vitest";
import Integrations from "../presentation/pages/Settings/Integrations.vue";
const mocks = vi.hoisted(() => ({
  request: vi.fn(),
  query: {} as Record<string, string>,
}));
vi.mock("../infrastructure/todos-api", () => ({ request: mocks.request }));
vi.mock("vue-router", () => ({ useRoute: () => ({ query: mocks.query }) }));
const assign = vi.fn();
beforeEach(() => {
  mocks.request.mockReset();
  mocks.query = {};
  assign.mockReset();
  Object.defineProperty(window, "location", {
    value: { assign },
    configurable: true,
  });
});
it("sends the user to the Agent consent page", async () => {
  mocks.request.mockResolvedValueOnce({
    redirect_url:
      "https://agent.example/#/integrations/todo/consent?req=1&code=c",
  });
  const wrapper = mount(Integrations, {
    props: { agent: { enabled: true, linked: false } },
  });
  await wrapper.get("button.primary").trigger("click");
  await flushPromises();
  expect(mocks.request).toHaveBeenCalledWith(
    "/integrations/agent/link/start",
    expect.objectContaining({ method: "POST" }),
  );
  expect(assign).toHaveBeenCalledWith(
    "https://agent.example/#/integrations/todo/consent?req=1&code=c",
  );
});
it("refuses non-http redirect URLs", async () => {
  mocks.request.mockResolvedValueOnce({ redirect_url: "javascript:alert(1)" });
  const wrapper = mount(Integrations, {
    props: { agent: { enabled: true, linked: false } },
  });
  await wrapper.get("button.primary").trigger("click");
  await flushPromises();
  expect(assign).not.toHaveBeenCalled();
  expect(wrapper.find("[role=alert]").exists()).toBe(true);
});
it("shows the linked account and disconnects", async () => {
  vi.spyOn(window, "confirm").mockReturnValue(true);
  mocks.request
    .mockResolvedValueOnce({ linked: false, agent_notified: true })
    .mockResolvedValueOnce({ enabled: true, linked: false });
  const wrapper = mount(Integrations, {
    props: {
      agent: { enabled: true, linked: true, email: "me@example.com" },
    },
  });
  expect(wrapper.text()).toContain("me@example.com");
  await wrapper.get("button").trigger("click");
  await flushPromises();
  expect(mocks.request).toHaveBeenNthCalledWith(1, "/integrations/agent/link", {
    method: "DELETE",
  });
  expect(wrapper.find("button.primary").exists()).toBe(true);
  expect(wrapper.text()).not.toContain("me@example.com");
});
it("explains when the integration is disabled", () => {
  const wrapper = mount(Integrations, {
    props: { agent: { enabled: false, linked: false } },
  });
  expect(wrapper.find("button").exists()).toBe(false);
});
