type Tone = {
  frequency: number;
  duration: number;
  type?: OscillatorType;
  /** Peak gain before master bus (0..1). Default ~0.28 */
  gain?: number;
};

/** Seconds remaining that trigger per-second warning ticks */
export const POMODORO_COUNTDOWN_WARN_SECONDS = 5;

let sharedCtx: AudioContext | null = null;
let masterGain: GainNode | null = null;
let compressor: DynamicsCompressorNode | null = null;

const getAudioContext = (): AudioContext | null => {
  try {
    const AudioCtx =
      window.AudioContext ||
      (window as unknown as { webkitAudioContext?: typeof AudioContext })
        .webkitAudioContext;
    if (!AudioCtx) {
      return null;
    }
    if (!sharedCtx || sharedCtx.state === "closed") {
      sharedCtx = new AudioCtx();
      masterGain = null;
      compressor = null;
    }
    if (sharedCtx.state === "suspended") {
      void sharedCtx.resume();
    }
    return sharedCtx;
  } catch {
    return null;
  }
};

/**
 * Shared bus: oscillator → tone gain → compressor → master → destination.
 * Compressor + high master gain raise perceived loudness so cues cut through
 * other-tab media better than a soft sine into destination alone.
 * Cross-tab ducking of YouTube is not available to a normal web page.
 */
const getMasterBus = (
  ctx: AudioContext,
): { input: DynamicsCompressorNode; master: GainNode } | null => {
  if (!compressor || !masterGain || compressor.context !== ctx) {
    compressor = ctx.createDynamicsCompressor();
    compressor.threshold.setValueAtTime(-18, ctx.currentTime);
    compressor.knee.setValueAtTime(10, ctx.currentTime);
    compressor.ratio.setValueAtTime(6, ctx.currentTime);
    compressor.attack.setValueAtTime(0.003, ctx.currentTime);
    compressor.release.setValueAtTime(0.18, ctx.currentTime);

    masterGain = ctx.createGain();
    // Makeup after compression — keep under ~1.2 to limit harsh clipping
    masterGain.gain.setValueAtTime(1.15, ctx.currentTime);

    compressor.connect(masterGain);
    masterGain.connect(ctx.destination);
  }
  return { input: compressor, master: masterGain };
};

/** Keep AudioContext alive when returning to the tab / after OS suspend */
export const unlockPomodoroAudio = (): void => {
  const ctx = getAudioContext();
  if (ctx?.state === "suspended") {
    void ctx.resume();
  }
};

const playTones = (tones: Tone[]): void => {
  const ctx = getAudioContext();
  if (!ctx) {
    return;
  }
  const bus = getMasterBus(ctx);
  if (!bus) {
    return;
  }

  let offset = 0;
  for (const tone of tones) {
    const oscillator = ctx.createOscillator();
    const gain = ctx.createGain();
    oscillator.type = tone.type ?? "square";
    oscillator.frequency.value = tone.frequency;
    const volume = Math.min(0.55, tone.gain ?? 0.28);
    const start = ctx.currentTime + offset;
    gain.gain.setValueAtTime(0.0001, start);
    gain.gain.exponentialRampToValueAtTime(volume, start + 0.012);
    gain.gain.exponentialRampToValueAtTime(0.0001, start + tone.duration);
    oscillator.connect(gain);
    gain.connect(bus.input);
    oscillator.start(start);
    oscillator.stop(start + tone.duration + 0.03);
    offset += tone.duration * 0.82;
  }
};

/** Rising chime when timer starts / resumes — piercing enough vs background media */
export const playPomodoroStartSound = (): void => {
  unlockPomodoroAudio();
  playTones([
    { frequency: 659.25, duration: 0.11, type: "square", gain: 0.3 },
    { frequency: 783.99, duration: 0.12, type: "square", gain: 0.32 },
    { frequency: 1046.5, duration: 0.2, type: "triangle", gain: 0.34 },
  ]);
};

/** Distinct fanfare when a phase ends */
export const playPomodoroPhaseEndSound = (): void => {
  unlockPomodoroAudio();
  playTones([
    { frequency: 880, duration: 0.14, type: "square", gain: 0.36 },
    { frequency: 1174.66, duration: 0.15, type: "square", gain: 0.34 },
    { frequency: 1396.91, duration: 0.18, type: "triangle", gain: 0.32 },
    { frequency: 1760, duration: 0.28, type: "sawtooth", gain: 0.28 },
  ]);
};

/** Soft tick when pausing */
export const playPomodoroPauseSound = (): void => {
  unlockPomodoroAudio();
  playTones([{ frequency: 330, duration: 0.1, type: "triangle", gain: 0.22 }]);
};

/**
 * Per-second warning in the last N seconds of a phase.
 * Pitch rises as `secondsLeft` falls (5 → 1) so the cue feels urgent.
 */
export const playPomodoroCountdownTick = (secondsLeft: number): void => {
  unlockPomodoroAudio();
  const clamped = Math.max(1, Math.min(POMODORO_COUNTDOWN_WARN_SECONDS, secondsLeft));
  const frequency = 920 + (POMODORO_COUNTDOWN_WARN_SECONDS - clamped) * 140;
  const gain = 0.3 + (POMODORO_COUNTDOWN_WARN_SECONDS - clamped) * 0.04;
  playTones([
    {
      frequency,
      duration: clamped === 1 ? 0.16 : 0.09,
      type: "square",
      gain,
    },
  ]);
};
