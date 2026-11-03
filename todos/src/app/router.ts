import { createRouter, createWebHistory } from "vue-router";
import { session } from "./services";
export const authError = { message: "" };
let ready: Promise<void> | undefined;
export const vueRouter = createRouter({
  history: createWebHistory(),
  routes: [
    { path: "/login", component: () => import("../presentation/Login.vue") },
    {
      path: "/auth/callback",
      component: () => import("../presentation/Login.vue"),
    },
    {
      path: "/:pathMatch(.*)*",
      component: () => import("../presentation/PageView.vue"),
    },
  ],
  scrollBehavior: () => ({ top: 0 }),
});
vueRouter.beforeEach(async (to) => {
  ready ??= (async () => {
    try {
      const url = new URL(window.location.href);
      if (url.pathname === "/auth/callback") await session.completeLogin(url);
      else await session.restore();
    } catch (e) {
      authError.message =
        e instanceof Error ? e.message : "Không thể đăng nhập.";
    }
  })();
  await ready;
  if (!session.state.user && !["/login", "/auth/callback"].includes(to.path))
    return "/login";
  if (session.state.user && ["/login", "/auth/callback"].includes(to.path))
    return "/matrix";
});
