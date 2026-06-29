import { Button } from "@/ts/components/ui/button";
import { Checkbox } from "@/ts/components/ui/checkbox";
import { Input } from "@/ts/components/ui/input";
import { Label } from "@/ts/components/ui/label";
import PrivateLayout, { RootProps } from "@/ts/layouts/PrivateLayout";
import { router, useForm } from "@inertiajs/react";
import { useRoute } from "ziggy-js";
import ReadingDigestNav from "../../../components/ReadingDigestNav";
import type { RdSource, RdTaxonomyNode, SourceTypeOption } from "../../../types";

type Props = RootProps & {
  source: RdSource | null;
  sourceTypes: SourceTypeOption[];
  taxonomyNodes: RdTaxonomyNode[];
};

const FormPage = ({ auth, source, sourceTypes, taxonomyNodes }: Props) => {
  const route = useRoute();
  const isEdit = Boolean(source?.id);

  const { data, setData, post, put, processing } = useForm({
    name: source?.name ?? "",
    type: source?.type ?? "rss",
    url: source?.url ?? "",
    fetch_interval_minutes: source?.fetch_interval_minutes ?? 60,
    enabled: source?.enabled ?? true,
    config: source?.config ?? { query: "", tags: "story" },
    tag_mappings: source?.tag_mappings?.map((m) => ({
      raw_tag: m.raw_tag,
      taxonomy_node_id: m.taxonomy_node_id,
    })) ?? [{ raw_tag: "", taxonomy_node_id: "" }],
  });

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
      <h1 className="text-2xl font-bold text-gray-100 mb-4">{isEdit ? "Edit Source" : "Create Source"}</h1>
      <form onSubmit={submit} className="max-w-2xl space-y-4">
        <div>
          <Label>Name</Label>
          <Input value={data.name} onChange={(e) => setData("name", e.target.value)} />
        </div>
        <div>
          <Label>Type</Label>
          <select
            className="w-full border border-border rounded-md bg-background px-3 py-2"
            value={data.type}
            onChange={(e) => setData("type", e.target.value)}
          >
            {sourceTypes.map((t) => (
              <option key={t.value} value={t.value}>{t.label}</option>
            ))}
          </select>
        </div>
        <div>
          <Label>URL</Label>
          <Input value={data.url} onChange={(e) => setData("url", e.target.value)} />
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
          <Label>Enabled</Label>
        </div>
        <div>
          <Label className="mb-2 block">Tag mappings</Label>
          {data.tag_mappings.map((mapping, index) => (
            <div key={index} className="flex gap-2 mb-2">
              <Input
                placeholder="raw tag"
                value={mapping.raw_tag}
                onChange={(e) => {
                  const next = [...data.tag_mappings];
                  next[index] = { ...next[index], raw_tag: e.target.value };
                  setData("tag_mappings", next);
                }}
              />
              <select
                className="flex-1 border border-border rounded-md bg-background px-2"
                value={mapping.taxonomy_node_id}
                onChange={(e) => {
                  const next = [...data.tag_mappings];
                  next[index] = { ...next[index], taxonomy_node_id: e.target.value };
                  setData("tag_mappings", next);
                }}
              >
                <option value="">— taxonomy —</option>
                {taxonomyNodes.map((node) => (
                  <option key={node.id} value={node.id}>{node.path}</option>
                ))}
              </select>
            </div>
          ))}
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={() => setData("tag_mappings", [...data.tag_mappings, { raw_tag: "", taxonomy_node_id: "" }])}
          >
            Add mapping
          </Button>
        </div>
        <div className="flex gap-2">
          <Button type="submit" disabled={processing}>Save</Button>
          <Button type="button" variant="outline" onClick={() => router.get(route("admin.reading-digest.sources.index"))}>
            Cancel
          </Button>
        </div>
      </form>
    </PrivateLayout>
  );
};

export default FormPage;
