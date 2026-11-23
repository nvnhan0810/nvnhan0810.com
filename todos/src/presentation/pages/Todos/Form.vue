<script setup lang="ts">
import TaskEditor from "../../components/TaskEditor.vue";
import { vueRouter } from "../../../app/router";
import type {
  DependencyCandidate,
  TodoItem,
  TodoProject,
} from "../../../domain/todo";
import { t } from "../../../application/locale";
defineProps<{
  todo?: TodoItem | null;
  projects: TodoProject[];
  candidates?: DependencyCandidate[];
  statuses?: string[];
  priorities?: string[];
}>();
</script>
<template>
  <section class="panel form-page">
    <h1>{{ todo ? t("edit") : t("newTask") }}</h1>
    <TaskEditor
      :todo="todo"
      :projects="projects"
      :candidates="candidates"
      @cancel="vueRouter.push('/todos')"
    />
  </section>
</template>
