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
import PrivateLayout, { type RootProps } from "@/ts/layouts/PrivateLayout";
import { router } from "@inertiajs/react";
import { Plus } from "lucide-react";
import { useRoute } from "ziggy-js";
import ReaderNav from "../../../components/ReaderNav";
import type { ReaderCollection } from "../../../types";

type Props = RootProps & {
  collections: ReaderCollection[];
};

const ListPage = ({ auth, collections }: Props) => {
  const route = useRoute();

  const handleDelete = (id: string) => {
    router.delete(route("admin.reader.collections.destroy", id));
  };

  return (
    <PrivateLayout auth={auth}>
      <ReaderNav />
      <div className="mb-4 flex items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Collections</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Đổi tên và thêm / gỡ document trong collection.
          </p>
        </div>
        <Button
          variant="outline"
          onClick={() => router.get(route("admin.reader.collections.create"))}
        >
          <Plus className="mr-1 h-4 w-4" /> Thêm
        </Button>
      </div>

      <div className="overflow-x-auto rounded-xl border border-border">
        <table className="w-full text-sm">
          <thead className="bg-muted/50 text-left text-foreground">
            <tr>
              <th className="px-3 py-2 font-semibold">Name</th>
              <th className="px-3 py-2 font-semibold">Documents</th>
              <th className="px-3 py-2 font-semibold">Updated</th>
              <th className="px-3 py-2 font-semibold">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border text-foreground">
            {collections.length === 0 && (
              <tr>
                <td colSpan={4} className="px-3 py-6 text-center text-muted-foreground">
                  Chưa có collection.
                </td>
              </tr>
            )}
            {collections.map((collection) => (
              <tr key={collection.id} className="bg-card">
                <td className="px-3 py-2 font-medium">{collection.name}</td>
                <td className="px-3 py-2">{collection.document_count}</td>
                <td className="px-3 py-2 whitespace-nowrap">
                  {collection.updated_at.slice(0, 16).replace("T", " ")}
                </td>
                <td className="px-3 py-2">
                  <div className="flex gap-2">
                    <a
                      href={route("admin.reader.collections.edit", collection.id)}
                      className="text-blue-600 dark:text-blue-400"
                    >
                      Edit
                    </a>
                    <AlertDialog>
                      <AlertDialogTrigger asChild>
                        <button type="button" className="cursor-pointer text-red-600 dark:text-red-400">
                          Delete
                        </button>
                      </AlertDialogTrigger>
                      <AlertDialogContent>
                        <AlertDialogHeader>
                          <AlertDialogTitle>Xóa collection?</AlertDialogTitle>
                          <AlertDialogDescription>
                            Xóa <strong>{collection.name}</strong>. Documents vẫn giữ nguyên.
                          </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                          <AlertDialogCancel>Hủy</AlertDialogCancel>
                          <AlertDialogAction
                            className="bg-red-500 hover:bg-red-600"
                            onClick={() => handleDelete(collection.id)}
                          >
                            Xóa
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
