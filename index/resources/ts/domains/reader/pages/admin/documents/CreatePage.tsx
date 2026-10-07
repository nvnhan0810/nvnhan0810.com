import { Button } from "@/ts/components/ui/button";
import { Checkbox } from "@/ts/components/ui/checkbox";
import { Input } from "@/ts/components/ui/input";
import { Label } from "@/ts/components/ui/label";
import PrivateLayout, { type RootProps } from "@/ts/layouts/PrivateLayout";
import { router, useForm } from "@inertiajs/react";
import { useRoute } from "ziggy-js";
import ReaderNav from "../../../components/ReaderNav";
import type { ReaderCollection } from "../../../types";

type SourceMode = "upload" | "url";

type Props = RootProps & {
  collections: ReaderCollection[];
  maxPdfMb: number;
};

const CreatePage = ({ auth, collections, maxPdfMb }: Props) => {
  const route = useRoute();

  const { data, setData, post, processing, errors, progress } = useForm<{
    source: SourceMode;
    url: string;
    file: File | null;
    is_favorite: boolean;
    collection_ids: string[];
  }>({
    source: "upload",
    url: "",
    file: null,
    is_favorite: false,
    collection_ids: [],
  });

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    post(route("admin.reader.documents.store"), {
      forceFormData: true,
    });
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
      <h1 className="mb-2 text-2xl font-bold text-foreground">Thêm document</h1>
      <p className="mb-4 text-sm text-muted-foreground">
        Chỉ cần upload PDF hoặc dán link — tên và số trang lấy tự động. Upload tối đa{" "}
        {maxPdfMb} MB (file ~50 MB được hỗ trợ).
      </p>

      <form onSubmit={submit} className="max-w-2xl space-y-4">
        <div>
          <Label className="mb-2 block">Nguồn</Label>
          <div className="flex gap-4">
            <label className="flex items-center gap-2 text-sm">
              <input
                type="radio"
                name="source"
                checked={data.source === "upload"}
                onChange={() => setData("source", "upload")}
              />
              Upload file
            </label>
            <label className="flex items-center gap-2 text-sm">
              <input
                type="radio"
                name="source"
                checked={data.source === "url"}
                onChange={() => setData("source", "url")}
              />
              Kéo từ link
            </label>
          </div>
        </div>

        {data.source === "upload" ? (
          <div>
            <Label htmlFor="file">PDF file</Label>
            <Input
              id="file"
              type="file"
              accept="application/pdf,.pdf"
              onChange={(e) => setData("file", e.target.files?.[0] ?? null)}
            />
            {errors.file && <p className="mt-1 text-sm text-red-400">{errors.file}</p>}
            {progress && (
              <p className="mt-1 text-xs text-muted-foreground">Uploading… {progress.percentage}%</p>
            )}
          </div>
        ) : (
          <div>
            <Label htmlFor="url">PDF URL</Label>
            <Input
              id="url"
              type="url"
              value={data.url}
              onChange={(e) => setData("url", e.target.value)}
              placeholder="https://example.com/file.pdf"
            />
            {errors.url && <p className="mt-1 text-sm text-red-400">{errors.url}</p>}
          </div>
        )}

        <div className="flex items-center gap-2">
          <Checkbox
            checked={data.is_favorite}
            onCheckedChange={(v) => setData("is_favorite", Boolean(v))}
            id="is_favorite"
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

        <div className="flex gap-2">
          <Button type="submit" disabled={processing}>
            Tạo
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

export default CreatePage;
