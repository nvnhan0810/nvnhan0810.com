import PaginationBar from "@/ts/components/common/PaginationBar";
import SearchForm from "@/ts/components/posts/SearchForm";
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle, AlertDialogTrigger } from "@/ts/components/ui/alert-dialog";
import { Button } from "@/ts/components/ui/button";
import PrivateLayout, { RootProps } from "@/ts/layouts/PrivateLayout";
import { AuthUser } from "@/ts/types/auth";
import { Pagination } from "@/ts/types/common";
import { Series } from "@/ts/types/series";
import { router } from "@inertiajs/react";
import { format } from "date-fns";
import { Plus } from "lucide-react";
import { useRoute } from "ziggy-js";

type Props = RootProps & {
  series: Pagination<Series & { posts_count: number }>;
  auth: AuthUser;
}

const ListPage = ({ auth, series }: Props) => {
  const route = useRoute();
  console.log(series);

  const handleDelete = (id: number) => {
    router.delete(route('admin.series.destroy', id), {
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
            <AlertDialogTitle>Xoá series</AlertDialogTitle>
            <AlertDialogDescription>
              Bạn thật sự muốn xóa series này?
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
          <h1 className="text-2xl font-bold text-gray-100 text-center">
            Quản Lý series
          </h1>
          <Button variant="outline" title="Thêm series" onClick={() => router.get(route('admin.series.create'))}>
            <Plus className="w-4 h-4" />
          </Button>
        </div>

        <div className="mb-4 flex justify-start items-center gap-2">
          <SearchForm onSearch={handleSearch} />
        </div>

        <div className="max-w-auto overflow-x-auto">
          <table className="w-full">
            <thead className="bg-gray-100 text-gray-900">
              <tr>
                <th className="px-4 py-2 border border-gray-400">ID</th>
                <th className="px-4 py-2 border border-gray-400">Name</th>
                <th className="px-4 py-2 border border-gray-400">Post Count</th>
                <th className="px-4 py-2 border border-gray-400">Created At</th>
                <th className="px-4 py-2 border border-gray-400">Actions</th>
              </tr>
            </thead>
            <tbody className="text-gray-300">
              {series.data.map((seriesItem) => (
                <tr key={seriesItem.id}>
                  <td className="px-4 py-2 border border-gray-300">{seriesItem.id}</td>
                  <td className="px-4 py-2 border border-gray-300">{seriesItem.name}</td>
                  <td className="px-4 py-2 border border-gray-300">
                    {seriesItem.posts_count}
                  </td>
                  <td className="px-4 py-2 border border-gray-300">{format(seriesItem.created_at, 'dd/MM/yyyy')}</td>
                  <td className="px-4 py-2 border border-gray-300">
                    <div className="flex gap-2">
                      <a href={route('admin.series.edit', seriesItem.id)} className="text-blue-500">Edit</a>
                      <DeleteButton id={seriesItem.id} />
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
            <tfoot>
              <tr>
                <td colSpan={6} className="p-4 text-center">
                  <PaginationBar pagination={series} />
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
