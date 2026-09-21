import { cn } from "@ts/utils";
import { router } from "@inertiajs/react";
import { useRoute } from "ziggy-js";
import { useState } from "react";
import type { TodoItem } from "../../types";
import MatrixCard from "./MatrixCard";
import type { QuadrantMeta } from "./quadrants";

type Props = {
  meta: QuadrantMeta;
  todos: TodoItem[];
};

const MatrixQuadrant = ({ meta, todos }: Props) => {
  const route = useRoute();
  const [isOver, setIsOver] = useState(false);

  const onDrop = (event: React.DragEvent<HTMLElement>): void => {
    event.preventDefault();
    setIsOver(false);
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

  return (
    <section
      onDragOver={(event) => {
        event.preventDefault();
        event.dataTransfer.dropEffect = "move";
        setIsOver(true);
      }}
      onDragLeave={() => setIsOver(false)}
      onDrop={onDrop}
      className={cn(
        "flex h-full min-h-[18rem] flex-col rounded-lg border-2 border-dashed p-3 transition-colors duration-200",
        meta.accent,
        meta.panel,
        isOver && "border-solid bg-white/5",
      )}
    >
      <header className="mb-3 flex items-baseline justify-between gap-2 shrink-0">
        <div>
          <h2 className={cn("text-base font-semibold", meta.header)}>{meta.title}</h2>
          <p className="text-[11px] uppercase tracking-wide text-muted-foreground">{meta.subtitle}</p>
        </div>
        <span className="rounded-full bg-black/30 px-2 py-0.5 text-xs tabular-nums text-gray-300">
          {todos.length}
        </span>
      </header>

      <div className="flex flex-1 flex-col gap-2 overflow-y-auto min-h-0">
        {todos.length === 0 && (
          <p className="mt-6 text-center text-xs text-muted-foreground">
            Kéo todo vào đây
          </p>
        )}
        {todos.map((todo) => (
          <MatrixCard key={todo.id} todo={todo} />
        ))}
      </div>
    </section>
  );
};

export default MatrixQuadrant;
