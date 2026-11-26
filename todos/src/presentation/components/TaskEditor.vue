<script setup lang="ts">
import { reactive, ref, computed, onMounted, watch } from "vue";
import {
  agentBadgeState,
  needsMergeBeforeDone,
  safeHttpUrl,
} from "../../domain/todo";
import type {
  DependencyCandidate,
  TodoItem,
  TodoProject,
} from "../../domain/todo";
import { submit } from "../../application/navigation";
import { ValidationError } from "../../infrastructure/todos-api";
import Modal from "./Modal.vue";
import ProjectEditor from "./ProjectEditor.vue";
import { locale, t } from "../../application/locale";
import {
  agentCatalog,
  catalogProblem,
  workspaceRepos,
  type AgentCatalog,
} from "../../application/agent-link";
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
  agent_repo_id: props.todo?.agent_repo_id ?? null,
  agent_auto: props.todo?.agent_auto ?? false,
  agent_base_branch: props.todo?.agent_base_branch ?? "",
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
// Agent run panel (Phase 4): state, branch, PR, chat, last result and timeline. Read-only.
const agentState = computed(() =>
  props.todo ? agentBadgeState(props.todo) : null,
);
const agentPanelVisible = computed(() => {
  const todo = props.todo;
  return Boolean(
    todo &&
    ((todo.agent_state && todo.agent_state !== "idle") ||
      todo.agent_branch ||
      todo.agent_pr_url ||
      todo.agent_result_md ||
      todo.agent_events?.length),
  );
});
const prHref = computed(() => safeHttpUrl(props.todo?.agent_pr_url));
const chatHref = computed(() => safeHttpUrl(props.todo?.agent_chat_url));
const events = computed(() => props.todo?.agent_events ?? []);
const warnDone = computed(() =>
  props.todo ? needsMergeBeforeDone(props.todo, form.status) : false,
);
function eventTime(iso: string) {
  const date = new Date(iso);
  return Number.isNaN(date.getTime())
    ? ""
    : date.toLocaleString(locale.value === "vi" ? "vi-VN" : "en-US");
}

// Agent repo: only repos of the project's Agent workspace. Loaded lazily, once.
const catalog = ref<AgentCatalog | null>(null),
  catalogIssue = ref(""),
  catalogCode = ref("");
const catalogLoading = ref(false);
function workspaceOf(projectId: number | null) {
  return (
    props.projects.find((p) => p.id === projectId)?.agent_workspace_id ?? null
  );
}
const originalWorkspace = workspaceOf(props.todo?.project_id ?? null);
const workspaceId = computed(() => workspaceOf(form.project_id));
const repos = computed(() => workspaceRepos(catalog.value, workspaceId.value));
const selectedRepo = computed(() =>
  repos.value.find((r) => r.id === form.agent_repo_id),
);
// Placeholder for the task base: the repo default from Agent, if known.
const repoBase = computed(() => selectedRepo.value?.base_branch || "main");
// A task with its own base does not need the repo default to exist (Agent checks it on fetch).
const ownBase = computed(() => form.agent_base_branch.trim() !== "");
// With the catalog we know whether the repo can run. Without it (Agent offline) we only keep
// what was already stored; the server re-checks on save.
// Without a repo the task runs in the workspace root: only the project's workspace is needed.
const autoDisabled = computed(() => {
  if (!workspaceId.value) return true;
  if (!form.agent_repo_id) return catalogCode.value === "not_linked";
  if (catalog.value) return !ownBase.value && !selectedRepo.value?.has_main;
  return catalogCode.value === "not_linked";
});
const autoHint = computed(() => {
  if (!form.agent_repo_id) return t("agentAutoRootHint");
  if (
    catalog.value &&
    selectedRepo.value &&
    !ownBase.value &&
    !selectedRepo.value.has_main
  )
    return t("agentRepoNeedsMain");
  if (catalogIssue.value) return catalogIssue.value;
  return t("agentAutoHint");
});
async function ensureCatalog() {
  if (!workspaceId.value || catalog.value || catalogLoading.value) return;
  catalogLoading.value = true;
  try {
    catalog.value = await agentCatalog();
    catalogIssue.value = "";
  } catch (e) {
    catalogCode.value = e instanceof Error ? e.message : "";
    catalogIssue.value = catalogProblem(e);
  } finally {
    catalogLoading.value = false;
  }
}
onMounted(ensureCatalog);
watch(
  () => form.project_id,
  () => {
    const allowed = new Set(dependencyOptions.value.map((c) => c.id));
    form.depends_on = form.depends_on.filter((id) => allowed.has(id));
    // A repo belongs to one workspace: switching projects unpicks it when we know it is gone.
    const stillValid = catalog.value
      ? repos.value.some((r) => r.id === form.agent_repo_id)
      : workspaceId.value === originalWorkspace;
    if (!stillValid) form.agent_repo_id = null;
    ensureCatalog();
  },
);
watch(
  () => form.agent_repo_id,
  (id) => {
    if (!id) form.agent_auto = false;
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
    <p v-if="todo?.identifier" class="task-identifier-line">
      <span class="task-identifier-label">{{ t("identifier") }}</span>
      <code class="task-identifier" :title="t('identifierHint')">{{
        todo.identifier
      }}</code>
    </p>
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
    <label
      >{{ t("agentRepo")
      }}<select v-model="form.agent_repo_id" :disabled="!workspaceId">
        <option :value="null">{{ t("agentRepoNone") }}</option>
        <option
          v-for="repo in repos"
          :key="repo.id"
          :value="repo.id"
          :disabled="!repo.has_main && !ownBase"
        >
          {{ repo.name }}{{ repo.has_main ? "" : ` ${t("agentNoMain")}` }}
        </option>
        <option
          v-if="form.agent_repo_id && !selectedRepo"
          :value="form.agent_repo_id"
        >
          {{ todo?.agent_repo_name || form.agent_repo_id }}
        </option>
      </select></label
    >
    <label v-if="form.agent_repo_id"
      >{{ t("agentBaseBranch")
      }}<input
        v-model="form.agent_base_branch"
        maxlength="120"
        :placeholder="repoBase"
    /></label>
    <p v-if="form.project_id && !workspaceId" class="muted">
      {{ t("agentPickWorkspaceFirst") }}
    </p>
    <p v-if="catalogIssue && workspaceId" class="muted">{{ catalogIssue }}</p>
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
    <label class="checkbox"
      ><input
        v-model="form.agent_auto"
        type="checkbox"
        :disabled="autoDisabled && !form.agent_auto"
      />{{ t("agentAuto") }}</label
    >
    <p class="muted">{{ autoHint }}</p>
    <section v-if="todo && agentPanelVisible" class="agent-panel">
      <h3>{{ t("agentPanel") }}</h3>
      <p>
        <span v-if="agentState" class="badge" :class="`agent-${agentState}`">{{
          t(`agent_${agentState}`)
        }}</span>
        <span v-if="todo.agent_pr_has_conflict" class="badge conflict">{{
          t("agentConflict")
        }}</span>
      </p>
      <p v-if="todo.agent_branch">
        {{ t("agentBranch") }}: <code>{{ todo.agent_branch }}</code>
      </p>
      <p v-if="prHref">
        {{ t("agentPr") }}:
        <a :href="prHref" target="_blank" rel="noopener noreferrer">{{
          todo.agent_pr_url
        }}</a>
      </p>
      <p v-if="chatHref">
        <a :href="chatHref" target="_blank" rel="noopener noreferrer">{{
          t("agentChat")
        }}</a>
      </p>
      <p v-if="warnDone" class="field-error" role="status">
        {{ t("agentDoneWarning") }}
      </p>
      <details v-if="todo.agent_result_md">
        <summary>{{ t("agentResult") }}</summary>
        <pre class="agent-result">{{ todo.agent_result_md }}</pre>
      </details>
      <details v-if="events.length">
        <summary>{{ t("agentTimeline") }}</summary>
        <ol>
          <li v-for="(event, index) in events" :key="event.id ?? index">
            {{ eventTime(event.created_at) }} · {{ t(`ev_${event.kind}`) }}
            <template v-if="event.message">— {{ event.message }}</template>
          </li>
        </ol>
      </details>
    </section>
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
