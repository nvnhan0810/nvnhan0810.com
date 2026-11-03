<script setup lang="ts">
import { reactive, ref } from "vue";
import type { TodoProject } from "../../domain/todo";
import { submit } from "../../application/navigation";
import { action, ValidationError } from "../../infrastructure/todos-api";
import { t } from "../../application/locale";
const props = defineProps<{ inline?: boolean; project?: TodoProject | null }>();
const emit = defineEmits<{
  saved: [];
  cancel: [];
  created: [project: TodoProject];
}>();
const form = reactive({
  name: props.project?.name ?? "",
  git_repo_url: props.project?.git_repo_url ?? "",
  domain: props.project?.domain ?? "",
});
const busy = ref(false),
  errors = ref<Record<string, string>>({});
async function save() {
  if (busy.value) return;
  busy.value = true;
  errors.value = {};
  try {
    if (props.inline) {
      const result = await action<{ project: TodoProject }>(
        "/todos/projects",
        "POST",
        { ...form, inline: true },
      );
      emit("created", result.project);
      emit("saved");
      return;
    }
    await submit(
      props.project ? `/todos/projects/${props.project.id}` : "/todos/projects",
      props.project ? "PUT" : "POST",
      form,
    );
    emit("saved");
  } catch (e) {
    errors.value =
      e instanceof ValidationError
        ? e.errors
        : { request: e instanceof Error ? e.message : "Không thể lưu." };
  } finally {
    busy.value = false;
  }
}
</script>
<template>
  <form class="editor" @submit.prevent="save">
    <label
      >{{ t("name")
      }}<input v-model="form.name" required maxlength="255" autofocus /></label
    ><label
      >{{ t("domain")
      }}<input v-model="form.domain" type="url" placeholder="https://…"
    /></label>
    <label
      >{{ t("repo")
      }}<input
        v-model="form.git_repo_url"
        type="url"
        placeholder="https://github.com/…"
    /></label>
    <p
      v-for="(message, key) in errors"
      :key="key"
      class="field-error"
      role="alert"
    >
      {{ message }}
    </p>
    <div class="form-actions">
      <button type="button" @click="emit('cancel')">{{ t("cancel") }}</button
      ><button class="primary" :disabled="busy">
        {{ busy ? "…" : t("save") }}
      </button>
    </div>
  </form>
</template>
