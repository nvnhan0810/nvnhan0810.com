import { Button } from "@/ts/components/ui/button";
import { Checkbox } from "@/ts/components/ui/checkbox";
import { Input } from "@/ts/components/ui/input";
import { Label } from "@/ts/components/ui/label";
import PrivateLayout, { RootProps } from "@/ts/layouts/PrivateLayout";
import { router, useForm } from "@inertiajs/react";
import { useRoute } from "ziggy-js";
import ReadingDigestNav from "../../../components/ReadingDigestNav";
import type { RdSource, SourceTypeOption } from "../../../types";

type Props = RootProps & {
  source: RdSource | null;
  sourceTypes: SourceTypeOption[];
};

const FormPage = ({ auth, source, sourceTypes }: Props) => {
  const route = useRoute();
  const isEdit = Boolean(source?.id);

  const { data, setData, post, put, processing } = useForm({
    name: source?.name ?? "",
    type: source?.type ?? "rss",
    url: source?.url ?? "",
    enabled: source?.enabled ?? true,
    config: source?.config ?? { query: "", tags: "story" },
  });

  const selectedType = sourceTypes.find((t) => t.value === data.type);

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    if (isEdit) {
      put(route("admin.reading-digest.sources.update", source!.id));
    } else {
      post(route("admin.reading-digest.sources.store"));
    }
  };

  return (
    <PrivateLayout auth={auth}>
      <ReadingDigestNav />
      <h1 className="text-2xl font-bold text-gray-100 mb-2">{isEdit ? "Sửa nguồn" : "Thêm nguồn"}</h1>
      <p className="text-sm text-muted-foreground mb-4 max-w-2xl">
        Website hoặc RSS feed bạn tin cậy. Sau khi lưu, vào <strong>Chủ đề</strong> và tick chọn nguồn này —
        nếu không gắn chủ đề thì digest sẽ không có bài từ nguồn đó.
      </p>
      <form onSubmit={submit} className="max-w-2xl space-y-4">
        <div>
          <Label>Tên hiển thị</Label>
          <Input
            placeholder="VD: Tuổi Trẻ — Công nghệ"
            value={data.name}
            onChange={(e) => setData("name", e.target.value)}
          />
        </div>
        <div>
          <Label>Cách lấy bài</Label>
          <select
            className="w-full border border-border rounded-md bg-background px-3 py-2"
            value={data.type}
            onChange={(e) => setData("type", e.target.value)}
          >
            {sourceTypes.map((t) => (
              <option key={t.value} value={t.value}>{t.label}</option>
            ))}
          </select>
          {selectedType?.description && (
            <p className="text-xs text-muted-foreground mt-1">{selectedType.description}</p>
          )}
        </div>
        <div>
          <Label>URL feed / site</Label>
          <Input
            placeholder="https://example.com/rss.xml"
            value={data.url}
            onChange={(e) => setData("url", e.target.value)}
          />
        </div>
        {data.type === "hn_algolia" && (
          <div className="grid grid-cols-2 gap-4">
            <div>
              <Label>HN Query</Label>
              <Input
                value={(data.config as { query?: string }).query ?? ""}
                onChange={(e) => setData("config", { ...data.config, query: e.target.value })}
              />
            </div>
            <div>
              <Label>Tags</Label>
              <Input
                value={(data.config as { tags?: string }).tags ?? "story"}
                onChange={(e) => setData("config", { ...data.config, tags: e.target.value })}
              />
            </div>
          </div>
        )}
        <div className="flex items-center gap-2">
          <Checkbox checked={data.enabled} onCheckedChange={(v) => setData("enabled", Boolean(v))} />
          <Label>Bật — lấy bài khi chạy digest hằng ngày</Label>
        </div>
        <div className="flex gap-2">
          <Button type="submit" disabled={processing}>Lưu</Button>
          <Button type="button" variant="outline" onClick={() => router.get(route("admin.reading-digest.sources.index"))}>
            Hủy
          </Button>
        </div>
      </form>
    </PrivateLayout>
  );
};

export default FormPage;
