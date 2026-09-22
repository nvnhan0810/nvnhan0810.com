import { Button } from "@/ts/components/ui/button";
import { router, useForm } from "@inertiajs/react";
import PrivateLayout, { RootProps } from "@/ts/layouts/PrivateLayout";
import { useRoute } from "ziggy-js";
import TodoFormFields from "../../../components/TodoFormFields";
import TodoNav from "../../../components/TodoNav";
import type { TodoItem, TodoPriority, TodoProject, TodoStatus } from "../../../types";

type Props = RootProps & {
  todo: TodoItem | null;
  projects: Pick<TodoProject, "id" | "name">[];
  statuses: TodoStatus[];
  priorities: TodoPriority[];
  default_project_id: number | null;
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
}: Props): React.ReactElement => {
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
        <TodoFormFields
          values={data}
          errors={errors}
          projects={projects}
          statuses={statuses}
          priorities={priorities}
          onChange={(key, value) => {
            setData(key, value as never);
          }}
        />

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
