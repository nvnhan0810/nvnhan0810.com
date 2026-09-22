import {
  POMODORO_PHASE_LABEL,
  type PomodoroPhase,
} from "../constants/pomodoro";

const NOTIFICATION_TAG = "todo-pomodoro-phase";
const NOTIFICATION_ICON_FOCUS = "/images/todos/work.gif";
const NOTIFICATION_ICON_BREAK = "/images/todos/relax.gif";
const NOTIFICATION_BADGE = "/images/favicon-32x32.png";

const canUseNotifications = (): boolean =>
  typeof window !== "undefined" && "Notification" in window;

const isTabInactive = (): boolean => {
  if (typeof document === "undefined") {
    return true;
  }
  // Hidden tab, or visible but focus is on another window/app
  return document.visibilityState !== "visible" || !document.hasFocus();
};

const iconForPhase = (phase: PomodoroPhase): string =>
  phase === "focus" ? NOTIFICATION_ICON_FOCUS : NOTIFICATION_ICON_BREAK;

/** Soft-ask once on first Start (user gesture) — never spam if denied */
export const ensurePomodoroNotificationPermission = (): void => {
  if (!canUseNotifications()) {
    return;
  }
  if (Notification.permission !== "default") {
    return;
  }
  void Notification.requestPermission();
};

export type PhaseTransitionNotice = {
  fromPhase: PomodoroPhase;
  toPhase: PomodoroPhase;
};

/**
 * Browser notification (Facebook-style) when a phase ends and the Todo tab
 * is not active. No-op if permission missing or tab is focused.
 */
export const notifyPomodoroPhaseEnd = ({
  fromPhase,
  toPhase,
}: PhaseTransitionNotice): void => {
  if (!canUseNotifications() || Notification.permission !== "granted") {
    return;
  }
  if (!isTabInactive()) {
    return;
  }

  const fromLabel = POMODORO_PHASE_LABEL[fromPhase];
  const toLabel = POMODORO_PHASE_LABEL[toPhase];
  const title =
    fromPhase === "focus"
      ? "Hết phiên tập trung"
      : "Hết giờ nghỉ";
  const body = `${fromLabel} → ${toLabel}. Bấm để quay lại Todo.`;

  try {
    const notification = new Notification(title, {
      body,
      icon: iconForPhase(toPhase),
      badge: NOTIFICATION_BADGE,
      tag: NOTIFICATION_TAG,
      renotify: true,
      requireInteraction: true,
      silent: false,
    });

    notification.onclick = (): void => {
      try {
        window.focus();
        // Some browsers need both focus() on window and bring tab forward
        window.parent?.focus();
      } finally {
        notification.close();
      }
    };
  } catch {
    // Permission revoked mid-session or unsupported Notification options
  }
};
