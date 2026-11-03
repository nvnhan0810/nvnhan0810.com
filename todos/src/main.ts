import { createApp } from "vue";
import App from "./app/App.vue";
import { vueRouter } from "./app/router";
import "./presentation/styles.css";
const theme = localStorage.getItem("todos.theme") || "system";
document.documentElement.dataset.theme =
  theme === "system"
    ? matchMedia("(prefers-color-scheme: dark)").matches
      ? "dark"
      : "light"
    : theme;
createApp(App).use(vueRouter).mount("#app");
