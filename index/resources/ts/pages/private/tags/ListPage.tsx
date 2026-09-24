import PaginationBar from "@/ts/components/common/PaginationBar";
import SearchForm from "@/ts/components/posts/SearchForm";
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from "@/ts/components/ui/alert-dialog";
import { Button } from "@/ts/components/ui/button";
import PrivateLayout, { RootProps } from "@/ts/layouts/PrivateLayout";
import { Pagination } from "@/ts/types/common";
import { Tag } from "@/ts/types/tag";
import { router } from "@inertiajs/react";
import { Plus } from "lucide-react";
import { useRoute } from "ziggy-js";

type Props = RootProps & {
  tags: Pagination<Tag>;
}

const ListPage = ({ auth, tags }: Props) => {
  const route = useRoute();

  const handleDelete = (id: number) => {
    router.delete(route('admin.tags.destroy', id), {
      onSuccess: () => {
        router.reload();
      }
    });
  }

  const DeleteButton = ({ id }: { id: number }) => {
    return (
      <AlertDialog>
        <AlertDialogTrigger asChild>
          <span className="text-red-500 cursor-pointer">Delete</span>
        </AlertDialogTrigger>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Xoá thẻ</AlertDialogTitle>
            <AlertDialogDescription>
              Bạn thật sự muốn xóa thẻ này?
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Hủy</AlertDialogCancel>
            <AlertDialogAction className="bg-red-500 hover:bg-red-600" onClick={() => handleDelete(id)}>Xoá</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    )
  }

  const handleSearch = (search: string) => {
    router.get(route('admin.index'), { search: search }, {
      preserveUrl: true,
      preserveScroll: true,
      replace: true,
    });
  }

  return (
    <PrivateLayout auth={auth}>
      <div>
        <div className="flex justify-between items-center mb-4">
          <h1 className="text-2xl font-bold text-foreground text-center">
            Quản Lý thẻ
          </h1>
          <Button variant="outline" title="Thêm thẻ" onClick={() => router.get(route('admin.tags.create'))}>
            <Plus className="w-4 h-4" />
          </Button>
        </div>

        <div className="mb-4 flex justify-start items-center gap-2">
          <SearchForm onSearch={handleSearch} />
        </div>

        <div className="max-w-auto overflow-x-auto">
          <table className="w-full">
            <thead className="bg-muted/50 text-foreground text-center">
              <tr>
                <th className="px-4 py-2 border border-border">ID</th>
                <th className="px-4 py-2 border border-border">Tên</th>
                <th className="px-4 py-2 border border-border">Số lượng bài viết</th>
                <th className="px-4 py-2 border border-border">Actions</th>
              </tr>
            </thead>
            <tbody className="text-foreground">
              {tags.data.map((tag) => (
                <tr key={tag.id}>
                  <td className="px-4 py-2 border border-border text-center">{tag.id}</td>
                  <td className="px-4 py-2 border border-border">{tag.name}</td>
                  <td className="px-4 py-2 border border-border text-center">{tag.posts_count}</td>
                  <td className="px-4 py-2 border border-border">
                    <div className="flex gap-2 justify-center items-center">
                      <a href={route('admin.tags.edit', tag.id)} className="text-blue-500">Edit</a>
                      {tag.posts_count === 0 && <DeleteButton id={tag.id} />}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
            <tfoot>
              <tr>
                <td colSpan={6} className="p-4 text-center">
                  <PaginationBar pagination={tags} />
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </PrivateLayout>
  );
};

export default ListPage;
