import { Button } from "@/ts/components/ui/button";
import { Checkbox } from "@/ts/components/ui/checkbox";
import { Input } from "@/ts/components/ui/input";
import { Label } from "@/ts/components/ui/label";
import PrivateLayout, { type RootProps } from "@/ts/layouts/PrivateLayout";
import { router, useForm } from "@inertiajs/react";
import { useRoute } from "ziggy-js";
import ReaderNav from "../../../components/ReaderNav";
import type { ReaderCollection, ReaderDocument } from "../../../types";

type Props = RootProps & {
  collection: ReaderCollection | null;
  memberIds: string[];
  documents: ReaderDocument[];
};

const FormPage = ({ auth, collection, memberIds, documents }: Props) => {
  const route = useRoute();
  const isEdit = Boolean(collection?.id);

  const { data, setData, post, put, processing, errors } = useForm({
    name: collection?.name ?? "",
    document_ids: memberIds,
  });

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    if (isEdit && collection) {
      put(route("admin.reader.collections.update", collection.id));
    } else {
      post(route("admin.reader.collections.store"));
    }
  };

  const toggleDocument = (id: string) => {
    setData(
      "document_ids",
      data.document_ids.includes(id)
        ? data.document_ids.filter((docId) => docId !== id)
        : [...data.document_ids, id],
    );
  };

  return (
    <PrivateLayout auth={auth}>
      <ReaderNav />
      <h1 className="mb-2 text-2xl font-bold text-foreground">
        {isEdit ? "Sửa collection" : "Tạo collection"}
      </h1>
      <p className="mb-4 text-sm text-muted-foreground">
        Đổi tên và chọn documents thuộc collection này.
      </p>

      <form onSubmit={submit} className="max-w-2xl space-y-4">
        <div>
          <Label htmlFor="name">Tên</Label>
          <Input
            id="name"
            value={data.name}
            onChange={(e) => setData("name", e.target.value)}
          />
          {errors.name && <p className="mt-1 text-sm text-red-400">{errors.name}</p>}
        </div>

        <div>
          <Label className="mb-2 block">Documents trong collection</Label>
          <div className="max-h-80 space-y-2 overflow-y-auto rounded-md border border-border p-3">
            {documents.length === 0 && (
              <p className="text-sm text-muted-foreground">Chưa có document nào.</p>
            )}
            {documents.map((doc) => (
              <label key={doc.id} className="flex items-center gap-2 text-sm text-foreground">
                <Checkbox
                  checked={data.document_ids.includes(doc.id)}
                  onCheckedChange={() => toggleDocument(doc.id)}
                />
                <span className="min-w-0 truncate">{doc.title}</span>
              </label>
            ))}
          </div>
        </div>

        <div className="flex gap-2">
          <Button type="submit" disabled={processing}>
            Lưu
          </Button>
          <Button
            type="button"
            variant="outline"
            onClick={() => router.get(route("admin.reader.collections.index"))}
          >
            Hủy
          </Button>
        </div>
      </form>
    </PrivateLayout>
  );
};

export default FormPage;
