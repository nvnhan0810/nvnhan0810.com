<script setup lang="ts">
import { computed, onMounted, reactive, ref } from "vue";
import type { TodoProject } from "../../domain/todo";
import { submit } from "../../application/navigation";
import { action, ValidationError } from "../../infrastructure/todos-api";
import { t } from "../../application/locale";
import SubmitButton from "./SubmitButton.vue";
import {
  agentCatalog,
  catalogProblem,
  type AgentCatalog,
} from "../../application/agent-link";
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
  agent_workspace_id: props.project?.agent_workspace_id ?? "",
  identifier_prefix: props.project?.identifier_prefix ?? "",
});
const busy = ref(false),
  errors = ref<Record<string, string>>({});

// Agent workspace picker. Loaded once; a failure only disables the picker and explains why.
const catalog = ref<AgentCatalog | null>(null),
  catalogIssue = ref("");
const originalWorkspace = props.project?.agent_workspace_id ?? "";
const workspaceChanged = computed(
  () => (form.agent_workspace_id || "") !== originalWorkspace,
);
// Keep the stored name visible when the Agent is offline and the selection is unchanged.
const storedWorkspace = computed(() =>
  props.project?.agent_workspace_id && props.project.agent_workspace_name
    ? {
        id: props.project.agent_workspace_id,
        name: props.project.agent_workspace_name,
      }
    : null,
);
onMounted(async () => {
  try {
    catalog.value = await agentCatalog();
  } catch (e) {
    catalogIssue.value = catalogProblem(e);
  }
});
function payload() {
  return { ...form, agent_workspace_id: form.agent_workspace_id || null };
}
async function save() {
  if (busy.value) return;
  busy.value = true;
  errors.value = {};
  try {
    if (props.inline) {
      const result = await action<{ project: TodoProject }>(
        "/todos/projects",
        "POST",
        { ...payload(), inline: true },
      );
      emit("created", result.project);
      emit("saved");
      return;
    }
    await submit(
      props.project ? `/todos/projects/${props.project.id}` : "/todos/projects",
      props.project ? "PUT" : "POST",
      payload(),
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
  <form
    class="editor"
    :aria-busy="busy ? 'true' : undefined"
    @submit.prevent="save"
  >
    <fieldset class="editor-body" :disabled="busy">
      <label
        >{{ t("name")
        }}<input
          v-model="form.name"
          required
          maxlength="255"
          autofocus /></label
      ><label
        >{{ t("identifierPrefix")
        }}<input
          v-model="form.identifier_prefix"
          maxlength="10"
          placeholder="DEF" /></label
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
      <label
        >{{ t("agentWorkspace")
        }}<select
          v-model="form.agent_workspace_id"
          :disabled="!catalog && !storedWorkspace"
        >
          <option value="">{{ t("agentWorkspaceNone") }}</option>
          <option
            v-for="workspace in catalog?.workspaces ?? []"
            :key="workspace.id"
            :value="workspace.id"
          >
            {{ workspace.name }}
          </option>
          <option
            v-if="
              storedWorkspace &&
              !catalog?.workspaces.some((w) => w.id === storedWorkspace?.id)
            "
            :value="storedWorkspace.id"
          >
            {{ storedWorkspace.name }}
          </option>
        </select></label
      >
      <p v-if="catalogIssue" class="muted">{{ catalogIssue }}</p>
      <p v-if="workspaceChanged && originalWorkspace" class="muted">
        {{ t("agentWorkspaceChanged") }}
      </p>
    </fieldset>
    <p
      v-for="(message, key) in errors"
      :key="key"
      class="field-error"
      role="alert"
    >
      {{ message }}
    </p>
    <div class="form-actions">
      <button type="button" :disabled="busy" @click="emit('cancel')">
        {{ t("cancel") }}</button
      ><SubmitButton :busy="busy">{{ t("save") }}</SubmitButton>
    </div>
  </form>
</template>
