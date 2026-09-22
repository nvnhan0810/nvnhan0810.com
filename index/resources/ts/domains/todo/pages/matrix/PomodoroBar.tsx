import { Button } from "@/ts/components/ui/button";
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "@/ts/components/ui/tooltip";
import { cn } from "@ts/utils";
import { CheckCircle2, Pause, Play, SkipForward, X } from "lucide-react";
import {
  formatTimer,
  POMODORO_PHASE_LABEL,
} from "../../constants/pomodoro";
import type { UsePomodoroResult } from "../../presentation/hooks/usePomodoro";
import type { TodoItem } from "../../types";
import PomodoroPhaseGif from "./PomodoroPhaseGif";

type Props = {
  pomodoro: UsePomodoroResult;
  matrixTodos: TodoItem[];
  onLocateTodo: (todoId: number) => void;
  onCompleteActiveTodo: () => void;
  className?: string;
};

const PomodoroBar = ({
  pomodoro,
  matrixTodos,
  onLocateTodo,
  onCompleteActiveTodo,
  className,
}: Props): React.ReactElement => {
  const {
    settings,
    phase,
    remainingMs,
    focusCount,
    activeTodoId,
    isRunning,
    clearActiveTodo,
    toggle,
    skipPhase,
  } = pomodoro;

  const activeTodo =
    activeTodoId !== null
      ? matrixTodos.find((todo) => todo.id === activeTodoId) ?? null
      : null;

  const sessionDots = Array.from(
    { length: settings.sessionsBeforeLongBreak },
    (_, index) => index < focusCount,
  );

  const phaseAccent =
    phase === "focus"
      ? "border-rose-500/40 bg-rose-950/30"
      : phase === "short_break"
        ? "border-teal-500/40 bg-teal-950/25"
        : "border-indigo-500/40 bg-indigo-950/30";

  return (
    <div
      className={cn(
        "rounded-lg border px-3 py-2.5 flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-4",
        phaseAccent,
        className,
      )}
    >
      <div className="flex items-center gap-3 shrink-0">
        <PomodoroPhaseGif phase={phase} size="md" />
        <div className="tabular-nums text-2xl font-semibold tracking-tight text-gray-100 min-w-[4.5rem]">
          {formatTimer(remainingMs)}
        </div>
        <div className="min-w-0">
          <p className="text-xs font-medium text-gray-200">
            {POMODORO_PHASE_LABEL[phase]}
          </p>
          <div className="mt-1 flex items-center gap-1">
            {sessionDots.map((filled, index) => (
              <span
                key={index}
                className={cn(
                  "h-1.5 w-1.5 rounded-full",
                  filled ? "bg-rose-400" : "bg-white/20",
                )}
              />
            ))}
            <span className="ml-1 text-[10px] text-muted-foreground tabular-nums">
              {focusCount}/{settings.sessionsBeforeLongBreak}
            </span>
          </div>
        </div>
      </div>

      <div className="min-w-0 flex-1">
        {activeTodo ? (
          <div className="flex items-start gap-1.5 max-w-full">
            <button
              type="button"
              onClick={() => onLocateTodo(activeTodo.id)}
              className="text-left min-w-0 flex-1 group cursor-pointer"
              title="Click để tìm task trên Matrix"
            >
              <span className="block truncate text-sm font-medium text-gray-100 group-hover:text-sky-300 transition-colors underline-offset-2 group-hover:underline">
                {activeTodo.title}
              </span>
              {activeTodo.project?.name && (
                <span className="block truncate text-[11px] text-muted-foreground">
                  {activeTodo.project.name}
                </span>
              )}
            </button>
            <Tooltip>
              <TooltipTrigger asChild>
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  className="h-7 w-7 shrink-0 p-0 cursor-pointer text-muted-foreground hover:text-rose-300"
                  onClick={clearActiveTodo}
                  aria-label="Bỏ task khỏi Pomodoro"
                >
                  <X className="w-3.5 h-3.5" />
                </Button>
              </TooltipTrigger>
              <TooltipContent>Bỏ task & reset Pomodoro</TooltipContent>
            </Tooltip>
          </div>
        ) : (
          <p className="text-sm text-muted-foreground">
            Chưa chọn todo — bấm Play trên card
          </p>
        )}
      </div>

      <div className="flex items-center gap-1.5 shrink-0 sm:ml-auto">
        <Tooltip>
          <TooltipTrigger asChild>
            <Button
              type="button"
              variant="outline"
              size="sm"
              className="cursor-pointer"
              onClick={toggle}
              aria-label={isRunning ? "Tạm dừng" : "Bắt đầu"}
            >
              {isRunning ? (
                <Pause className="w-4 h-4" />
              ) : (
                <Play className="w-4 h-4" />
              )}
            </Button>
          </TooltipTrigger>
          <TooltipContent>
            {isRunning ? "Tạm dừng (Space)" : "Bắt đầu (Space)"}
          </TooltipContent>
        </Tooltip>

        <Tooltip>
          <TooltipTrigger asChild>
            <Button
              type="button"
              variant="outline"
              size="sm"
              className="cursor-pointer"
              onClick={skipPhase}
              aria-label="Bỏ qua phase"
            >
              <SkipForward className="w-4 h-4" />
            </Button>
          </TooltipTrigger>
          <TooltipContent>Bỏ qua phase hiện tại</TooltipContent>
        </Tooltip>

        <Tooltip>
          <TooltipTrigger asChild>
            <Button
              type="button"
              variant="outline"
              size="sm"
              className="cursor-pointer"
              disabled={activeTodoId === null}
              onClick={onCompleteActiveTodo}
              aria-label="Hoàn thành todo"
            >
              <CheckCircle2 className="w-4 h-4" />
            </Button>
          </TooltipTrigger>
          <TooltipContent>Đánh Done & lấy todo tiếp theo (giữ timer)</TooltipContent>
        </Tooltip>
      </div>
    </div>
  );
};

export default PomodoroBar;
