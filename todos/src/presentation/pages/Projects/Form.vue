<script setup lang="ts">
import ProjectEditor from "../../components/ProjectEditor.vue";
import { vueRouter } from "../../../app/router";
import type { TodoProject } from "../../../domain/todo";
import { t } from "../../../application/locale";
defineProps<{ project?: TodoProject | null }>();
</script>
<template>
  <section class="panel form-page">
    <h1>{{ project ? t("edit") : t("newProject") }}</h1>
    <ProjectEditor
      :project="project"
      @cancel="vueRouter.push('/todos/projects')"
    />
  </section>
</template>
