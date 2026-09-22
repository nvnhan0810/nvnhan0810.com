type Tone = {
  frequency: number;
  duration: number;
  type?: OscillatorType;
  gain?: number;
};

let sharedCtx: AudioContext | null = null;

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
    }
    if (sharedCtx.state === "suspended") {
      void sharedCtx.resume();
    }
    return sharedCtx;
  } catch {
    return null;
  }
};

const playTones = (tones: Tone[]): void => {
  const ctx = getAudioContext();
  if (!ctx) {
    return;
  }

  let offset = 0;
  for (const tone of tones) {
    const oscillator = ctx.createOscillator();
    const gain = ctx.createGain();
    oscillator.type = tone.type ?? "sine";
    oscillator.frequency.value = tone.frequency;
    const volume = tone.gain ?? 0.06;
    gain.gain.setValueAtTime(0.0001, ctx.currentTime + offset);
    gain.gain.exponentialRampToValueAtTime(volume, ctx.currentTime + offset + 0.02);
    gain.gain.exponentialRampToValueAtTime(
      0.0001,
      ctx.currentTime + offset + tone.duration,
    );
    oscillator.connect(gain);
    gain.connect(ctx.destination);
    oscillator.start(ctx.currentTime + offset);
    oscillator.stop(ctx.currentTime + offset + tone.duration + 0.02);
    offset += tone.duration * 0.85;
  }
};

/** Soft rising chime when timer starts / resumes */
export const playPomodoroStartSound = (): void => {
  playTones([
    { frequency: 523.25, duration: 0.12, gain: 0.05 },
    { frequency: 659.25, duration: 0.14, gain: 0.055 },
    { frequency: 783.99, duration: 0.18, gain: 0.06 },
  ]);
};

/** Distinct chime when a phase ends */
export const playPomodoroPhaseEndSound = (): void => {
  playTones([
    { frequency: 880, duration: 0.14, type: "triangle", gain: 0.07 },
    { frequency: 1174.66, duration: 0.16, type: "triangle", gain: 0.06 },
    { frequency: 1318.51, duration: 0.22, type: "sine", gain: 0.05 },
  ]);
};

/** Soft tick when pausing */
export const playPomodoroPauseSound = (): void => {
  playTones([{ frequency: 392, duration: 0.1, type: "triangle", gain: 0.045 }]);
};
