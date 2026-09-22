import { Button } from "@/ts/components/ui/button";
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from "@/ts/components/ui/tooltip";
import { cn } from "@ts/utils";
import { Play } from "lucide-react";
import { useEffect, useRef } from "react";
import type { PomodoroPhase } from "../../constants/pomodoro";
import type { TodoItem } from "../../types";
import PomodoroPhaseGif from "./PomodoroPhaseGif";
import { priorityLabel, statusStyles } from "./quadrants";

type Props = {
  todo: TodoItem;
  onEdit: (todo: TodoItem) => void;
  onSelectForPomodoro: (todo: TodoItem) => void;
  isPomodoroActive: boolean;
  isHighlighted: boolean;
  pomodoroPhase: PomodoroPhase;
};

const MatrixCard = ({
  todo,
  onEdit,
  onSelectForPomodoro,
  isPomodoroActive,
  isHighlighted,
  pomodoroPhase,
}: Props): React.ReactElement => {
  const status = todo.status === "in_progress" ? "in_progress" : "todo";
  const style = statusStyles[status];
  const didDragRef = useRef(false);
  const cardRef = useRef<HTMLElement | null>(null);

  useEffect(() => {
    if (!isHighlighted || !cardRef.current) {
      return;
    }
    cardRef.current.scrollIntoView({ behavior: "smooth", block: "center" });
  }, [isHighlighted]);

  const onDragStart = (event: React.DragEvent<HTMLElement>): void => {
    didDragRef.current = true;
    event.dataTransfer.setData("text/todo-id", String(todo.id));
    event.dataTransfer.effectAllowed = "move";
  };

  return (
    <article
      ref={cardRef}
      data-matrix-card
      data-todo-id={todo.id}
      draggable
      onDragStart={onDragStart}
      onDragEnd={() => {
        window.setTimeout(() => {
          didDragRef.current = false;
        }, 0);
      }}
      onClick={(event) => {
        event.stopPropagation();
        if (didDragRef.current) {
          didDragRef.current = false;
          return;
        }
        onEdit(todo);
      }}
      className={cn(
        "rounded-md px-3 py-2.5 cursor-grab active:cursor-grabbing transition-all duration-300",
        "border border-white/5 hover:border-white/15",
        style.card,
        isPomodoroActive && "ring-1 ring-rose-400/60",
        isHighlighted &&
          "ring-2 ring-sky-400 shadow-[0_0_0_4px_rgba(56,189,248,0.25)] scale-[1.02] z-10",
      )}
    >
      <div className="flex items-start justify-between gap-2 mb-1">
        <span className="block flex-1 text-sm font-medium text-gray-100 leading-snug">
          {todo.title}
        </span>
        <div className="flex items-center gap-1 shrink-0">
          {isPomodoroActive && (
            <PomodoroPhaseGif phase={pomodoroPhase} size="sm" />
          )}
          <span className="text-[10px] uppercase tracking-wide text-muted-foreground">
            {priorityLabel[todo.priority]}
          </span>
          <Tooltip>
            <TooltipTrigger asChild>
              <Button
                type="button"
                variant="ghost"
                size="sm"
                className="h-6 w-6 p-0 cursor-pointer text-rose-300 hover:text-rose-200"
                aria-label="Chọn todo cho Pomodoro"
                onClick={(event) => {
                  event.stopPropagation();
                  onSelectForPomodoro(todo);
                }}
              >
                <Play className="w-3.5 h-3.5" />
              </Button>
            </TooltipTrigger>
            <TooltipContent side="left">Làm todo này (Pomodoro)</TooltipContent>
          </Tooltip>
        </div>
      </div>

      <div className="flex flex-wrap items-center gap-2 text-[11px] text-muted-foreground">
        {todo.project?.name && (
          <span className="truncate max-w-[10rem]">{todo.project.name}</span>
        )}
        {todo.due_at && (
          <span className="tabular-nums">Due {todo.due_at.slice(0, 10)}</span>
        )}
      </div>
    </article>
  );
};

export default MatrixCard;
