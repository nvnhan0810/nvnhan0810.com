import { reactive } from "vue";
import { vueRouter } from "../app/router";
import { request, action } from "../infrastructure/todos-api";
export interface PagePayload {
  component: string;
  props: Record<string, unknown>;
}
export const page = reactive({
  url: "",
  payload: null as PagePayload | null,
  loading: false,
  error: "",
});
let version = 0;
export function resetPage() {
  version++;
  page.url = "";
  page.payload = null;
  page.loading = false;
  page.error = "";
}
export async function loadPage(
  path: string,
  prepare?: (name: string) => Promise<void>,
) {
  const own = ++version;
  page.loading = true;
  page.error = "";
  try {
    const payload = await request<PagePayload>(
      `/pages?path=${encodeURIComponent(path)}`,
    );
    await prepare?.(payload.component);
    if (own !== version || vueRouter.currentRoute.value.fullPath !== path)
      return;
    page.url = path;
    page.payload = payload;
  } catch (e) {
    if (own === version) {
      page.error = e instanceof Error ? e.message : "Không tải được trang.";
    }
  } finally {
    if (own === version) page.loading = false;
  }
}
export async function submit(path: string, method: string, data: unknown = {}) {
  const origin = vueRouter.currentRoute.value.fullPath;
  const result = await action<{ redirect?: string }>(path, method, data);
  if (origin !== vueRouter.currentRoute.value.fullPath) return;
  if (result.redirect && result.redirect !== origin)
    await vueRouter.push(result.redirect);
  else await loadPage(origin);
}
