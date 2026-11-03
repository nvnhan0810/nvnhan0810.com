import { mount, flushPromises } from "@vue/test-utils";
import { reactive } from "vue";
import { createRouter, createMemoryHistory } from "vue-router";
import { it, expect, vi } from "vitest";
import App from "../app/App.vue";
const mocks = vi.hoisted(() => ({ page: { loading: false } }));
vi.mock("../application/navigation", () => ({
  resetPage: vi.fn(),
  get page() {
    return mocks.page;
  },
}));
vi.mock("../app/services", () => ({
  session: { state: { user: { name: "Tester" } }, logout: vi.fn() },
}));
vi.mock("../app/router", () => ({ vueRouter: { replace: vi.fn() } }));
vi.mock("../infrastructure/web-push", () => ({
  disableNotifications: vi.fn(),
}));
it("keeps the navigation mounted and usable while a page is loading", async () => {
  mocks.page = reactive({ loading: false });
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: "/matrix", component: { template: "<p>Matrix</p>" } },
      { path: "/todos", component: { template: "<p>Tasks</p>" } },
    ],
  });
  await router.push("/matrix");
  await router.isReady();
  const wrapper = mount(App, { global: { plugins: [router] } });
  const nav = wrapper.get("nav").element;
  mocks.page.loading = true;
  await flushPromises();
  expect(wrapper.get("nav").element).toBe(nav);
  expect(wrapper.find(".loading-bar").exists()).toBe(true);
  await wrapper.get('nav a[href="/todos"]').trigger("click");
  await flushPromises();
  expect(router.currentRoute.value.path).toBe("/todos");
  expect(wrapper.get("nav").element).toBe(nav);
  wrapper.unmount();
});
