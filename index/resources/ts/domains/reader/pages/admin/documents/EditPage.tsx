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
  document: ReaderDocument;
  collectionIds: string[];
  collections: ReaderCollection[];
};

const EditPage = ({ auth, document, collectionIds, collections }: Props) => {
  const route = useRoute();

  const { data, setData, put, processing, errors } = useForm({
    title: document.title,
    is_favorite: document.is_favorite,
    collection_ids: collectionIds,
  });

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    put(route("admin.reader.documents.update", document.id));
  };

  const toggleCollection = (id: string) => {
    setData(
      "collection_ids",
      data.collection_ids.includes(id)
        ? data.collection_ids.filter((cid) => cid !== id)
        : [...data.collection_ids, id],
    );
  };

  return (
    <PrivateLayout auth={auth}>
      <ReaderNav />
      <h1 className="mb-2 text-2xl font-bold text-foreground">Sửa document</h1>
      <p className="mb-4 text-sm text-muted-foreground">
        Chỉ đổi tên, yêu thích và danh sách collection. File PDF không thay đổi tại đây.
      </p>

      <form onSubmit={submit} className="max-w-2xl space-y-4">
        <div>
          <Label htmlFor="title">Tên</Label>
          <Input
            id="title"
            value={data.title}
            onChange={(e) => setData("title", e.target.value)}
          />
          {errors.title && <p className="mt-1 text-sm text-red-400">{errors.title}</p>}
        </div>

        <div className="flex items-center gap-2">
          <Checkbox
            id="is_favorite"
            checked={data.is_favorite}
            onCheckedChange={(v) => setData("is_favorite", Boolean(v))}
          />
          <Label htmlFor="is_favorite">Yêu thích</Label>
        </div>

        <div>
          <Label className="mb-2 block">Collections</Label>
          <div className="space-y-2 rounded-md border border-border p-3">
            {collections.length === 0 && (
              <p className="text-sm text-muted-foreground">Chưa có collection.</p>
            )}
            {collections.map((collection) => (
              <label key={collection.id} className="flex items-center gap-2 text-sm text-foreground">
                <Checkbox
                  checked={data.collection_ids.includes(collection.id)}
                  onCheckedChange={() => toggleCollection(collection.id)}
                />
                {collection.name}
              </label>
            ))}
          </div>
        </div>

        <div className="flex flex-wrap gap-2">
          <Button type="submit" disabled={processing}>
            Lưu
          </Button>
          <Button
            type="button"
            variant="outline"
            onClick={() => router.get(route("admin.reader.documents.show", document.id))}
          >
            Xem file
          </Button>
          <Button
            type="button"
            variant="outline"
            onClick={() => router.get(route("admin.reader.documents.index"))}
          >
            Hủy
          </Button>
        </div>
      </form>
    </PrivateLayout>
  );
};

export default EditPage;
