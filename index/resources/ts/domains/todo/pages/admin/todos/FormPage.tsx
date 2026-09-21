import { Button } from "@/ts/components/ui/button";
import { Checkbox } from "@/ts/components/ui/checkbox";
import { Input } from "@/ts/components/ui/input";
import { Label } from "@/ts/components/ui/label";
import { Textarea } from "@/ts/components/ui/textarea";
import PrivateLayout, { RootProps } from "@/ts/layouts/PrivateLayout";
import { router, useForm } from "@inertiajs/react";
import { useRoute } from "ziggy-js";
import TodoNav from "../../../components/TodoNav";
import type { TodoItem, TodoPriority, TodoProject, TodoStatus } from "../../../types";

type Props = RootProps & {
  todo: TodoItem | null;
  projects: Pick<TodoProject, "id" | "name">[];
  statuses: TodoStatus[];
  priorities: TodoPriority[];
  default_project_id: number | null;
};

const statusLabel: Record<TodoStatus, string> = {
  backlog: "Backlog",
  todo: "Todo",
  in_progress: "In progress",
  done: "Done",
  rejected: "Rejected",
};

const priorityLabel: Record<TodoPriority, string> = {
  low: "Low",
  medium: "Medium",
  high: "High",
  urgent: "Urgent",
};

const toDateInput = (value: string | null | undefined): string => {
  if (!value) {
    return "";
  }
  return value.slice(0, 10);
};

const FormPage = ({
  auth,
  todo,
  projects,
  statuses,
  priorities,
  default_project_id,
}: Props) => {
  const route = useRoute();
  const isEdit = Boolean(todo?.id);

  const { data, setData, post, put, processing, errors } = useForm({
    project_id: todo?.project_id ?? default_project_id ?? "",
    title: todo?.title ?? "",
    description: todo?.description ?? "",
    status: todo?.status ?? "backlog",
    priority: todo?.priority ?? "medium",
    due_at: toDateInput(todo?.due_at),
    is_urgent: todo?.is_urgent ?? false,
    is_important: todo?.is_important ?? false,
  });

  const submit = (e: React.FormEvent): void => {
    e.preventDefault();
    if (isEdit && todo) {
      put(route("todos.update", todo.id));
      return;
    }
    post(route("todos.store"));
  };

  return (
    <PrivateLayout auth={auth}>
      <TodoNav />
      <h1 className="text-2xl font-bold text-gray-100 mb-4">
        {isEdit ? "Sửa todo" : "Tạo todo"}
      </h1>

      <form onSubmit={submit} className="max-w-2xl space-y-4">
        <div>
          <Label>Title</Label>
          <Input value={data.title} onChange={(e) => setData("title", e.target.value)} />
          {errors.title && <p className="text-red-400 text-sm">{errors.title}</p>}
        </div>

        <div>
          <Label>Project</Label>
          <select
            className="flex h-9 w-full rounded-md border border-gray-700 bg-transparent px-3 text-sm text-gray-200"
            value={data.project_id === null || data.project_id === undefined ? "" : String(data.project_id)}
            onChange={(e) =>
              setData("project_id", e.target.value === "" ? "" : Number(e.target.value))
            }
          >
            <option value="">— Không gán —</option>
            {projects.map((project) => (
              <option key={project.id} value={project.id}>
                {project.name}
              </option>
            ))}
          </select>
          {errors.project_id && <p className="text-red-400 text-sm">{errors.project_id}</p>}
        </div>

        <div>
          <Label>Description</Label>
          <Textarea
            value={data.description}
            onChange={(e) => setData("description", e.target.value)}
            rows={4}
          />
          {errors.description && (
            <p className="text-red-400 text-sm">{errors.description}</p>
          )}
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <Label>Status</Label>
            <select
              className="flex h-9 w-full rounded-md border border-gray-700 bg-transparent px-3 text-sm text-gray-200"
              value={data.status}
              onChange={(e) => setData("status", e.target.value as TodoStatus)}
            >
              {statuses.map((status) => (
                <option key={status} value={status}>
                  {statusLabel[status]}
                </option>
              ))}
            </select>
            {errors.status && <p className="text-red-400 text-sm">{errors.status}</p>}
          </div>
          <div>
            <Label>Priority</Label>
            <select
              className="flex h-9 w-full rounded-md border border-gray-700 bg-transparent px-3 text-sm text-gray-200"
              value={data.priority}
              onChange={(e) => setData("priority", e.target.value as TodoPriority)}
            >
              {priorities.map((priority) => (
                <option key={priority} value={priority}>
                  {priorityLabel[priority]}
                </option>
              ))}
            </select>
            {errors.priority && <p className="text-red-400 text-sm">{errors.priority}</p>}
          </div>
        </div>

        <div>
          <Label>Due date</Label>
          <Input
            type="date"
            value={data.due_at}
            onChange={(e) => setData("due_at", e.target.value)}
          />
          {errors.due_at && <p className="text-red-400 text-sm">{errors.due_at}</p>}
        </div>

        <div className="flex flex-wrap gap-6">
          <label className="flex items-center gap-2 text-gray-300">
            <Checkbox
              checked={data.is_urgent}
              onCheckedChange={(v) => setData("is_urgent", Boolean(v))}
            />
            Urgent
          </label>
          <label className="flex items-center gap-2 text-gray-300">
            <Checkbox
              checked={data.is_important}
              onCheckedChange={(v) => setData("is_important", Boolean(v))}
            />
            Important
          </label>
        </div>

        <div className="flex gap-2">
          <Button type="submit" disabled={processing}>
            Lưu
          </Button>
          <Button
            type="button"
            variant="outline"
            onClick={() => router.get(route("todos.index"))}
          >
            Hủy
          </Button>
        </div>
      </form>
    </PrivateLayout>
  );
};

export default FormPage;
