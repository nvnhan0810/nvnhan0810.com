import { Button } from "@/ts/components/ui/button";
import { Checkbox } from "@/ts/components/ui/checkbox";
import Combobox from "@/ts/components/ui/combobox";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/ts/components/ui/dialog";
import {
  HoverCard,
  HoverCardContent,
  HoverCardTrigger,
} from "@/ts/components/ui/hover-card";
import { cn } from "@ts/utils";
import { router } from "@inertiajs/react";
import { useEffect, useState } from "react";
import { useRoute } from "ziggy-js";
import { TODO_PRIORITY_LABEL } from "../../constants/labels";
import type { TodoItem, TodoPriority, TodoProject } from "../../types";

type SelectionState = {
  selected: boolean;
  is_urgent: boolean;
  is_important: boolean;
};

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  backlog: TodoItem[];
  projects: Pick<TodoProject, "id" | "name">[];
  priorities: TodoPriority[];
  onEditTodo: (todo: TodoItem) => void;
};

const emptySelection = (): SelectionState => ({
  selected: false,
  is_urgent: false,
  is_important: false,
});

const BacklogPromoteDialog = ({
  open,
  onOpenChange,
  backlog,
  projects,
  priorities,
  onEditTodo,
}: Props): React.ReactElement => {
  const route = useRoute();
  const [projectFilter, setProjectFilter] = useState("");
  const [priorityFilter, setPriorityFilter] = useState("");
  const [selections, setSelections] = useState<Record<number, SelectionState>>(
    {},
  );
  const [processing, setProcessing] = useState(false);

  useEffect(() => {
    if (!open) {
      return;
    }
    setSelections({});
    setProjectFilter("");
    setPriorityFilter("");
  }, [open, backlog]);

  const projectOptions = [
    { value: "", label: "Tất cả project" },
    ...projects.map((project) => ({
      value: String(project.id),
      label: project.name,
    })),
  ];

  const priorityOptions = [
    { value: "", label: "Tất cả priority" },
    ...priorities.map((priority) => ({
      value: priority,
      label: TODO_PRIORITY_LABEL[priority],
    })),
  ];

  const filtered = backlog.filter((todo) => {
    if (projectFilter !== "" && String(todo.project_id ?? "") !== projectFilter) {
      return false;
    }
    if (priorityFilter !== "" && todo.priority !== priorityFilter) {
      return false;
    }
    return true;
  });

  const selectedItems = filtered.filter(
    (todo) => selections[todo.id]?.selected === true,
  );

  const getSelection = (id: number): SelectionState =>
    selections[id] ?? emptySelection();

  const patchSelection = (id: number, patch: Partial<SelectionState>): void => {
    setSelections((prev) => {
      const current = prev[id] ?? emptySelection();
      const next: SelectionState = { ...current, ...patch };
      if (!next.selected) {
        next.is_urgent = false;
        next.is_important = false;
      }
      return { ...prev, [id]: next };
    });
  };

  const submit = (): void => {
    const items = selectedItems.map((todo) => {
      const selection = getSelection(todo.id);
      return {
        id: todo.id,
        is_urgent: selection.is_urgent,
        is_important: selection.is_important,
      };
    });

    if (items.length === 0) {
      return;
    }

    setProcessing(true);
    router.post(
      route("matrix.backlog.promote"),
      { items },
      {
        preserveScroll: true,
        onFinish: () => setProcessing(false),
        onSuccess: () => onOpenChange(false),
      },
    );
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl max-h-[90vh] overflow-hidden flex flex-col z-[210] gap-3">
        <DialogHeader>
          <DialogTitle>Backlog → Matrix</DialogTitle>
          <DialogDescription>
            Chọn todo, gán Urgent / Important, rồi đưa vào Matrix (status Todo).
          </DialogDescription>
        </DialogHeader>

        <div className="flex flex-wrap gap-3 shrink-0">
          <div className="w-full sm:w-56">
            <Combobox
              options={projectOptions}
              value={projectFilter}
              handleChange={setProjectFilter}
              placeholder="Tất cả project"
              searchPlaceholder="Tìm project..."
              contentClassName="z-[240]"
            />
          </div>
          <div className="w-full sm:w-48">
            <Combobox
              options={priorityOptions}
              value={priorityFilter}
              handleChange={setPriorityFilter}
              placeholder="Tất cả priority"
              searchPlaceholder="Tìm priority..."
              contentClassName="z-[240]"
            />
          </div>
          <p className="text-xs text-muted-foreground self-center ml-auto">
            {filtered.length} / {backlog.length} · đã chọn {selectedItems.length}
          </p>
        </div>

        <div className="min-h-0 flex-1 overflow-y-auto -mx-1 px-1 space-y-2">
          {filtered.length === 0 && (
            <p className="py-10 text-center text-sm text-muted-foreground">
              Không có todo backlog phù hợp filter.
            </p>
          )}

          {filtered.map((todo) => {
            const selection = getSelection(todo.id);
            return (
              <BacklogRow
                key={todo.id}
                todo={todo}
                selection={selection}
                onToggleSelected={(selected) =>
                  patchSelection(todo.id, { selected })
                }
                onToggleUrgent={(is_urgent) =>
                  patchSelection(todo.id, { is_urgent })
                }
                onToggleImportant={(is_important) =>
                  patchSelection(todo.id, { is_important })
                }
                onEdit={() => onEditTodo(todo)}
              />
            );
          })}
        </div>

        <DialogFooter className="shrink-0">
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            Đóng
          </Button>
          <Button
            type="button"
            disabled={processing || selectedItems.length === 0}
            onClick={submit}
          >
            Đưa vào Matrix ({selectedItems.length})
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
};

type RowProps = {
  todo: TodoItem;
  selection: SelectionState;
  onToggleSelected: (selected: boolean) => void;
  onToggleUrgent: (urgent: boolean) => void;
  onToggleImportant: (important: boolean) => void;
  onEdit: () => void;
};

const BacklogRow = ({
  todo,
  selection,
  onToggleSelected,
  onToggleUrgent,
  onToggleImportant,
  onEdit,
}: RowProps): React.ReactElement => {
  const enabled = selection.selected;

  return (
    <div
      className={cn(
        "rounded-md border border-white/10 bg-white/[0.03] px-3 py-2.5 transition-colors",
        enabled && "border-sky-500/40 bg-sky-950/20",
      )}
    >
      <div className="flex items-start gap-3">
        <Checkbox
          className="mt-1"
          checked={selection.selected}
          onCheckedChange={(value) => onToggleSelected(value === true)}
          aria-label={`Chọn ${todo.title}`}
        />

        <div className="min-w-0 flex-1">
          <HoverCard openDelay={200} closeDelay={100}>
            <HoverCardTrigger asChild>
              <button
                type="button"
                onClick={onEdit}
                className="text-left text-sm font-medium text-gray-100 hover:text-sky-300 transition-colors cursor-pointer truncate max-w-full"
                aria-label={`Sửa ${todo.title}`}
              >
                {todo.title}
              </button>
            </HoverCardTrigger>
            <HoverCardContent
              side="right"
              align="start"
              collisionPadding={16}
              className="w-80 z-[250] space-y-2"
            >
              <p className="text-sm font-semibold text-foreground leading-snug">
                {todo.title}
              </p>
              <div className="flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-muted-foreground">
                {todo.project?.name && <span>{todo.project.name}</span>}
                <span className="uppercase tracking-wide">
                  {TODO_PRIORITY_LABEL[todo.priority]}
                </span>
                {todo.due_at && (
                  <span className="tabular-nums">
                    Due {todo.due_at.slice(0, 10)}
                  </span>
                )}
              </div>
              <p className="text-xs text-muted-foreground whitespace-pre-wrap break-words max-h-40 overflow-y-auto">
                {todo.description?.trim()
                  ? todo.description
                  : "Không có mô tả."}
              </p>
            </HoverCardContent>
          </HoverCard>

          <div className="flex flex-wrap items-center gap-2 mt-0.5 text-[11px] text-muted-foreground">
            {todo.project?.name && <span>{todo.project.name}</span>}
            <span className="uppercase tracking-wide">
              {TODO_PRIORITY_LABEL[todo.priority]}
            </span>
            {todo.due_at && (
              <span className="tabular-nums">Due {todo.due_at.slice(0, 10)}</span>
            )}
          </div>
        </div>

        <div className="flex flex-col gap-2 shrink-0 pt-0.5">
          <label
            className={cn(
              "inline-flex items-center gap-1.5 text-xs",
              enabled ? "text-gray-200" : "text-muted-foreground/50",
            )}
          >
            <Checkbox
              checked={selection.is_urgent}
              disabled={!enabled}
              onCheckedChange={(value) => onToggleUrgent(value === true)}
            />
            Urgent
          </label>
          <label
            className={cn(
              "inline-flex items-center gap-1.5 text-xs",
              enabled ? "text-gray-200" : "text-muted-foreground/50",
            )}
          >
            <Checkbox
              checked={selection.is_important}
              disabled={!enabled}
              onCheckedChange={(value) => onToggleImportant(value === true)}
            />
            Important
          </label>
        </div>
      </div>
    </div>
  );
};

export default BacklogPromoteDialog;
