<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from "vue";
import {
  Plus,
  Check,
  Maximize2,
  Minimize2,
  MoreVertical,
  Inbox,
  Play,
  X,
  Settings,
  Bell,
  SunMoon,
} from "lucide-vue-next";
import type {
  TodoItem,
  TodoProject,
  MatrixQuadrants,
  MatrixQuadrantKey,
} from "../../domain/todo";
import type { PomodoroPayload } from "../../domain/pomodoro";
import { isPomodoro } from "../../domain/pomodoro";
import { matrixStream } from "../../infrastructure/matrix-stream";
import { submit } from "../../application/navigation";
import { action } from "../../infrastructure/todos-api";
import { t } from "../../application/locale";
import Modal from "../components/Modal.vue";
import TaskEditor from "../components/TaskEditor.vue";
import PomodoroBar from "../components/PomodoroBar.vue";
const props = defineProps<{
  quadrants: MatrixQuadrants;
  backlog: TodoItem[];
  projects: TodoProject[];
  pomodoro: PomodoroPayload;
  version: number;
  statuses?: string[];
  priorities?: string[];
}>();
const live = ref({
    quadrants: props.quadrants,
    backlog: props.backlog,
    projects: props.projects,
    pomodoro: props.pomodoro,
  }),
  connected = ref(true),
  error = ref(""),
  busy = ref(false),
  editing = ref<TodoItem | null | undefined>(),
  showBacklog = ref(false),
  selected = ref<Record<number, MatrixQuadrantKey>>({}),
  fullscreen = ref(false);
const quadrantMeta: {
  key: MatrixQuadrantKey;
  urgent: boolean;
  important: boolean;
  number: string;
}[] = [
  { key: "do", urgent: true, important: true, number: "01" },
  { key: "schedule", urgent: false, important: true, number: "02" },
  { key: "delegate", urgent: true, important: false, number: "03" },
  { key: "eliminate", urgent: false, important: false, number: "04" },
];
const allTasks = computed(() => Object.values(live.value.quadrants).flat());
watch(
  () =>
    [props.quadrants, props.backlog, props.projects, props.pomodoro] as const,
  () => {
    live.value = {
      quadrants: props.quadrants,
      backlog: props.backlog,
      projects: props.projects,
      pomodoro: props.pomodoro,
    };
  },
);
const createDefaults = ref<Partial<TodoItem>>({});
const pomodoroBar = ref<InstanceType<typeof PomodoroBar> | null>(null);
function toggleTheme() {
  const theme =
    document.documentElement.dataset.theme === "dark" ? "light" : "dark";
  document.documentElement.dataset.theme = theme;
  localStorage.setItem("todos.theme", theme);
}
function createIn(key: MatrixQuadrantKey) {
  const q = quadrantMeta.find((q) => q.key === key)!;
  createDefaults.value = {
    status: "todo",
    is_urgent: q.urgent,
    is_important: q.important,
  };
  editing.value = null;
}
const controller = new AbortController();
onMounted(() => {
  void matrixStream(
    controller.signal,
    (data) => {
      if (!data || typeof data !== "object") return;
      const d = data as typeof live.value;
      if (!d.quadrants || !Array.isArray(d.backlog) || !isPomodoro(d.pomodoro))
        return;
      live.value = {
        quadrants: d.quadrants,
        backlog: d.backlog,
        projects: d.projects,
        pomodoro: d.pomodoro,
      };
    },
    (value) => {
      connected.value = value;
    },
  );
});
onUnmounted(() => controller.abort());
async function run(operation: () => Promise<unknown>) {
  if (busy.value) return;
  busy.value = true;
  error.value = "";
  try {
    await operation();
  } catch (e) {
    error.value = e instanceof Error ? e.message : "Không thể cập nhật.";
  } finally {
    busy.value = false;
  }
}
async function move(todo: TodoItem, key: MatrixQuadrantKey) {
  const quadrant = quadrantMeta.find((q) => q.key === key)!;
  await run(() =>
    submit(`/matrix/todos/${todo.id}`, "PATCH", {
      is_urgent: quadrant.urgent,
      is_important: quadrant.important,
      status: todo.status === "in_progress" ? "in_progress" : "todo",
    }),
  );
}
function drag(event: DragEvent, todo: TodoItem) {
  event.dataTransfer?.setData("text/plain", String(todo.id));
  if (event.dataTransfer) event.dataTransfer.effectAllowed = "move";
}
function drop(event: DragEvent, key: MatrixQuadrantKey) {
  event.preventDefault();
  const id = Number(event.dataTransfer?.getData("text/plain"));
  const task = allTasks.value.find((t) => t.id === id);
  if (task) void move(task, key);
}
async function complete(todo: TodoItem) {
  await run(() => submit(`/matrix/todos/${todo.id}/complete`, "PATCH"));
}
async function focus(todo: TodoItem) {
  await run(async () => {
    live.value.pomodoro = await action<PomodoroPayload>(
      "/matrix/pomodoro/start",
      "POST",
      { activeTodoId: todo.id },
    );
    if (todo.status === "todo")
      await submit(`/matrix/todos/${todo.id}`, "PATCH", {
        is_urgent: todo.is_urgent,
        is_important: todo.is_important,
        status: "in_progress",
      });
  });
}
async function promote() {
  const items = Object.entries(selected.value).map(([id, key]) => {
    const q = quadrantMeta.find((q) => q.key === key)!;
    return { id: Number(id), is_urgent: q.urgent, is_important: q.important };
  });
  await run(() => submit("/matrix/backlog/promote", "POST", { items }));
  if (!error.value) {
    showBacklog.value = false;
    selected.value = {};
  }
}
function choose(todo: TodoItem, event: Event) {
  if ((event.target as HTMLInputElement).checked)
    selected.value[todo.id] = "do";
  else delete selected.value[todo.id];
}
function remaining(value: string | null) {
  return value ? new Date(value).toLocaleDateString() : "";
}
async function clearActiveTask() {
  try {
    live.value.pomodoro = await action<PomodoroPayload>(
      "/matrix/pomodoro/active-todo",
      "PATCH",
      { activeTodoId: null },
    );
  } catch (e) {
    error.value = e instanceof Error ? e.message : "Không thể cập nhật.";
  }
}
</script>
<template>
  <div class="matrix-page" :class="{ fullscreen }">
    <div class="matrix-toolbar">
      <h1>
        {{ t("matrix") }}
        <button
          class="icon-button create-backlog"
          :title="t('createBacklog')"
          :aria-label="t('createBacklog')"
          @click="
            createDefaults = { status: 'backlog' };
            editing = null;
          "
        >
          <Plus :size="16" />
        </button>
      </h1>
      <div class="matrix-toolbar-actions">
        <span class="live-status" :class="{ online: connected }">
          <i />{{ connected ? t("live") : t("offline") }}
        </span>
        <span class="matrix-legend"
          ><i class="todo-dot" />{{ t("legendTodo") }}</span
        >
        <span class="matrix-legend"
          ><i class="progress-dot" />{{ t("legendProgress") }}</span
        >
        <button
          class="icon-button bordered"
          :aria-label="fullscreen ? t('exitFullscreen') : t('fullscreen')"
          :title="fullscreen ? t('exitFullscreen') : t('fullscreen')"
          @click="fullscreen = !fullscreen"
        >
          <Minimize2 v-if="fullscreen" :size="16" /><Maximize2
            v-else
            :size="16"
          />
        </button>
        <button
          class="icon-button bordered"
          :aria-label="t('theme')"
          :title="t('theme')"
          @click="toggleTheme"
        >
          <SunMoon :size="16" />
        </button>
        <details class="matrix-more">
          <summary
            class="icon-button bordered"
            :aria-label="t('moreActions')"
            :title="t('moreActions')"
          >
            <MoreVertical :size="16" />
          </summary>
          <div class="matrix-menu">
            <button @click="showBacklog = true">
              <Inbox :size="16" />{{ t("backlog")
              }}<span>{{ live.backlog.length }}</span>
            </button>
            <button @click="pomodoroBar?.openSettings()">
              <Settings :size="16" />{{ t("settings") }}
            </button>
            <button @click="pomodoroBar?.notify()">
              <Bell :size="16" />{{ t("notifications") }}
            </button>
          </div>
        </details>
      </div>
    </div>
    <PomodoroBar
      ref="pomodoroBar"
      :payload="live.pomodoro"
      :tasks="allTasks"
      @complete-active="complete"
    />
    <p v-if="error" role="alert" class="error">{{ error }}</p>
    <div class="matrix-grid">
      <section
        v-for="quadrant in quadrantMeta"
        :key="quadrant.key"
        class="quadrant"
        :class="quadrant.key"
        :title="t('quadrantCreateHint')"
        @click="createIn(quadrant.key)"
        @dragover.prevent
        @drop="drop($event, quadrant.key)"
      >
        <div class="quadrant-heading">
          <div>
            <span class="eyebrow"
              >{{ quadrant.urgent ? t("urgent") : t("notUrgent") }} ·
              {{
                quadrant.important ? t("important") : t("lessImportant")
              }}</span
            >
            <h2>{{ t(quadrant.key) }}</h2>
          </div>
          <span class="count">{{ live.quadrants[quadrant.key].length }}</span>
        </div>
        <div class="quadrant-tasks">
          <article
            v-for="todo in live.quadrants[quadrant.key]"
            :key="todo.id"
            class="task-card"
            :class="todo.status"
            :data-todo-id="todo.id"
            draggable="true"
            @click.stop="editing = todo"
            @dragstart="drag($event, todo)"
          >
            <div class="task-card-top">
              <span class="task-title">{{ todo.title }}</span>
              <div class="task-card-tools">
                <template v-if="live.pomodoro.runtime.activeTodoId === todo.id">
                  <img
                    :src="
                      live.pomodoro.runtime.phase === 'focus'
                        ? '/images/todos/work.gif'
                        : '/images/todos/relax.gif'
                    "
                    alt=""
                    width="22"
                    height="22"
                  />
                  <button
                    class="icon-button task-tool clear-focus"
                    :aria-label="t('clearFocus')"
                    :title="t('clearFocus')"
                    @click.stop="clearActiveTask"
                  >
                    <X :size="14" />
                  </button>
                  <button
                    class="icon-button task-tool complete"
                    :aria-label="t('done')"
                    :title="t('done')"
                    @click.stop="complete(todo)"
                  >
                    <Check :size="14" />
                  </button>
                </template>
                <button
                  v-else
                  class="icon-button task-tool focus-task"
                  :aria-label="t('focus')"
                  :title="t('focus')"
                  @click.stop="focus(todo)"
                >
                  <Play :size="14" />
                </button>
              </div>
            </div>
            <div class="task-meta">
              <span v-if="todo.project" class="task-project">{{
                todo.project.name
              }}</span>
              <span class="task-priority">{{ t(todo.priority) }}</span>
              <span
                v-if="todo.due_at"
                class="due"
                :class="{ overdue: new Date(todo.due_at) < new Date() }"
                >{{ t("due") }} {{ remaining(todo.due_at) }}</span
              >
            </div>
          </article>
          <p v-if="!live.quadrants[quadrant.key].length" class="empty">
            {{ t("empty") }}
          </p>
        </div>
      </section>
    </div>
    <Modal
      v-if="editing !== undefined"
      :title="editing ? t('edit') : t('newTask')"
      @close="editing = undefined"
      ><TaskEditor
        :todo="editing"
        :defaults="createDefaults"
        :projects="live.projects"
        return-to="matrix"
        @saved="editing = undefined"
        @cancel="editing = undefined" /></Modal
    ><Modal
      v-if="showBacklog"
      :title="t('backlog')"
      @close="showBacklog = false"
      ><div class="backlog-list">
        <div v-for="todo in live.backlog" :key="todo.id" class="backlog-item">
          <label class="checkbox"
            ><input
              type="checkbox"
              :checked="todo.id in selected"
              @change="choose(todo, $event)"
            /><span
              >{{ todo.title }}
              <small class="muted">{{ todo.project?.name }}</small></span
            ></label
          ><select
            v-if="todo.id in selected"
            v-model="selected[todo.id]"
            :aria-label="t('matrix')"
          >
            <option v-for="q in quadrantMeta" :key="q.key" :value="q.key">
              {{ t(q.key) }}
            </option>
          </select>
        </div>
        <p v-if="!live.backlog.length" class="empty">{{ t("empty") }}</p>
      </div>
      <p v-if="error" class="field-error" role="alert">{{ error }}</p>
      <div class="form-actions">
        <button @click="showBacklog = false">{{ t("cancel") }}</button
        ><button
          class="primary"
          :disabled="busy || !Object.keys(selected).length"
          @click="promote"
        >
          {{ t("promote") }} ({{ Object.keys(selected).length }})
        </button>
      </div></Modal
    >
  </div>
</template>
