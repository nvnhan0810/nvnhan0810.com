<script setup lang="ts">
import { reactive, ref, watch } from "vue";
import { Plus, Search, ChevronLeft, ChevronRight } from "lucide-vue-next";
import type { TodoItem, TodoProject } from "../../../domain/todo";
import { vueRouter } from "../../../app/router";
import { submit } from "../../../application/navigation";
import { t } from "../../../application/locale";
const props = defineProps<{
  todos: {
    data: TodoItem[];
    current_page: number;
    last_page: number;
    total: number;
  };
  projects: TodoProject[];
  filters: { search: string; status: string; project_id: number | null };
  statuses: string[];
  priorities: string[];
}>();
const filters = reactive({ ...props.filters }),
  error = ref(""),
  busy = ref<number | null>(null);
watch(
  () => props.filters,
  (value) => Object.assign(filters, value),
  { deep: true },
);
let searchTimer: ReturnType<typeof setTimeout> | undefined;
watch(
  () => filters.search,
  () => {
    if (filters.search.trim() === props.filters.search.trim()) return;
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => filter(), 300);
  },
);
function filter(page = 1) {
  const params = new URLSearchParams();
  for (const [k, v] of Object.entries(filters)) if (v) params.set(k, String(v));
  if (page > 1) params.set("page", String(page));
  void vueRouter.push(`/todos?${params}`);
}
function due(value: string | null) {
  return value ? value.slice(0, 10) : "";
}
async function remove(todo: TodoItem) {
  if (!confirm(t("confirmDelete"))) return;
  busy.value = todo.id;
  error.value = "";
  try {
    await submit(`/todos/${todo.id}`, "DELETE");
  } catch (e) {
    error.value = e instanceof Error ? e.message : "Không thể xóa.";
  } finally {
    busy.value = null;
  }
}
</script>
<template>
  <div class="section-heading">
    <div>
      <h1>{{ t("tasks") }}</h1>
      <p class="muted">{{ todos.total }} {{ t("tasks").toLowerCase() }}</p>
    </div>
    <RouterLink class="button" to="/todos/create"
      ><Plus :size="16" />{{ t("newTask") }}</RouterLink
    >
  </div>
  <form class="filters" @submit.prevent="filter()">
    <label class="search"
      ><Search :size="18" /><input
        v-model="filters.search"
        :placeholder="t('search')"
        maxlength="200" /></label
    ><select
      v-model="filters.project_id"
      :aria-label="t('projects')"
      @change="filter()"
    >
      <option :value="null">
        {{ t("all") }} {{ t("projects").toLowerCase() }}
      </option>
      <option v-for="project in projects" :key="project.id" :value="project.id">
        {{ project.name }}
      </option>
    </select>
    ><select
      v-model="filters.status"
      :aria-label="t('status')"
      @change="filter()"
    >
      <option value="">{{ t("all") }} {{ t("status").toLowerCase() }}</option>
      <option v-for="status in statuses" :key="status" :value="status">
        {{ t(status) }}
      </option>
    </select>
  </form>
  <p v-if="error" class="error" role="alert">{{ error }}</p>
  <div class="table-wrap panel">
    <table>
      <thead>
        <tr>
          <th>{{ t("title") }}</th>
          <th>{{ t("projects") }}</th>
          <th>{{ t("status") }}</th>
          <th>{{ t("priority") }}</th>
          <th>{{ t("eisenhower") }}</th>
          <th>{{ t("due") }}</th>
          <th />
        </tr>
      </thead>
      <tbody>
        <tr v-if="!todos.data.length">
          <td colspan="7" class="empty">{{ t("empty") }}</td>
        </tr>
        <tr v-for="todo in todos.data" :key="todo.id">
          <td>
            <RouterLink class="task-title" :to="`/todos/${todo.id}/edit`">{{
              todo.title
            }}</RouterLink>
            <p class="description-preview">{{ todo.description }}</p>
          </td>
          <td>{{ todo.project?.name || "—" }}</td>
          <td>
            <span class="badge" :class="todo.status">{{ t(todo.status) }}</span>
          </td>
          <td>
            <span class="badge" :class="todo.priority">{{
              t(todo.priority)
            }}</span>
          </td>
          <td>
            <span
              class="badge eisenhower"
              :class="{
                do: todo.is_urgent && todo.is_important,
                schedule: !todo.is_urgent && todo.is_important,
                delegate: todo.is_urgent && !todo.is_important,
                eliminate: !todo.is_urgent && !todo.is_important,
              }"
              >{{
                t(
                  todo.is_urgent && todo.is_important
                    ? "do"
                    : !todo.is_urgent && todo.is_important
                      ? "schedule"
                      : todo.is_urgent
                        ? "delegate"
                        : "eliminate",
                )
              }}</span
            >
          </td>
          <td
            :class="{
              overdue:
                todo.due_at &&
                new Date(todo.due_at) < new Date() &&
                !['done', 'rejected'].includes(todo.status),
            }"
          >
            {{ due(todo.due_at) || "—" }}
          </td>
          <td>
            <div class="row">
              <RouterLink class="table-link" :to="`/todos/${todo.id}/edit`">{{
                t("edit")
              }}</RouterLink
              ><button
                class="table-link danger"
                :disabled="busy === todo.id"
                @click="remove(todo)"
              >
                {{ t("delete") }}
              </button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
  <div class="pagination">
    <button
      :disabled="todos.current_page <= 1"
      aria-label="Trang trước"
      @click="filter(todos.current_page - 1)"
    >
      <ChevronLeft :size="18" /></button
    ><span>{{ todos.current_page }} / {{ todos.last_page }}</span
    ><button
      :disabled="todos.current_page >= todos.last_page"
      aria-label="Trang sau"
      @click="filter(todos.current_page + 1)"
    >
      <ChevronRight :size="18" />
    </button>
  </div>
</template>
