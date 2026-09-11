import { Button } from "@/ts/components/ui/button";
import { Checkbox } from "@/ts/components/ui/checkbox";
import { Input } from "@/ts/components/ui/input";
import { Label } from "@/ts/components/ui/label";
import { Textarea } from "@/ts/components/ui/textarea";
import PrivateLayout, { RootProps } from "@/ts/layouts/PrivateLayout";
import { router, useForm } from "@inertiajs/react";
import { useRoute } from "ziggy-js";
import ReadingDigestNav from "../../../components/ReadingDigestNav";
import type { RdSource, RdSubject } from "../../../types";

type Props = RootProps & {
  subject: RdSubject | null;
  sources: RdSource[];
};

const FormPage = ({ auth, subject, sources }: Props) => {
  const route = useRoute();
  const isEdit = Boolean(subject?.id);

  const { data, setData, post, put, processing, errors } = useForm({
    name: subject?.name ?? "",
    description: subject?.description ?? "",
    articles_per_digest: subject?.articles_per_digest ?? 5,
    enabled: subject?.enabled ?? true,
    source_ids: subject?.sources?.map((s) => s.id) ?? [],
  });

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    if (isEdit) {
      put(route("admin.reading-digest.subjects.update", subject!.id));
    } else {
      post(route("admin.reading-digest.subjects.store"));
    }
  };

  const toggleSource = (id: string) => {
    setData(
      "source_ids",
      data.source_ids.includes(id)
        ? data.source_ids.filter((s) => s !== id)
        : [...data.source_ids, id],
    );
  };

  return (
    <PrivateLayout auth={auth}>
      <ReadingDigestNav />
      <h1 className="text-2xl font-bold text-gray-100 mb-2">
        {isEdit ? "Sửa chủ đề" : "Tạo chủ đề"}
      </h1>
      <p className="text-sm text-muted-foreground mb-4">
        Một chủ đề gom các nguồn liên quan. Mỗi ngày hệ thống chọn vài bài hay nhất trong chủ đề này để gửi bạn.
      </p>
      <form onSubmit={submit} className="max-w-2xl space-y-4">
        <div>
          <Label>Tên chủ đề</Label>
          <Input value={data.name} onChange={(e) => setData("name", e.target.value)} />
          {errors.name && <p className="text-red-400 text-sm">{errors.name}</p>}
        </div>
        <div>
          <Label>Mô tả (tuỳ chọn)</Label>
          <Textarea value={data.description} onChange={(e) => setData("description", e.target.value)} />
        </div>
        <div>
          <Label>Số bài gửi mỗi ngày</Label>
          <Input type="number" min={1} max={20} value={data.articles_per_digest} onChange={(e) => setData("articles_per_digest", Number(e.target.value))} />
        </div>
        <div className="flex items-center gap-2">
          <Checkbox checked={data.enabled} onCheckedChange={(v) => setData("enabled", Boolean(v))} />
          <Label>Bật chủ đề</Label>
        </div>
        <div>
          <Label className="mb-2 block">Nguồn trong chủ đề</Label>
          <div className="space-y-2 border border-border rounded-md p-3">
            {sources.length === 0 && (
              <p className="text-sm text-muted-foreground">Chưa có nguồn nào — thêm ở mục Nguồn trước.</p>
            )}
            {sources.map((source) => (
              <label key={source.id} className="flex items-center gap-2 text-gray-300">
                <Checkbox
                  checked={data.source_ids.includes(source.id)}
                  onCheckedChange={() => toggleSource(source.id)}
                />
                {source.name}
              </label>
            ))}
          </div>
        </div>
        <div className="flex gap-2">
          <Button type="submit" disabled={processing}>Lưu</Button>
          <Button type="button" variant="outline" onClick={() => router.get(route("admin.reading-digest.subjects.index"))}>
            Hủy
          </Button>
        </div>
      </form>
    </PrivateLayout>
  );
};

export default FormPage;
