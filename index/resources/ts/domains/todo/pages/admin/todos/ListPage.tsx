import PaginationBar from "@/ts/components/common/PaginationBar";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from "@/ts/components/ui/alert-dialog";
import { Button } from "@/ts/components/ui/button";
import Combobox from "@/ts/components/ui/combobox";
import PrivateLayout, { RootProps } from "@/ts/layouts/PrivateLayout";
import { Pagination } from "@/ts/types/common";
import { router } from "@inertiajs/react";
import { Plus } from "lucide-react";
import { useRoute } from "ziggy-js";
import TodoNav from "../../../components/TodoNav";
import { TODO_PRIORITY_LABEL, TODO_STATUS_LABEL } from "../../../constants/labels";
import type { TodoItem, TodoPriority, TodoProject, TodoStatus } from "../../../types";

type Props = RootProps & {
  todos: Pagination<TodoItem>;
  projects: Pick<TodoProject, "id" | "name">[];
  filters: {
    project_id: number | null;
    status: string | null;
  };
  statuses: TodoStatus[];
  priorities: TodoPriority[];
};

const ListPage = ({ auth, todos, projects, filters, statuses }: Props): React.ReactElement => {
  const route = useRoute();

  const applyFilter = (key: "project_id" | "status", value: string): void => {
    router.get(
      route("todos.index"),
      {
        project_id: key === "project_id" ? value || undefined : filters.project_id ?? undefined,
        status: key === "status" ? value || undefined : filters.status ?? undefined,
      },
      { preserveState: true, replace: true },
    );
  };

  const handleDelete = (id: number): void => {
    router.delete(route("todos.destroy", id));
  };

  const projectOptions = [
    { value: "", label: "All projects" },
    ...projects.map((project) => ({
      value: String(project.id),
      label: project.name,
    })),
  ];

  const statusOptions = [
    { value: "", label: "All statuses" },
    ...statuses.map((status) => ({
      value: status,
      label: TODO_STATUS_LABEL[status],
    })),
  ];

  return (
    <PrivateLayout auth={auth}>
      <TodoNav />
      <div className="flex justify-between items-center mb-4">
        <h1 className="text-2xl font-bold text-gray-100">Todo — Tasks</h1>
        <Button
          variant="outline"
          onClick={() =>
            router.get(
              route("todos.create"),
              filters.project_id ? { project_id: filters.project_id } : undefined,
            )
          }
        >
          <Plus className="w-4 h-4 mr-1" /> New
        </Button>
      </div>

      <div className="mb-4 flex flex-wrap gap-3">
        <div className="w-full sm:w-56">
          <Combobox
            options={projectOptions}
            value={filters.project_id != null ? String(filters.project_id) : ""}
            handleChange={(value) => applyFilter("project_id", value)}
            placeholder="All projects"
            searchPlaceholder="Tìm project..."
          />
        </div>
        <div className="w-full sm:w-48">
          <Combobox
            options={statusOptions}
            value={filters.status ?? ""}
            handleChange={(value) => applyFilter("status", value)}
            placeholder="All statuses"
            searchPlaceholder="Tìm status..."
          />
        </div>
      </div>

      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead className="bg-gray-100 text-gray-900">
            <tr>
              <th className="px-3 py-2 border">Title</th>
              <th className="px-3 py-2 border">Project</th>
              <th className="px-3 py-2 border">Status</th>
              <th className="px-3 py-2 border">Priority</th>
              <th className="px-3 py-2 border">Eisenhower</th>
              <th className="px-3 py-2 border">Due</th>
              <th className="px-3 py-2 border">Actions</th>
            </tr>
          </thead>
          <tbody className="text-gray-300">
            {todos.data.length === 0 && (
              <tr>
                <td colSpan={7} className="px-3 py-6 border text-center text-muted-foreground">
                  Chưa có todo nào.
                </td>
              </tr>
            )}
            {todos.data.map((todo) => (
              <tr key={todo.id}>
                <td className="px-3 py-2 border">{todo.title}</td>
                <td className="px-3 py-2 border">{todo.project?.name ?? "—"}</td>
                <td className="px-3 py-2 border text-center">
                  {TODO_STATUS_LABEL[todo.status as TodoStatus]}
                </td>
                <td className="px-3 py-2 border text-center">
                  {TODO_PRIORITY_LABEL[todo.priority as keyof typeof TODO_PRIORITY_LABEL]}
                </td>
                <td className="px-3 py-2 border text-center text-xs">
                  {[todo.is_urgent ? "U" : null, todo.is_important ? "I" : null]
                    .filter(Boolean)
                    .join("/") || "—"}
                </td>
                <td className="px-3 py-2 border text-center">
                  {todo.due_at ? todo.due_at.slice(0, 10) : "—"}
                </td>
                <td className="px-3 py-2 border">
                  <div className="flex gap-3 justify-center items-center">
                    <a href={route("todos.edit", todo.id)} className="text-blue-400">
                      Edit
                    </a>
                    <AlertDialog>
                      <AlertDialogTrigger asChild>
                        <span className="text-red-500 cursor-pointer">Delete</span>
                      </AlertDialogTrigger>
                      <AlertDialogContent>
                        <AlertDialogHeader>
                          <AlertDialogTitle>Xoá todo</AlertDialogTitle>
                          <AlertDialogDescription>
                            Soft-delete “{todo.title}”?
                          </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                          <AlertDialogCancel>Hủy</AlertDialogCancel>
                          <AlertDialogAction
                            className="bg-red-500 hover:bg-red-600"
                            onClick={() => handleDelete(todo.id)}
                          >
                            Xoá
                          </AlertDialogAction>
                        </AlertDialogFooter>
                      </AlertDialogContent>
                    </AlertDialog>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
          <tfoot>
            <tr>
              <td colSpan={7} className="p-4 text-center">
                <PaginationBar pagination={todos} />
              </td>
            </tr>
          </tfoot>
        </table>
      </div>
    </PrivateLayout>
  );
};

export default ListPage;
