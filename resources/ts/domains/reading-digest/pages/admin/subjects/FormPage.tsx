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
    slug: subject?.slug ?? "",
    description: subject?.description ?? "",
    articles_per_digest: subject?.articles_per_digest ?? 5,
    max_age_days: subject?.max_age_days ?? 7,
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
        : [...data.source_ids, id]
    );
  };

  return (
    <PrivateLayout auth={auth}>
      <ReadingDigestNav />
      <h1 className="text-2xl font-bold text-gray-100 mb-4">
        {isEdit ? "Edit Subject" : "Create Subject"}
      </h1>
      <form onSubmit={submit} className="max-w-2xl space-y-4">
        <div>
          <Label>Name</Label>
          <Input value={data.name} onChange={(e) => setData("name", e.target.value)} />
          {errors.name && <p className="text-red-400 text-sm">{errors.name}</p>}
        </div>
        <div>
          <Label>Slug</Label>
          <Input value={data.slug} onChange={(e) => setData("slug", e.target.value)} />
        </div>
        <div>
          <Label>Description</Label>
          <Textarea value={data.description} onChange={(e) => setData("description", e.target.value)} />
        </div>
        <div className="grid grid-cols-2 gap-4">
          <div>
            <Label>Articles per digest</Label>
            <Input type="number" value={data.articles_per_digest} onChange={(e) => setData("articles_per_digest", Number(e.target.value))} />
          </div>
          <div>
            <Label>Max age (days)</Label>
            <Input type="number" value={data.max_age_days} onChange={(e) => setData("max_age_days", Number(e.target.value))} />
          </div>
        </div>
        <div className="flex items-center gap-2">
          <Checkbox checked={data.enabled} onCheckedChange={(v) => setData("enabled", Boolean(v))} />
          <Label>Enabled</Label>
        </div>
        <div>
          <Label className="mb-2 block">Sources</Label>
          <div className="space-y-2 border border-border rounded-md p-3">
            {sources.map((source) => (
              <label key={source.id} className="flex items-center gap-2 text-gray-300">
                <Checkbox
                  checked={data.source_ids.includes(source.id)}
                  onCheckedChange={() => toggleSource(source.id)}
                />
                {source.name} <span className="text-muted-foreground">({source.type})</span>
              </label>
            ))}
          </div>
        </div>
        <div className="flex gap-2">
          <Button type="submit" disabled={processing}>Save</Button>
          <Button type="button" variant="outline" onClick={() => router.get(route("admin.reading-digest.subjects.index"))}>
            Cancel
          </Button>
        </div>
      </form>
    </PrivateLayout>
  );
};

export default FormPage;
