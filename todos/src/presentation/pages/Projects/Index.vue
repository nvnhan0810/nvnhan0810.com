<script setup lang="ts">
import { ref } from "vue";
import { Plus } from "lucide-vue-next";
import type { TodoProject } from "../../../domain/todo";
import { submit } from "../../../application/navigation";
import { t } from "../../../application/locale";
defineProps<{ projects: TodoProject[] }>();
const error = ref(""),
  busy = ref<number | null>(null);
async function remove(project: TodoProject) {
  if (!confirm(t("confirmDelete"))) return;
  busy.value = project.id;
  error.value = "";
  try {
    await submit(`/todos/projects/${project.id}`, "DELETE");
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
      <h1>{{ t("projects") }}</h1>
      <p class="muted">
        {{ projects.length }} {{ t("projects").toLowerCase() }}
      </p>
    </div>
    <RouterLink class="button" to="/todos/projects/create"
      ><Plus :size="16" />{{ t("newProject") }}</RouterLink
    >
  </div>
  <p v-if="error" role="alert" class="error">{{ error }}</p>
  <div class="table-wrap panel">
    <table>
      <thead>
        <tr>
          <th>{{ t("projects") }}</th>
          <th>{{ t("domain") }}</th>
          <th>{{ t("repo") }}</th>
          <th>{{ t("tasks") }}</th>
          <th>{{ t("actions") }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="!projects.length">
          <td colspan="5" class="empty">{{ t("empty") }}</td>
        </tr>
        <tr v-for="project in projects" :key="project.id">
          <td>
            <RouterLink :to="`/todos?project_id=${project.id}`">{{
              project.name
            }}</RouterLink>
          </td>
          <td>{{ project.domain || "—" }}</td>
          <td class="repository-cell">
            <a
              v-if="project.git_repo_url"
              :href="project.git_repo_url"
              target="_blank"
              rel="noopener noreferrer"
              >{{ project.git_repo_url }}</a
            ><span v-else>—</span>
          </td>
          <td class="numeric-cell">{{ project.todos_count ?? 0 }}</td>
          <td>
            <div class="project-actions">
              <RouterLink :to="`/todos?project_id=${project.id}`">{{
                t("tasks")
              }}</RouterLink>
              <RouterLink :to="`/todos/projects/${project.id}/edit`">{{
                t("edit")
              }}</RouterLink>
              <button
                class="text-button danger"
                :disabled="busy === project.id"
                @click="remove(project)"
              >
                {{ t("delete") }}
              </button>
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
