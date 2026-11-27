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
import { ChevronLeft, ChevronRight } from "lucide-vue-next";
import ProjectEditor from "./ProjectEditor.vue";
import SubmitButton from "./SubmitButton.vue";
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
// Without a repo the Agent works in the project's workspace root, so only the workspace is needed.
const autoDisabled = computed(() => {
  if (!workspaceId.value) return true;
  if (catalog.value && form.agent_repo_id)
    return !ownBase.value && !selectedRepo.value?.has_main;
  return catalogCode.value === "not_linked";
});
const autoHint = computed(() => {
  if (!workspaceId.value) return t("agentAutoNeedsWorkspace");
  if (
    catalog.value &&
    selectedRepo.value &&
    !ownBase.value &&
    !selectedRepo.value.has_main
  )
    return t("agentRepoNeedsMain");
  if (catalogIssue.value) return catalogIssue.value;
  if (!form.agent_repo_id) return t("agentAutoWorkspaceHint");
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
watch(workspaceId, (id) => {
  if (!id) form.agent_auto = false;
});
// The editor is split into a short main view plus sub views (dependencies, Agent, new
// project) so that on mobile each screen stays small; sub views are only hidden (v-show) so
// their state is part of the same form.
type View = "main" | "deps" | "agent" | "project";
const view = ref<View>("main");
const NEW_PROJECT = "__new__";
const createdProjects = ref<TodoProject[]>([]),
  projectSelectKey = ref(0);
const availableProjects = computed(() => [
  ...props.projects,
  ...createdProjects.value.filter(
    (p) => !props.projects.some((old) => old.id === p.id),
  ),
]);
// The last option of the project select opens the new-project view instead of selecting.
const projectChoice = computed<number | null | typeof NEW_PROJECT>({
  get: () => form.project_id,
  set: (value) => {
    if (value === NEW_PROJECT) {
      view.value = "project";
      // Re-mount the select so it shows the current project again, not the action option.
      projectSelectKey.value++;
      return;
    }
    form.project_id = value;
  },
});
function projectCreated(project: TodoProject) {
  createdProjects.value.push(project);
  form.project_id = project.id;
  view.value = "main";
}
const dependencySummary = computed(() => {
  if (!form.depends_on.length) return t("noneSelected");
  const names = form.depends_on.map(
    (id) =>
      props.candidates?.find((c) => c.id === id)?.identifier ||
      props.todo?.depends_on?.find((d) => d.id === id)?.identifier ||
      `${id}`,
  );
  return names.length > 2
    ? `${names.length} ${t("selectedCount")}`
    : names.join(", ");
});
const agentSummary = computed(() => {
  if (!form.project_id) return t("agentNeedsProject");
  const repo =
    selectedRepo.value?.name ||
    (form.agent_repo_id ? props.todo?.agent_repo_name || "" : "");
  const mode = form.agent_auto ? t("agentAuto") : t("agentManual");
  return repo ? `${repo} · ${mode}` : mode;
});
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
// Pause / resume of the Agent run (agent panel).
const canPause = computed(
  () =>
    (props.todo?.agent_state === "claimed" ||
      props.todo?.agent_state === "running") &&
    !props.todo.agent_pause_requested,
);
const pausing = computed(
  () =>
    (props.todo?.agent_state === "claimed" ||
      props.todo?.agent_state === "running") &&
    Boolean(props.todo.agent_pause_requested),
);
async function agentAction(action: "pause" | "resume") {
  if (!props.todo || busy.value) return;
  busy.value = true;
  errors.value = {};
  try {
    await submit(`/todos/${props.todo.id}/agent/${action}`, "POST", {
      return_to: props.returnTo ?? "todos",
    });
    emit("saved");
  } catch (e) {
    errors.value =
      e instanceof ValidationError
        ? e.errors
        : { request: e instanceof Error ? e.message : "Không thực hiện được." };
  } finally {
    busy.value = false;
  }
}
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
  <div class="task-editor">
    <form
      v-show="view !== 'project'"
      class="editor"
      :aria-busy="busy ? 'true' : undefined"
      @submit.prevent="save"
    >
      <fieldset class="editor-body" :disabled="busy">
        <!-- Main view: three short groups; long lists live in sub views. -->
        <div v-show="view === 'main'" class="editor-view">
          <section class="form-section">
            <h3 class="form-section-title">
              {{ t("groupTask") }}
              <code
                v-if="todo?.identifier"
                class="task-identifier"
                :title="t('identifierHint')"
                >{{ todo.identifier }}</code
              >
            </h3>
            <label
              >{{ t("title")
              }}<input
                v-model="form.title"
                required
                maxlength="255"
                autofocus
              /><span v-if="errors.title" class="field-error">{{
                errors.title
              }}</span></label
            >
            <label
              >{{ t("description")
              }}<textarea v-model="form.description" rows="3" />
            </label>
          </section>
          <section class="form-section">
            <h3 class="form-section-title">{{ t("groupPlanning") }}</h3>
            <div class="form-grid compact">
              <label
                >{{ t("status")
                }}<select v-model="form.status" name="status">
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
                }}<select v-model="form.priority" name="priority">
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
            <p v-if="warnDone" class="field-error" role="status">
              {{ t("agentDoneWarning") }}
            </p>
            <label
              >{{ t("due") }}<input v-model="form.due_at" type="date"
            /></label>
            <div class="chip-row">
              <label class="checkbox chip"
                ><input v-model="form.is_urgent" type="checkbox" />{{
                  t("urgent")
                }}</label
              ><label class="checkbox chip"
                ><input v-model="form.is_important" type="checkbox" />{{
                  t("important")
                }}</label
              >
            </div>
          </section>
          <section class="form-section">
            <h3 class="form-section-title">{{ t("groupLinks") }}</h3>
            <label
              >{{ t("projects")
              }}<select
                :key="projectSelectKey"
                v-model="projectChoice"
                name="project_id"
              >
                <option :value="null">—</option>
                <option
                  v-for="project in availableProjects"
                  :key="project.id"
                  :value="project.id"
                >
                  {{ project.name }}
                </option>
                <option :value="NEW_PROJECT" class="new-project-option">
                  {{ t("newProjectOption") }}
                </option>
              </select></label
            >
            <div class="nav-rows">
              <button
                type="button"
                class="nav-row"
                data-view="deps"
                @click="view = 'deps'"
              >
                <span class="nav-row-label">{{ t("waitsFor") }}</span>
                <span class="nav-row-value">{{ dependencySummary }}</span>
                <ChevronRight :size="16" />
              </button>
              <button
                type="button"
                class="nav-row"
                data-view="agent"
                @click="view = 'agent'"
              >
                <span class="nav-row-label">{{ t("agentPanel") }}</span>
                <span class="nav-row-value">
                  <span
                    v-if="agentState"
                    class="badge"
                    :class="`agent-${agentState}`"
                    >{{ t(`agent_${agentState}`) }}</span
                  >
                  {{ agentSummary }}</span
                >
                <ChevronRight :size="16" />
              </button>
            </div>
          </section>
        </div>

        <!-- Sub view: dependencies. -->
        <div v-show="view === 'deps'" class="editor-view">
          <div class="subview-heading">
            <button
              type="button"
              class="icon-button subview-back"
              :aria-label="t('back')"
              @click="view = 'main'"
            >
              <ChevronLeft :size="18" />
            </button>
            <h3>{{ t("waitsFor") }}</h3>
          </div>
          <fieldset class="dependencies">
            <legend class="sr-only">{{ t("waitsFor") }}</legend>
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
              /><span class="dependency-name"
                ><small class="task-identifier">{{
                  candidate.identifier
                }}</small>
                {{ candidate.title }}</span
              >
              <span class="badge" :class="candidate.status">{{
                t(candidate.status)
              }}</span></label
            >
          </fieldset>
        </div>

        <!-- Sub view: Agent settings and run panel. -->
        <div v-show="view === 'agent'" class="editor-view">
          <div class="subview-heading">
            <button
              type="button"
              class="icon-button subview-back"
              :aria-label="t('back')"
              @click="view = 'main'"
            >
              <ChevronLeft :size="18" />
            </button>
            <h3>{{ t("agentPanel") }}</h3>
          </div>
          <label
            >{{ t("agentRepo")
            }}<select
              v-model="form.agent_repo_id"
              name="agent_repo_id"
              :disabled="!workspaceId"
            >
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
              @keydown.enter.prevent="view = 'main'"
          /></label>
          <p v-if="form.project_id && !workspaceId" class="muted">
            {{ t("agentPickWorkspaceFirst") }}
          </p>
          <p v-if="catalogIssue && workspaceId" class="muted">
            {{ catalogIssue }}
          </p>
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
              <span
                v-if="agentState"
                class="badge"
                :class="`agent-${agentState}`"
                >{{ t(`agent_${agentState}`) }}</span
              >
              <span v-if="todo.agent_pr_has_conflict" class="badge conflict">{{
                t("agentConflict")
              }}</span>
            </p>
            <p v-if="canPause">
              <button
                type="button"
                :disabled="busy"
                @click="agentAction('pause')"
              >
                {{ t("agentPause") }}
              </button>
            </p>
            <p v-else-if="pausing" class="muted" role="status">
              {{ t("agentPausing") }}
            </p>
            <template v-else-if="todo.agent_state === 'paused'">
              <p class="muted">{{ t("agentPausedHint") }}</p>
              <p>
                <button
                  type="button"
                  :disabled="busy"
                  @click="agentAction('resume')"
                >
                  {{ t("agentResume") }}
                </button>
              </p>
            </template>
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
            <details v-if="todo.agent_result_md">
              <summary>{{ t("agentResult") }}</summary>
              <pre class="agent-result">{{ todo.agent_result_md }}</pre>
            </details>
            <details v-if="events.length">
              <summary>{{ t("agentTimeline") }}</summary>
              <ol>
                <li v-for="(event, index) in events" :key="event.id ?? index">
                  {{ eventTime(event.created_at) }} ·
                  {{ t(`ev_${event.kind}`) }}
                  <template v-if="event.message"
                    >— {{ event.message }}</template
                  >
                </li>
              </ol>
            </details>
          </section>
        </div>
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
        <template v-if="view === 'main'">
          <button
            v-if="todo"
            type="button"
            class="danger"
            :disabled="busy"
            @click="remove"
          >
            {{ t("delete") }}
          </button>
          <button type="button" :disabled="busy" @click="emit('cancel')">
            {{ t("cancel") }}</button
          ><SubmitButton :busy="busy">{{ t("save") }}</SubmitButton>
        </template>
        <button
          v-else
          type="button"
          class="primary"
          :disabled="busy"
          @click="view = 'main'"
        >
          {{ t("apply") }}
        </button>
      </div>
    </form>
    <!-- Sub view: new project. Outside the task form, a form cannot be nested. -->
    <div v-if="view === 'project'" class="editor-view">
      <div class="subview-heading">
        <button
          type="button"
          class="icon-button subview-back"
          :aria-label="t('back')"
          @click="view = 'main'"
        >
          <ChevronLeft :size="18" />
        </button>
        <h3>{{ t("newProject") }}</h3>
      </div>
      <ProjectEditor inline @created="projectCreated" @cancel="view = 'main'" />
    </div>
  </div>
</template>
