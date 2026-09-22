import { Button } from "@/ts/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/ts/components/ui/dialog";
import { useForm } from "@inertiajs/react";
import { useRoute } from "ziggy-js";
import type { TodoItem, TodoPriority, TodoProject, TodoStatus } from "../types";
import TodoFormFields, { type TodoFormValues } from "./TodoFormFields";

export type TodoCreateDefaults = {
  status: TodoStatus;
  is_urgent: boolean;
  is_important: boolean;
  priority?: TodoPriority;
  project_id?: number | null;
};

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  mode: "create" | "edit";
  todo?: TodoItem | null;
  defaults?: TodoCreateDefaults;
  projects: Pick<TodoProject, "id" | "name">[];
  statuses: TodoStatus[];
  priorities: TodoPriority[];
};

const toDateInput = (value: string | null | undefined): string => {
  if (!value) {
    return "";
  }
  return value.slice(0, 10);
};

const buildInitialValues = (
  mode: "create" | "edit",
  todo: TodoItem | null | undefined,
  defaults: TodoCreateDefaults | undefined,
): TodoFormValues & { return_to: "matrix" } => {
  if (mode === "edit" && todo) {
    return {
      project_id: todo.project_id ?? "",
      title: todo.title,
      description: todo.description ?? "",
      status: todo.status,
      priority: todo.priority,
      due_at: toDateInput(todo.due_at),
      is_urgent: todo.is_urgent,
      is_important: todo.is_important,
      return_to: "matrix",
    };
  }

  return {
    project_id: defaults?.project_id ?? "",
    title: "",
    description: "",
    status: defaults?.status ?? "todo",
    priority: defaults?.priority ?? "medium",
    due_at: "",
    is_urgent: defaults?.is_urgent ?? false,
    is_important: defaults?.is_important ?? false,
    return_to: "matrix",
  };
};

const TodoFormModal = ({
  open,
  onOpenChange,
  mode,
  todo,
  defaults,
  projects,
  statuses,
  priorities,
}: Props): React.ReactElement => {
  const formKey =
    mode === "edit" && todo
      ? `edit-${todo.id}`
      : `create-${defaults?.status ?? "todo"}-${String(defaults?.is_urgent)}-${String(defaults?.is_important)}`;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent
        className="max-w-lg max-h-[90vh] overflow-y-auto z-[210]"
        onClick={(event) => event.stopPropagation()}
        onPointerDown={(event) => event.stopPropagation()}
      >
        {open && (
          <TodoFormModalBody
            key={formKey}
            mode={mode}
            todo={todo}
            defaults={defaults}
            projects={projects}
            statuses={statuses}
            priorities={priorities}
            onClose={() => onOpenChange(false)}
          />
        )}
      </DialogContent>
    </Dialog>
  );
};

type BodyProps = Omit<Props, "open" | "onOpenChange"> & {
  onClose: () => void;
};

const TodoFormModalBody = ({
  mode,
  todo,
  defaults,
  projects,
  statuses,
  priorities,
  onClose,
}: BodyProps): React.ReactElement => {
  const route = useRoute();
  const isEdit = mode === "edit" && Boolean(todo?.id);

  const { data, setData, post, put, processing, errors } = useForm(
    buildInitialValues(mode, todo, defaults),
  );

  const submit = (e: React.FormEvent): void => {
    e.preventDefault();
    if (isEdit && todo) {
      put(route("todos.update", todo.id), {
        preserveScroll: true,
        onSuccess: () => onClose(),
      });
      return;
    }
    post(route("todos.store"), {
      preserveScroll: true,
      onSuccess: () => onClose(),
    });
  };

  const values: TodoFormValues = {
    project_id: data.project_id,
    title: data.title,
    description: data.description,
    status: data.status,
    priority: data.priority,
    due_at: data.due_at,
    is_urgent: data.is_urgent,
    is_important: data.is_important,
  };

  return (
    <form onSubmit={submit} className="space-y-4">
      <DialogHeader>
        <DialogTitle>{isEdit ? "Sửa todo" : "Tạo todo"}</DialogTitle>
        <DialogDescription>
          {isEdit
            ? "Cập nhật thông tin todo. Các trường đều chỉnh sửa được."
            : "Giá trị mặc định lấy từ vùng Matrix bạn vừa chọn — vẫn chỉnh sửa bình thường."}
        </DialogDescription>
      </DialogHeader>

      <TodoFormFields
        values={values}
        errors={errors}
        projects={projects}
        statuses={statuses}
        priorities={priorities}
        onChange={(key, value) => {
          setData(key, value as never);
        }}
      />

      <DialogFooter>
        <Button type="button" variant="outline" onClick={onClose}>
          Hủy
        </Button>
        <Button type="submit" disabled={processing}>
          Lưu
        </Button>
      </DialogFooter>
    </form>
  );
};

export default TodoFormModal;
