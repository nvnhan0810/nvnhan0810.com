<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from "vue";
import { Play, Pause, SkipForward, Check, X } from "lucide-vue-next";
import type { TodoItem } from "../../domain/todo";
import {
  clock,
  remainingMs,
  type PomodoroPayload,
  isPomodoro,
} from "../../domain/pomodoro";
import { action, request } from "../../infrastructure/todos-api";
import {
  enableNotifications,
  disableNotifications,
  currentSubscription,
} from "../../infrastructure/web-push";
import { t } from "../../application/locale";
import Modal from "./Modal.vue";
const props = defineProps<{ payload: PomodoroPayload; tasks: TodoItem[] }>();
const emit = defineEmits<{ completeActive: [todo: TodoItem] }>();
const state = ref(props.payload),
  offset = ref(props.payload.serverNow - Date.now()),
  now = ref(Date.now()),
  busy = ref(false),
  error = ref(""),
  showSettings = ref(false),
  pushEnabled = ref(false);
const settings = ref({ ...props.payload.settings }),
  sound = ref(localStorage.getItem("todos.sound") !== "off");
let audio: AudioContext | undefined,
  wake: WakeLockSentinel | undefined,
  clockTimer: ReturnType<typeof setInterval> | undefined,
  heartbeat: ReturnType<typeof setInterval> | undefined;
function install(value: PomodoroPayload) {
  if (value.runtime.updatedAt < state.value.runtime.updatedAt) return;
  const phaseChanged = state.value.runtime.phase !== value.runtime.phase;
  if (phaseChanged && state.value.runtime.isRunning) {
    beep();
  }
  state.value = value;
  offset.value = value.serverNow - Date.now();
}
watch(
  () => props.payload,
  (value) => {
    if (isPomodoro(value)) install(value);
  },
);
const countdown = computed(() =>
  clock(remainingMs(state.value, now.value + offset.value)),
);
const phase = computed(() => state.value.runtime.phase);
const activeTodo = computed(
  () =>
    props.tasks.find((todo) => todo.id === state.value.runtime.activeTodoId) ??
    null,
);
function beep() {
  if (!sound.value) return;
  try {
    if (!audio) return;
    const oscillator = audio.createOscillator(),
      gain = audio.createGain();
    oscillator.connect(gain);
    gain.connect(audio.destination);
    gain.gain.setValueAtTime(0.12, audio.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.001, audio.currentTime + 0.8);
    oscillator.frequency.value = 660;
    oscillator.start();
    oscillator.stop(audio.currentTime + 0.8);
  } catch {
    /* Optional sound. */
  }
}
async function keepAwake() {
  if (wake && !wake.released) return;
  if (
    state.value.runtime.isRunning &&
    document.visibilityState === "visible" &&
    "wakeLock" in navigator
  ) {
    try {
      wake = await navigator.wakeLock.request("screen");
    } catch {
      /* Screen locks are optional. */
    }
  }
}
watch(
  () => state.value.runtime.isRunning,
  async (running) => {
    if (running) await keepAwake();
    else {
      await wake?.release();
      wake = undefined;
    }
  },
);
async function operate(operation: string, data: unknown = {}) {
  if (busy.value) return;
  busy.value = true;
  error.value = "";
  try {
    if (
      operation === "start" &&
      !audio &&
      typeof AudioContext !== "undefined"
    ) {
      try {
        audio = new AudioContext();
        await audio.resume();
      } catch {
        /* Audio is optional; starting the timer must still work. */
      }
    }
    const payload = await action<PomodoroPayload>(
      `/matrix/pomodoro/${operation}`,
      operation === "settings"
        ? "PUT"
        : operation === "active-todo"
          ? "PATCH"
          : "POST",
      data,
    );
    install(payload);
    await keepAwake();
  } catch (e) {
    error.value =
      e instanceof Error ? e.message : "Không thể cập nhật Pomodoro.";
  } finally {
    busy.value = false;
  }
}
async function notify() {
  error.value = "";
  try {
    if (pushEnabled.value) {
      await disableNotifications();
      pushEnabled.value = false;
    } else {
      await enableNotifications();
      pushEnabled.value = true;
    }
  } catch (e) {
    error.value = e instanceof Error ? e.message : "Không thể bật thông báo.";
  }
}
async function focus() {
  if (state.value.runtime.sessionUuid)
    try {
      const payload = await action<PomodoroPayload>(
        "/matrix/pomodoro/focus",
        "POST",
        {
          sessionUuid: state.value.runtime.sessionUuid,
          focused:
            document.visibilityState === "visible" && document.hasFocus(),
        },
      );
      install(payload);
    } catch {
      /* Reconnect on the next sync. */
    }
}
async function visible() {
  if (document.visibilityState === "visible") {
    try {
      install(await request<PomodoroPayload>("/pomodoro"));
    } catch {
      /* The SSE stream retries. */
    }
    await keepAwake();
  }
  await focus();
}
function openSettings() {
  settings.value = { ...state.value.settings };
  showSettings.value = true;
}
function locateTodo() {
  const todo = activeTodo.value;
  if (!todo) return;
  document
    .querySelector<HTMLElement>(`[data-todo-id="${todo.id}"]`)
    ?.scrollIntoView({ behavior: "smooth", block: "center" });
}
function completeActiveTodo() {
  if (activeTodo.value) emit("completeActive", activeTodo.value);
}
async function clearActiveTodo() {
  await operate("active-todo", { activeTodoId: null });
}
async function saveSettings() {
  await operate("settings", settings.value);
  if (!error.value) showSettings.value = false;
}
onMounted(() => {
  clockTimer = setInterval(() => {
    now.value = Date.now();
  }, 250);
  heartbeat = setInterval(() => void focus(), 5000);
  document.addEventListener("visibilitychange", visible);
  window.addEventListener("focus", visible);
  void currentSubscription().then((s) => {
    pushEnabled.value = !!s;
  });
  void keepAwake();
});
onUnmounted(() => {
  clearInterval(clockTimer);
  clearInterval(heartbeat);
  document.removeEventListener("visibilitychange", visible);
  window.removeEventListener("focus", visible);
  void wake?.release();
  void audio?.close();
  document.title = "Todo";
});
defineExpose({ openSettings, notify, pushEnabled });
watch(
  countdown,
  (value) => {
    document.title = `${state.value.runtime.isRunning ? value + " · " : ""}${t(phase.value)} · Todo`;
  },
  { immediate: true },
);
</script>
<template>
  <section class="pomodoro panel" :class="phase">
    <div class="phase-summary">
      <p>{{ t(phase) }}</p>
      <div class="session-dots">
        <i
          v-for="index in state.settings.sessionsBeforeLongBreak"
          :key="index"
          :class="{ filled: index <= state.runtime.focusCount }"
        />
        <span
          >{{ state.runtime.focusCount }}/{{
            state.settings.sessionsBeforeLongBreak
          }}</span
        >
      </div>
    </div>
    <div class="active-task-slot">
      <div v-if="activeTodo" class="active-task">
        <button
          class="icon-button clear-focus"
          :aria-label="t('clearFocus')"
          :title="t('clearFocus')"
          @click="clearActiveTodo"
        >
          <X :size="14" />
        </button>
        <button
          class="icon-button complete-focus"
          :aria-label="t('done')"
          :title="t('done')"
          @click="completeActiveTodo"
        >
          <Check :size="14" />
        </button>
        <button
          class="active-task-title"
          :title="t('locateTask')"
          @click="locateTodo"
        >
          <span>{{ activeTodo.title }}</span>
          <small v-if="activeTodo.project">{{ activeTodo.project.name }}</small>
        </button>
      </div>
      <p v-else class="no-active-task">{{ t("noActiveTask") }}</p>
    </div>
    <div class="timer-controls">
      <img
        :src="
          phase === 'focus'
            ? '/images/todos/work.gif'
            : '/images/todos/relax.gif'
        "
        alt=""
        class="phase-art"
      />
      <strong class="timer-countdown">{{ countdown }}</strong>
      <button
        class="icon-button bordered timer-icon-action"
        :disabled="busy"
        :aria-label="t(state.runtime.isRunning ? 'pause' : 'start')"
        :title="t(state.runtime.isRunning ? 'pause' : 'start')"
        @click="operate(state.runtime.isRunning ? 'pause' : 'start')"
      >
        <Pause v-if="state.runtime.isRunning" :size="16" /><Play
          v-else
          :size="16"
        />
      </button>
      <button
        class="icon-button bordered timer-icon-action"
        :disabled="busy"
        :title="t('skip')"
        :aria-label="t('skip')"
        @click="operate('skip')"
      >
        <SkipForward :size="16" />
      </button>
    </div>
    <p v-if="error" class="field-error timer-error" role="alert">{{ error }}</p>
  </section>
  <Modal
    v-if="showSettings"
    :title="`Pomodoro · ${t('settings')}`"
    @close="showSettings = false"
    ><form class="editor" @submit.prevent="saveSettings">
      <label
        >{{ t("focus") }} (phút)<input
          v-model.number="settings.focusMinutes"
          type="number"
          min="1"
          max="180"
          required /></label
      ><label
        >{{ t("short_break") }} (phút)<input
          v-model.number="settings.shortBreakMinutes"
          type="number"
          min="1"
          max="60"
          required /></label
      ><label
        >{{ t("long_break") }} (phút)<input
          v-model.number="settings.longBreakMinutes"
          type="number"
          min="1"
          max="60"
          required /></label
      ><label
        >Số phiên trước khi nghỉ dài<input
          v-model.number="settings.sessionsBeforeLongBreak"
          type="number"
          min="1"
          max="12"
          required
      /></label>
      <p class="muted">Lưu cài đặt sẽ đặt lại phiên hiện tại.</p>
      <p v-if="error" class="field-error" role="alert">{{ error }}</p>
      <div class="form-actions">
        <button type="button" @click="showSettings = false">
          {{ t("cancel") }}</button
        ><button class="primary" :disabled="busy">{{ t("save") }}</button>
      </div>
    </form></Modal
  >
</template>
