<script setup lang="ts">
import { watch } from "vue";
import { useRoute } from "vue-router";
import {
  LayoutGrid,
  CheckSquare,
  FolderKanban,
  LogOut,
  SunMoon,
  Languages,
} from "lucide-vue-next";
import { session } from "./services";
import { disableNotifications } from "../infrastructure/web-push";
import { vueRouter } from "./router";
import { page, resetPage } from "../application/navigation";
import { t, toggleLocale } from "../application/locale";
const route = useRoute();
watch(
  () => session.state.user,
  (user) => {
    resetPage();
    if (!user && route.path !== "/auth/callback")
      void vueRouter.replace("/login");
  },
);
function toggleTheme() {
  const theme =
    document.documentElement.dataset.theme === "dark" ? "light" : "dark";
  document.documentElement.dataset.theme = theme;
  localStorage.setItem("todos.theme", theme);
}
async function logout() {
  try {
    await disableNotifications().catch(() => {});
    await session.logout();
  } finally {
    await vueRouter.replace("/login");
  }
}
</script>
<template>
  <div
    v-if="page.loading"
    class="loading-bar"
    role="status"
    aria-label="Đang tải"
  >
    <span />
  </div>
  <template v-if="session.state.user">
    <header class="app-header">
      <RouterLink class="brand" to="/matrix"
        ><img
          src="/images/favicon-32x32.png"
          alt=""
          width="28"
          height="28"
        />Todo</RouterLink
      >
      <nav aria-label="Điều hướng chính">
        <RouterLink to="/matrix"
          ><LayoutGrid :size="18" />{{ t("matrix") }}</RouterLink
        ><RouterLink to="/todos"
          ><CheckSquare :size="18" />{{ t("tasks") }}</RouterLink
        ><RouterLink to="/todos/projects"
          ><FolderKanban :size="18" />{{ t("projects") }}</RouterLink
        >
      </nav>
      <div class="header-actions">
        <span class="user-name">{{ session.state.user.name }}</span
        ><button
          class="icon-button"
          aria-label="Đổi ngôn ngữ"
          @click="toggleLocale"
        >
          <Languages :size="18" /></button
        ><button
          class="icon-button"
          aria-label="Đổi giao diện"
          @click="toggleTheme"
        >
          <SunMoon :size="18" /></button
        ><button class="icon-button" :aria-label="t('logout')" @click="logout">
          <LogOut :size="18" />
        </button>
      </div>
    </header>
  </template>
  <main :class="{ 'app-main': session.state.user }"><RouterView /></main>
</template>
