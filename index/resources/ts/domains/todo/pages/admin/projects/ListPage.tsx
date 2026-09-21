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
import PrivateLayout, { RootProps } from "@/ts/layouts/PrivateLayout";
import { router } from "@inertiajs/react";
import { Plus } from "lucide-react";
import { useRoute } from "ziggy-js";
import TodoNav from "../../../components/TodoNav";
import type { TodoProject } from "../../../types";

type Props = RootProps & {
  projects: TodoProject[];
};

const ListPage = ({ auth, projects }: Props) => {
  const route = useRoute();

  const handleDelete = (id: number): void => {
    router.delete(route("todos.projects.destroy", id));
  };

  return (
    <PrivateLayout auth={auth}>
      <TodoNav />
      <div className="flex justify-between items-center mb-4">
        <h1 className="text-2xl font-bold text-gray-100">Todo — Projects</h1>
        <Button
          variant="outline"
          onClick={() => router.get(route("todos.projects.create"))}
        >
          <Plus className="w-4 h-4 mr-1" /> New
        </Button>
      </div>

      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead className="bg-gray-100 text-gray-900">
            <tr>
              <th className="px-3 py-2 border">Name</th>
              <th className="px-3 py-2 border">Domain</th>
              <th className="px-3 py-2 border">Repo</th>
              <th className="px-3 py-2 border">Todos</th>
              <th className="px-3 py-2 border">Actions</th>
            </tr>
          </thead>
          <tbody className="text-gray-300">
            {projects.length === 0 && (
              <tr>
                <td colSpan={5} className="px-3 py-6 border text-center text-muted-foreground">
                  Chưa có project nào.
                </td>
              </tr>
            )}
            {projects.map((project) => (
              <tr key={project.id}>
                <td className="px-3 py-2 border">{project.name}</td>
                <td className="px-3 py-2 border">{project.domain ?? "—"}</td>
                <td className="px-3 py-2 border max-w-xs truncate">
                  {project.git_repo_url ? (
                    <a
                      href={project.git_repo_url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="text-blue-400 hover:underline"
                    >
                      {project.git_repo_url}
                    </a>
                  ) : (
                    "—"
                  )}
                </td>
                <td className="px-3 py-2 border text-center">{project.todos_count ?? 0}</td>
                <td className="px-3 py-2 border">
                  <div className="flex gap-3 justify-center items-center">
                    <a
                      href={route("todos.index", { project_id: project.id })}
                      className="text-blue-400"
                    >
                      Todos
                    </a>
                    <a
                      href={route("todos.projects.edit", project.id)}
                      className="text-blue-400"
                    >
                      Edit
                    </a>
                    <AlertDialog>
                      <AlertDialogTrigger asChild>
                        <span className="text-red-500 cursor-pointer">Delete</span>
                      </AlertDialogTrigger>
                      <AlertDialogContent>
                        <AlertDialogHeader>
                          <AlertDialogTitle>Xoá project</AlertDialogTitle>
                          <AlertDialogDescription>
                            Soft-delete project “{project.name}”?
                          </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                          <AlertDialogCancel>Hủy</AlertDialogCancel>
                          <AlertDialogAction
                            className="bg-red-500 hover:bg-red-600"
                            onClick={() => handleDelete(project.id)}
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
        </table>
      </div>
    </PrivateLayout>
  );
};

export default ListPage;
