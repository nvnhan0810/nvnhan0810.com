import {
  formatTimer,
  POMODORO_PHASE_LABEL,
  type PomodoroPhase,
} from "../constants/pomodoro";
import { POMODORO_COUNTDOWN_WARN_SECONDS } from "./pomodoroAudio";

const WORK_GIF = "/images/todos/work.gif";
const RELAX_GIF = "/images/todos/relax.gif";
const FAVICON_SIZE = 32;
const ANIM_INTERVAL_MS = 120;

type PresenceState = {
  phase: PomodoroPhase;
  remainingMs: number;
  isRunning: boolean;
};

let installed = false;
let originalTitle = "";
let originalIconHrefs: Array<{ el: HTMLLinkElement; href: string }> = [];
let faviconLink: HTMLLinkElement | null = null;
let canvas: HTMLCanvasElement | null = null;
let ctx: CanvasRenderingContext2D | null = null;
let gifImg: HTMLImageElement | null = null;
let animTimer: number | null = null;
let currentPhase: PomodoroPhase | null = null;
let lastState: PresenceState | null = null;
let pulse = 0;

const isFocusPhase = (phase: PomodoroPhase): boolean => phase === "focus";

const phaseGifSrc = (phase: PomodoroPhase): string =>
  isFocusPhase(phase) ? WORK_GIF : RELAX_GIF;

const accentColor = (phase: PomodoroPhase, urgent: boolean): string => {
  if (urgent) {
    return "#fbbf24";
  }
  if (phase === "focus") {
    return "#fb7185";
  }
  if (phase === "short_break") {
    return "#2dd4bf";
  }
  return "#818cf8";
};

const ensureFaviconLink = (): HTMLLinkElement => {
  if (faviconLink && faviconLink.isConnected) {
    return faviconLink;
  }
  const existing = document.querySelector<HTMLLinkElement>('link[rel="icon"]');
  if (existing) {
    faviconLink = existing;
    return existing;
  }
  const link = document.createElement("link");
  link.rel = "icon";
  link.type = "image/png";
  document.head.appendChild(link);
  faviconLink = link;
  return link;
};

const ensureCanvas = (): CanvasRenderingContext2D | null => {
  if (!canvas) {
    canvas = document.createElement("canvas");
    canvas.width = FAVICON_SIZE;
    canvas.height = FAVICON_SIZE;
  }
  if (!ctx) {
    ctx = canvas.getContext("2d");
  }
  return ctx;
};

const ensureGifElement = (phase: PomodoroPhase): HTMLImageElement => {
  const src = phaseGifSrc(phase);
  if (gifImg && gifImg.dataset.phase === phase && gifImg.src.endsWith(src)) {
    return gifImg;
  }
  if (gifImg?.parentElement) {
    gifImg.parentElement.removeChild(gifImg);
  }
  const img = document.createElement("img");
  img.src = src;
  img.alt = "";
  img.decoding = "async";
  img.dataset.phase = phase;
  // Keep GIF decoding/animating off-DOM visibility; Chrome advances frames when drawn.
  img.setAttribute("aria-hidden", "true");
  img.style.cssText =
    "position:fixed;width:1px;height:1px;opacity:0;pointer-events:none;left:-9999px;top:0;";
  document.body.appendChild(img);
  gifImg = img;
  return img;
};

const buildTitle = (state: PresenceState): string => {
  const label = POMODORO_PHASE_LABEL[state.phase];
  const timer = formatTimer(state.remainingMs);
  const secondsLeft = Math.ceil(state.remainingMs / 1000);
  const urgent =
    state.isRunning &&
    secondsLeft > 0 &&
    secondsLeft <= POMODORO_COUNTDOWN_WARN_SECONDS;

  if (!state.isRunning) {
    return `⏸ ${timer} · ${label} | Todo`;
  }
  if (urgent) {
    return `⚠ ${timer} · ${label} | Todo`;
  }
  return `${timer} · ${label} | Todo`;
};

const paintFavicon = (state: PresenceState): void => {
  const draw = ensureCanvas();
  if (!draw || !canvas) {
    return;
  }

  const secondsLeft = Math.ceil(state.remainingMs / 1000);
  const urgent =
    state.isRunning &&
    secondsLeft > 0 &&
    secondsLeft <= POMODORO_COUNTDOWN_WARN_SECONDS;
  const img = ensureGifElement(state.phase);
  const accent = accentColor(state.phase, urgent);

  draw.clearRect(0, 0, FAVICON_SIZE, FAVICON_SIZE);

  // Background
  draw.fillStyle = "#0f172a";
  draw.beginPath();
  if (typeof draw.roundRect === "function") {
    draw.roundRect(0, 0, FAVICON_SIZE, FAVICON_SIZE, 6);
  } else {
    draw.rect(0, 0, FAVICON_SIZE, FAVICON_SIZE);
  }
  draw.fill();

  // GIF current frame (Chrome advances while img is in DOM)
  if (img.complete && img.naturalWidth > 0) {
    const scale = urgent ? 0.78 + Math.sin(pulse) * 0.06 : 0.82;
    const size = FAVICON_SIZE * scale;
    const offset = (FAVICON_SIZE - size) / 2;
    draw.drawImage(img, offset, offset, size, size);
  }

  // Animated ring — motion cue when tab is in background (Chrome ~1fps when hidden)
  const ringPulse = state.isRunning ? 0.55 + Math.sin(pulse) * 0.45 : 0.35;
  draw.strokeStyle = accent;
  draw.lineWidth = urgent ? 3.5 : 2.5;
  draw.globalAlpha = ringPulse;
  draw.beginPath();
  draw.arc(
    FAVICON_SIZE / 2,
    FAVICON_SIZE / 2,
    FAVICON_SIZE / 2 - 2,
    0,
    Math.PI * 2,
  );
  draw.stroke();
  draw.globalAlpha = 1;

  if (urgent) {
    draw.fillStyle = "rgba(15, 23, 42, 0.72)";
    draw.beginPath();
    draw.arc(FAVICON_SIZE / 2, FAVICON_SIZE / 2, 10, 0, Math.PI * 2);
    draw.fill();
    draw.fillStyle = "#fbbf24";
    draw.font = "bold 14px ui-sans-serif, system-ui, sans-serif";
    draw.textAlign = "center";
    draw.textBaseline = "middle";
    draw.fillText(String(secondsLeft), FAVICON_SIZE / 2, FAVICON_SIZE / 2 + 0.5);
  }

  const link = ensureFaviconLink();
  link.type = "image/png";
  link.href = canvas.toDataURL("image/png");
};

const tickAnimation = (): void => {
  if (!lastState) {
    return;
  }
  pulse += lastState.isRunning ? 0.55 : 0.2;
  paintFavicon(lastState);
};

const startAnimationLoop = (): void => {
  if (animTimer !== null) {
    return;
  }
  animTimer = window.setInterval(tickAnimation, ANIM_INTERVAL_MS);
};

const stopAnimationLoop = (): void => {
  if (animTimer === null) {
    return;
  }
  window.clearInterval(animTimer);
  animTimer = null;
};

/**
 * Sync Chrome tab title + animated favicon with pomodoro phase.
 * Uses work/relax GIFs drawn frame-by-frame onto a canvas (Chrome does not
 * animate GIF favicons natively).
 */
export const syncPomodoroTabPresence = (state: PresenceState): void => {
  if (typeof document === "undefined") {
    return;
  }

  if (!installed) {
    originalTitle = document.title;
    originalIconHrefs = Array.from(
      document.querySelectorAll<HTMLLinkElement>('link[rel="icon"]'),
    ).map((el) => ({ el, href: el.getAttribute("href") ?? "" }));
    installed = true;
    startAnimationLoop();
  }

  lastState = state;
  if (currentPhase !== state.phase) {
    currentPhase = state.phase;
    ensureGifElement(state.phase);
  }

  document.title = buildTitle(state);
  paintFavicon(state);
};

/** Restore document title and site favicons when leaving the matrix page */
export const clearPomodoroTabPresence = (): void => {
  if (!installed) {
    return;
  }
  stopAnimationLoop();
  document.title = originalTitle || document.title;
  for (const { el, href } of originalIconHrefs) {
    if (el.isConnected && href) {
      el.href = href;
    }
  }
  if (gifImg?.parentElement) {
    gifImg.parentElement.removeChild(gifImg);
  }
  gifImg = null;
  faviconLink = null;
  canvas = null;
  ctx = null;
  currentPhase = null;
  lastState = null;
  installed = false;
  originalTitle = "";
  originalIconHrefs = [];
};
