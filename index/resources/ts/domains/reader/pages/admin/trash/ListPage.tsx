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
import { useRoute } from "ziggy-js";
import ReaderNav from "../../../components/ReaderNav";
import { formatBytes, type ReaderDocument } from "../../../types";

type Props = RootProps & {
  documents: ReaderDocument[];
  retentionDays: number;
};

const ListPage = ({ auth, documents, retentionDays }: Props) => {
  const route = useRoute();

  const restore = (id: string) => {
    router.post(route("admin.reader.trash.restore", id));
  };

  const purge = (id: string) => {
    router.delete(route("admin.reader.trash.destroy", id));
  };

  const emptyTrash = () => {
    router.delete(route("admin.reader.trash.empty"));
  };

  return (
    <PrivateLayout auth={auth}>
      <ReaderNav />
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Trash</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Restore hoặc xoá vĩnh viễn. Tự purge sau {retentionDays} ngày.
          </p>
        </div>
        {documents.length > 0 && (
          <AlertDialog>
            <AlertDialogTrigger asChild>
              <Button variant="destructive">Empty trash</Button>
            </AlertDialogTrigger>
            <AlertDialogContent>
              <AlertDialogHeader>
                <AlertDialogTitle>Xoá toàn bộ thùng rác?</AlertDialogTitle>
                <AlertDialogDescription>
                  Không thể hoàn tác. Tất cả document trong trash sẽ bị xoá vĩnh viễn.
                </AlertDialogDescription>
              </AlertDialogHeader>
              <AlertDialogFooter>
                <AlertDialogCancel>Hủy</AlertDialogCancel>
                <AlertDialogAction className="bg-red-500 hover:bg-red-600" onClick={emptyTrash}>
                  Empty trash
                </AlertDialogAction>
              </AlertDialogFooter>
            </AlertDialogContent>
          </AlertDialog>
        )}
      </div>

      <div className="overflow-x-auto rounded-xl border border-border">
        <table className="w-full text-sm">
          <thead className="bg-muted/50 text-left text-foreground">
            <tr>
              <th className="px-3 py-2 font-semibold">Title</th>
              <th className="px-3 py-2 font-semibold">Size</th>
              <th className="px-3 py-2 font-semibold">Deleted</th>
              <th className="px-3 py-2 font-semibold">Purge at</th>
              <th className="px-3 py-2 font-semibold">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border text-foreground">
            {documents.length === 0 && (
              <tr>
                <td colSpan={5} className="px-3 py-6 text-center text-muted-foreground">
                  Thùng rác trống.
                </td>
              </tr>
            )}
            {documents.map((doc) => (
              <tr key={doc.id} className="bg-card">
                <td className="px-3 py-2 font-medium">{doc.title}</td>
                <td className="px-3 py-2">{formatBytes(doc.byte_size)}</td>
                <td className="px-3 py-2 whitespace-nowrap">
                  {doc.deleted_at?.slice(0, 16).replace("T", " ") ?? "—"}
                </td>
                <td className="px-3 py-2 whitespace-nowrap">
                  {doc.purge_at?.slice(0, 16).replace("T", " ") ?? "—"}
                </td>
                <td className="px-3 py-2">
                  <div className="flex flex-wrap gap-2">
                    <button
                      type="button"
                      className="cursor-pointer text-emerald-600 dark:text-green-400"
                      onClick={() => restore(doc.id)}
                    >
                      Restore
                    </button>
                    <AlertDialog>
                      <AlertDialogTrigger asChild>
                        <button type="button" className="cursor-pointer text-red-600 dark:text-red-400">
                          Delete forever
                        </button>
                      </AlertDialogTrigger>
                      <AlertDialogContent>
                        <AlertDialogHeader>
                          <AlertDialogTitle>Xoá vĩnh viễn?</AlertDialogTitle>
                          <AlertDialogDescription>
                            <strong>{doc.title}</strong> và file PDF sẽ bị xoá hẳn.
                          </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                          <AlertDialogCancel>Hủy</AlertDialogCancel>
                          <AlertDialogAction
                            className="bg-red-500 hover:bg-red-600"
                            onClick={() => purge(doc.id)}
                          >
                            Delete forever
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
