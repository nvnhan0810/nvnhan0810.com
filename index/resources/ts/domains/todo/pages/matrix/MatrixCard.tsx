import { cn } from "@ts/utils";
import { useRef } from "react";
import type { TodoItem } from "../../types";
import { priorityLabel, statusStyles } from "./quadrants";

type Props = {
  todo: TodoItem;
  onEdit: (todo: TodoItem) => void;
};

const MatrixCard = ({ todo, onEdit }: Props): React.ReactElement => {
  const status = todo.status === "in_progress" ? "in_progress" : "todo";
  const style = statusStyles[status];
  const didDragRef = useRef(false);

  const onDragStart = (event: React.DragEvent<HTMLElement>): void => {
    didDragRef.current = true;
    event.dataTransfer.setData("text/todo-id", String(todo.id));
    event.dataTransfer.effectAllowed = "move";
  };

  return (
    <article
      data-matrix-card
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
      title={status === "in_progress" ? "In progress — click để sửa" : "Todo — click để sửa"}
      className={cn(
        "rounded-md px-3 py-2.5 cursor-grab active:cursor-grabbing transition-colors duration-200",
        "border border-white/5 hover:border-white/15",
        style.card,
      )}
    >
      <div className="flex items-start justify-between gap-2 mb-1">
        <span className="block flex-1 text-sm font-medium text-gray-100 leading-snug">
          {todo.title}
        </span>
        <span className="shrink-0 text-[10px] uppercase tracking-wide text-muted-foreground">
          {priorityLabel[todo.priority]}
        </span>
      </div>

      <div className="flex flex-wrap items-center gap-2 text-[11px] text-muted-foreground">
        {todo.project?.name && <span className="truncate max-w-[10rem]">{todo.project.name}</span>}
        {todo.due_at && (
          <span className="tabular-nums">Due {todo.due_at.slice(0, 10)}</span>
        )}
      </div>
    </article>
  );
};

export default MatrixCard;
