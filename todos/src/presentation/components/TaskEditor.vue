<script setup lang="ts">
import { reactive, ref, computed, watch } from "vue";
import type {
  DependencyCandidate,
  TodoItem,
  TodoProject,
} from "../../domain/todo";
import { submit } from "../../application/navigation";
import { ValidationError } from "../../infrastructure/todos-api";
import Modal from "./Modal.vue";
import ProjectEditor from "./ProjectEditor.vue";
import { t } from "../../application/locale";
const props = defineProps<{
  todo?: TodoItem | null;
  defaults?: Partial<TodoItem>;
  projects: TodoProject[];
  candidates?: DependencyCandidate[];
  returnTo?: "matrix" | "todos";
}>();
const emit = defineEmits<{ saved: []; cancel: [] }>();
function localDate(value?: string | null) {
  if (!value) return "";
  return value.slice(0, 10);
}
const form = reactive({
  project_id: props.todo?.project_id ?? null,
  title: props.todo?.title ?? "",
  description: props.todo?.description ?? "",
  status: props.todo?.status ?? props.defaults?.status ?? "backlog",
  priority: props.todo?.priority ?? "medium",
  due_at: localDate(props.todo?.due_at),
  is_urgent: props.todo?.is_urgent ?? props.defaults?.is_urgent ?? false,
  is_important:
    props.todo?.is_important ?? props.defaults?.is_important ?? false,
  depends_on: (props.todo?.depends_on ?? []).map((d) => d.id),
});
// Only tasks of the selected project can be waited for; keep ones already selected visible.
const dependencyOptions = computed(() => {
  if (!form.project_id) return [];
  const known = new Map<number, DependencyCandidate>();
  for (const c of props.candidates ?? [])
    if (c.project_id === form.project_id && c.id !== props.todo?.id)
      known.set(c.id, c);
  return [...known.values()];
});
watch(
  () => form.project_id,
  () => {
    const allowed = new Set(dependencyOptions.value.map((c) => c.id));
    form.depends_on = form.depends_on.filter((id) => allowed.has(id));
  },
);
const showProject = ref(false),
  createdProjects = ref<TodoProject[]>([]);
const availableProjects = computed(() => [
  ...props.projects,
  ...createdProjects.value.filter(
    (p) => !props.projects.some((old) => old.id === p.id),
  ),
]);
function projectCreated(project: TodoProject) {
  createdProjects.value.push(project);
  form.project_id = project.id;
  showProject.value = false;
}
async function remove() {
  if (!props.todo || !confirm(t("confirmDelete")) || busy.value) return;
  busy.value = true;
  try {
    await submit(`/todos/${props.todo.id}`, "DELETE", {
      return_to: props.returnTo ?? "todos",
    });
    emit("saved");
  } catch (e) {
    errors.value = {
      request: e instanceof Error ? e.message : "Không thể xóa.",
    };
  } finally {
    busy.value = false;
  }
}
const busy = ref(false),
  errors = ref<Record<string, string>>({});
async function save() {
  if (busy.value) return;
  busy.value = true;
  errors.value = {};
  try {
    await submit(
      props.todo ? `/todos/${props.todo.id}` : "/todos",
      props.todo ? "PUT" : "POST",
      {
        ...form,
        due_at: form.due_at
          ? new Date(`${form.due_at}T00:00:00`).toISOString()
          : null,
        project_id: form.project_id || null,
        return_to: props.returnTo ?? "todos",
      },
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
      >{{ t("title")
      }}<input v-model="form.title" required maxlength="255" autofocus /><span
        v-if="errors.title"
        class="field-error"
        >{{ errors.title }}</span
      ></label
    ><label
      >{{ t("projects")
      }}<select v-model="form.project_id">
        <option :value="null">—</option>
        <option
          v-for="project in availableProjects"
          :key="project.id"
          :value="project.id"
        >
          {{ project.name }}
        </option>
      </select></label
    >
    <button type="button" class="small text-button" @click="showProject = true">
      + {{ t("newProject") }}
    </button>
    <fieldset class="dependencies">
      <legend>{{ t("waitsFor") }}</legend>
      <p v-if="!dependencyOptions.length" class="muted">
        {{ t("noDependencyCandidates") }}
      </p>
      <label
        v-for="candidate in dependencyOptions"
        :key="candidate.id"
        class="checkbox"
        ><input
          v-model="form.depends_on"
          type="checkbox"
          :value="candidate.id"
        />#{{ candidate.id }}
        {{ candidate.title }}
        <span class="badge" :class="candidate.status">{{
          t(candidate.status)
        }}</span></label
      >
    </fieldset>
    <label
      >{{ t("description") }}<textarea v-model="form.description" rows="4" />
    </label>
    <div class="form-grid">
      <label
        >{{ t("status")
        }}<select v-model="form.status">
          <option
            v-for="status in [
              'backlog',
              'todo',
              'in_progress',
              'in_review',
              'done',
              'rejected',
            ]"
            :key="status"
            :value="status"
          >
            {{ t(status) }}
          </option>
        </select></label
      ><label
        >{{ t("priority")
        }}<select v-model="form.priority">
          <option
            v-for="priority in ['low', 'medium', 'high', 'urgent']"
            :key="priority"
            :value="priority"
          >
            {{ t(priority) }}
          </option>
        </select></label
      >
    </div>
    <label>{{ t("due") }}<input v-model="form.due_at" type="date" /></label>
    <div class="row">
      <label class="checkbox"
        ><input v-model="form.is_urgent" type="checkbox" />{{
          t("urgent")
        }}</label
      ><label class="checkbox"
        ><input v-model="form.is_important" type="checkbox" />{{
          t("important")
        }}</label
      >
    </div>
    <p
      v-for="(message, key) in errors"
      :key="key"
      class="field-error"
      role="alert"
    >
      {{ message }}
    </p>
    <div class="form-actions">
      <button
        v-if="todo"
        type="button"
        class="danger"
        :disabled="busy"
        @click="remove"
      >
        {{ t("delete") }}
      </button>
      <button type="button" @click="emit('cancel')">{{ t("cancel") }}</button
      ><button class="primary" :disabled="busy">
        {{ busy ? "…" : t("save") }}
      </button>
    </div>
  </form>
  <Modal
    v-if="showProject"
    :title="t('newProject')"
    @close="showProject = false"
    ><ProjectEditor
      inline
      @created="projectCreated"
      @cancel="showProject = false"
  /></Modal>
</template>
