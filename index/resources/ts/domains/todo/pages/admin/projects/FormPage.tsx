import { Button } from "@/ts/components/ui/button";
import { Input } from "@/ts/components/ui/input";
import { Label } from "@/ts/components/ui/label";
import PrivateLayout, { RootProps } from "@/ts/layouts/PrivateLayout";
import { router, useForm } from "@inertiajs/react";
import { useRoute } from "ziggy-js";
import TodoNav from "../../../components/TodoNav";
import type { TodoProject } from "../../../types";

type Props = RootProps & {
  project: TodoProject | null;
};

const FormPage = ({ auth, project }: Props) => {
  const route = useRoute();
  const isEdit = Boolean(project?.id);

  const { data, setData, post, put, processing, errors } = useForm({
    name: project?.name ?? "",
    git_repo_url: project?.git_repo_url ?? "",
    domain: project?.domain ?? "",
  });

  const submit = (e: React.FormEvent): void => {
    e.preventDefault();
    if (isEdit && project) {
      put(route("todos.projects.update", project.id));
      return;
    }
    post(route("todos.projects.store"));
  };

  return (
    <PrivateLayout auth={auth}>
      <TodoNav />
      <h1 className="text-2xl font-bold text-gray-100 mb-4">
        {isEdit ? "Sửa project" : "Tạo project"}
      </h1>

      <form onSubmit={submit} className="max-w-2xl space-y-4">
        <div>
          <Label>Tên</Label>
          <Input value={data.name} onChange={(e) => setData("name", e.target.value)} />
          {errors.name && <p className="text-red-400 text-sm">{errors.name}</p>}
        </div>
        <div>
          <Label>Domain (tuỳ chọn)</Label>
          <Input
            value={data.domain}
            placeholder="example.nvnhan0810.com"
            onChange={(e) => setData("domain", e.target.value)}
          />
          {errors.domain && <p className="text-red-400 text-sm">{errors.domain}</p>}
        </div>
        <div>
          <Label>Git repo URL (tuỳ chọn)</Label>
          <Input
            value={data.git_repo_url}
            placeholder="https://github.com/..."
            onChange={(e) => setData("git_repo_url", e.target.value)}
          />
          {errors.git_repo_url && (
            <p className="text-red-400 text-sm">{errors.git_repo_url}</p>
          )}
        </div>
        <div className="flex gap-2">
          <Button type="submit" disabled={processing}>
            Lưu
          </Button>
          <Button
            type="button"
            variant="outline"
            onClick={() => router.get(route("todos.projects.index"))}
          >
            Hủy
          </Button>
        </div>
      </form>
    </PrivateLayout>
  );
};

export default FormPage;
