import { cn } from "@ts/utils";
import { useRef, useState } from "react";
import { router } from "@inertiajs/react";
import { useRoute } from "ziggy-js";
import type { PomodoroPhase } from "../../constants/pomodoro";
import type { TodoItem } from "../../types";
import MatrixCard from "./MatrixCard";
import type { QuadrantMeta } from "./quadrants";

type Props = {
  meta: QuadrantMeta;
  todos: TodoItem[];
  onCreateInQuadrant: (meta: QuadrantMeta) => void;
  onEditTodo: (todo: TodoItem) => void;
  onSelectForPomodoro: (todo: TodoItem) => void;
  activePomodoroTodoId: number | null;
  highlightedTodoId: number | null;
  pomodoroPhase: PomodoroPhase;
};

const MatrixQuadrant = ({
  meta,
  todos,
  onCreateInQuadrant,
  onEditTodo,
  onSelectForPomodoro,
  activePomodoroTodoId,
  highlightedTodoId,
  pomodoroPhase,
}: Props): React.ReactElement => {
  const route = useRoute();
  const [isOver, setIsOver] = useState(false);
  const suppressClickRef = useRef(false);

  const onDrop = (event: React.DragEvent<HTMLElement>): void => {
    event.preventDefault();
    setIsOver(false);
    suppressClickRef.current = true;
    window.setTimeout(() => {
      suppressClickRef.current = false;
    }, 200);

    const id = event.dataTransfer.getData("text/todo-id");
    if (!id) {
      return;
    }

    router.patch(
      route("matrix.update", id),
      {
        is_urgent: meta.is_urgent,
        is_important: meta.is_important,
      },
      { preserveScroll: true },
    );
  };

  const onQuadrantClick = (): void => {
    if (suppressClickRef.current) {
      return;
    }
    onCreateInQuadrant(meta);
  };

  return (
    <section
      onClick={onQuadrantClick}
      onDragOver={(event) => {
        event.preventDefault();
        event.dataTransfer.dropEffect = "move";
        setIsOver(true);
      }}
      onDragLeave={() => setIsOver(false)}
      onDrop={onDrop}
      title="Click để tạo todo trong vùng này"
      className={cn(
        "flex h-full min-h-[18rem] flex-col rounded-lg border-2 border-dashed p-3 transition-colors duration-200 cursor-pointer",
        meta.accent,
        meta.panel,
        isOver && "border-solid bg-white/5",
      )}
    >
      <header className="mb-3 flex items-baseline justify-between gap-2 shrink-0 pointer-events-none">
        <div>
          <h2 className={cn("text-base font-semibold", meta.header)}>{meta.title}</h2>
          <p className="text-[11px] uppercase tracking-wide text-muted-foreground">
            {meta.subtitle}
          </p>
        </div>
        <span className="rounded-full bg-black/30 px-2 py-0.5 text-xs tabular-nums text-gray-300">
          {todos.length}
        </span>
      </header>

      <div className="flex flex-1 flex-col gap-2 overflow-y-auto min-h-0">
        {todos.length === 0 && (
          <p className="mt-6 text-center text-xs text-muted-foreground pointer-events-none">
            Click để tạo · hoặc kéo todo vào đây
          </p>
        )}
        {todos.map((todo) => (
          <MatrixCard
            key={todo.id}
            todo={todo}
            onEdit={onEditTodo}
            onSelectForPomodoro={onSelectForPomodoro}
            isPomodoroActive={activePomodoroTodoId === todo.id}
            isHighlighted={highlightedTodoId === todo.id}
            pomodoroPhase={pomodoroPhase}
          />
        ))}
      </div>
    </section>
  );
};

export default MatrixQuadrant;
