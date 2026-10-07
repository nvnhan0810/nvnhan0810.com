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
import { Plus, Star } from "lucide-react";
import { useRoute } from "ziggy-js";
import ReaderNav from "../../../components/ReaderNav";
import { formatBytes, type ReaderCollection, type ReaderDocument } from "../../../types";

type Props = RootProps & {
  documents: ReaderDocument[];
  collections: ReaderCollection[];
};

const ListPage = ({ auth, documents }: Props) => {
  const route = useRoute();

  const handleDelete = (id: string) => {
    router.delete(route("admin.reader.documents.destroy", id));
  };

  return (
    <PrivateLayout auth={auth}>
      <ReaderNav />
      <div className="mb-4 flex items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Documents</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Quản lý PDF Reader — upload / kéo từ link, sửa tên &amp; collection, xem file.
          </p>
        </div>
        <Button
          variant="outline"
          onClick={() => router.get(route("admin.reader.documents.create"))}
        >
          <Plus className="mr-1 h-4 w-4" /> Thêm
        </Button>
      </div>

      <div className="overflow-x-auto rounded-xl border border-border">
        <table className="w-full text-sm">
          <thead className="bg-muted/50 text-left text-foreground">
            <tr>
              <th className="px-3 py-2 font-semibold">Title</th>
              <th className="px-3 py-2 font-semibold">Status</th>
              <th className="px-3 py-2 font-semibold">Pages</th>
              <th className="px-3 py-2 font-semibold">Size</th>
              <th className="px-3 py-2 font-semibold">Updated</th>
              <th className="px-3 py-2 font-semibold">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border text-foreground">
            {documents.length === 0 && (
              <tr>
                <td colSpan={6} className="px-3 py-6 text-center text-muted-foreground">
                  Chưa có document. Upload PDF hoặc kéo từ link.
                </td>
              </tr>
            )}
            {documents.map((doc) => (
              <tr key={doc.id} className="bg-card">
                <td className="px-3 py-2">
                  <div className="flex items-center gap-1.5">
                    {doc.is_favorite && (
                      <Star className="h-3.5 w-3.5 fill-amber-400 text-amber-400" />
                    )}
                    <span className="font-medium">{doc.title}</span>
                  </div>
                </td>
                <td className="px-3 py-2 capitalize">{doc.status}</td>
                <td className="px-3 py-2">{doc.page_count || "—"}</td>
                <td className="px-3 py-2">{formatBytes(doc.byte_size)}</td>
                <td className="px-3 py-2 whitespace-nowrap">
                  {doc.updated_at.slice(0, 16).replace("T", " ")}
                </td>
                <td className="px-3 py-2">
                  <div className="flex flex-wrap gap-2">
                    <a
                      href={route("admin.reader.documents.show", doc.id)}
                      className="text-blue-600 dark:text-blue-400"
                    >
                      View
                    </a>
                    <a
                      href={route("admin.reader.documents.edit", doc.id)}
                      className="text-blue-600 dark:text-blue-400"
                    >
                      Edit
                    </a>
                    <AlertDialog>
                      <AlertDialogTrigger asChild>
                        <button type="button" className="cursor-pointer text-red-600 dark:text-red-400">
                          Trash
                        </button>
                      </AlertDialogTrigger>
                      <AlertDialogContent>
                        <AlertDialogHeader>
                          <AlertDialogTitle>Chuyển vào thùng rác?</AlertDialogTitle>
                          <AlertDialogDescription>
                            <strong>{doc.title}</strong> sẽ vào trash (có thể restore sau).
                          </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                          <AlertDialogCancel>Hủy</AlertDialogCancel>
                          <AlertDialogAction
                            className="bg-red-500 hover:bg-red-600"
                            onClick={() => handleDelete(doc.id)}
                          >
                            Trash
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
