import { Button } from "@/ts/components/ui/button";
import { Input } from "@/ts/components/ui/input";
import { Label } from "@/ts/components/ui/label";
import PrivateLayout, { RootProps } from "@/ts/layouts/PrivateLayout";
import { useForm } from "@inertiajs/react";
import { useRoute } from "ziggy-js";
import ReadingDigestNav from "../../../components/ReadingDigestNav";
import type { RdTaxonomyNode } from "../../../types";

type Props = RootProps & {
  nodes: RdTaxonomyNode[];
};

const TaxonomyPage = ({ auth, nodes }: Props) => {
  const route = useRoute();
  const { data, setData, post, processing } = useForm({
    label: "",
    slug: "",
    parent_id: "",
  });

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    post(route("admin.reading-digest.taxonomy.store"), {
      onSuccess: () => setData({ label: "", slug: "", parent_id: "" }),
    });
  };

  return (
    <PrivateLayout auth={auth}>
      <ReadingDigestNav />
      <h1 className="text-2xl font-bold text-gray-100 mb-4">Taxonomy</h1>
      <form onSubmit={submit} className="max-w-xl grid grid-cols-3 gap-2 mb-6">
        <div>
          <Label>Label</Label>
          <Input value={data.label} onChange={(e) => setData("label", e.target.value)} />
        </div>
        <div>
          <Label>Slug</Label>
          <Input value={data.slug} onChange={(e) => setData("slug", e.target.value)} />
        </div>
        <div>
          <Label>Parent</Label>
          <select
            className="w-full border border-border rounded-md bg-background px-2 py-2"
            value={data.parent_id}
            onChange={(e) => setData("parent_id", e.target.value)}
          >
            <option value="">— root —</option>
            {nodes.map((node) => (
              <option key={node.id} value={node.id}>{node.path}</option>
            ))}
          </select>
        </div>
        <div className="col-span-3">
          <Button type="submit" disabled={processing}>Add node</Button>
        </div>
      </form>
      <ul className="text-sm text-gray-300 space-y-1 font-mono">
        {nodes.map((node) => (
          <li key={node.id}>{node.path} — {node.label}</li>
        ))}
      </ul>
    </PrivateLayout>
  );
};

export default TaxonomyPage;
