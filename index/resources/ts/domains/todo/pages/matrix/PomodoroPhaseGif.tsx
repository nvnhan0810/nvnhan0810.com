import { cn } from "@ts/utils";
import type { PomodoroPhase } from "../../constants/pomodoro";

type Props = {
  phase: PomodoroPhase;
  className?: string;
  size?: "sm" | "md";
};

const WORK_SRC = "/images/todos/work.gif";
const RELAX_SRC = "/images/todos/relax.gif";

export const isFocusPhase = (phase: PomodoroPhase): boolean => phase === "focus";

const PomodoroPhaseGif = ({
  phase,
  className,
  size = "sm",
}: Props): React.ReactElement => {
  const focus = isFocusPhase(phase);
  const src = focus ? WORK_SRC : RELAX_SRC;
  const label = focus ? "Focus" : "Relax";
  const dimension = size === "md" ? "h-12 w-12" : "h-8 w-8";

  return (
    <img
      src={src}
      alt={label}
      title={label}
      className={cn(
        dimension,
        "object-contain select-none pointer-events-none shrink-0",
        className,
      )}
      draggable={false}
    />
  );
};

export default PomodoroPhaseGif;
