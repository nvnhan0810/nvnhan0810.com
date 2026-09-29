import { Button } from "@/ts/components/ui/button";
import { Input } from "@/ts/components/ui/input";
import { Label } from "@/ts/components/ui/label";
import PrivateLayout, { RootProps } from "@/ts/layouts/PrivateLayout";
import { router, useForm, usePage } from "@inertiajs/react";
import { useRoute } from "ziggy-js";
import ReadingDigestNav from "../../../components/ReadingDigestNav";
import type { RdTaxonomyNode } from "../../../types";

type Props = RootProps & {
  nodes: RdTaxonomyNode[];
  favoriteTopics: Record<string, number>;
  ignoredTaxonomyIds: string[];
  ignoredTopics: string[];
};

const DEFAULT_PRIORITY_WEIGHT = 8;

const TaxonomyPage = ({
  auth,
  nodes,
  favoriteTopics,
  ignoredTaxonomyIds,
  ignoredTopics,
}: Props) => {
  const route = useRoute();
  const { flash } = usePage<{ flash?: { success?: string } }>().props;

  const createForm = useForm({
    label: "",
    slug: "",
    parent_id: "",
  });

  const priorityForm = useForm({
    taxonomy_node_id: "",
    weight: String(DEFAULT_PRIORITY_WEIGHT),
  });

  const restrictedForm = useForm({
    taxonomy_node_id: "",
  });

  const nodeByPath = new Map(nodes.map((node) => [node.path, node]));
  const nodeById = new Map(nodes.map((node) => [node.id, node]));

  const priorityEntries = Object.entries(favoriteTopics).sort(
    ([, a], [, b]) => b - a,
  );

  const restrictedNodes = ignoredTaxonomyIds
    .map((id) => nodeById.get(id))
    .filter((node): node is RdTaxonomyNode => node !== undefined);

  const orphanIgnoredTopics = ignoredTopics.filter(
    (path) => !restrictedNodes.some((node) => node.path === path),
  );

  const submitCreate = (e: React.FormEvent) => {
    e.preventDefault();
    createForm.post(route("admin.reading-digest.taxonomy.store"), {
      onSuccess: () => createForm.setData({ label: "", slug: "", parent_id: "" }),
    });
  };

  const submitPriority = (e: React.FormEvent) => {
    e.preventDefault();
    priorityForm.post(route("admin.reading-digest.taxonomy.priority.store"), {
      onSuccess: () =>
        priorityForm.setData({
          taxonomy_node_id: "",
          weight: String(DEFAULT_PRIORITY_WEIGHT),
        }),
    });
  };

  const submitRestricted = (e: React.FormEvent) => {
    e.preventDefault();
    restrictedForm.post(route("admin.reading-digest.taxonomy.restricted.store"), {
      onSuccess: () => restrictedForm.setData({ taxonomy_node_id: "" }),
    });
  };

  const removePriority = (path: string) => {
    router.delete(route("admin.reading-digest.taxonomy.priority.destroy"), {
      data: { path },
      preserveScroll: true,
    });
  };

  const removeRestricted = (payload: { taxonomy_node_id?: string; path?: string }) => {
    router.delete(route("admin.reading-digest.taxonomy.restricted.destroy"), {
      data: payload,
      preserveScroll: true,
    });
  };

  return (
    <PrivateLayout auth={auth}>
      <ReadingDigestNav />
      <h1 className="text-2xl font-bold text-foreground mb-2">Tag / Category</h1>
      <p className="text-sm text-muted-foreground mb-4 max-w-2xl">
        Quản lý taxonomy gắn với bài viết. Tag ưu tiên được boost khi xếp digest;
        tag hạn chế sẽ loại bài khỏi danh sách lấy bài.
      </p>
      {flash?.success && <p className="text-green-400 text-sm mb-4">{flash.success}</p>}

      <section className="mb-8 max-w-3xl">
        <h2 className="text-lg font-semibold text-foreground mb-3">Tag ưu tiên</h2>
        <form onSubmit={submitPriority} className="grid grid-cols-[1fr_100px_auto] gap-2 mb-4">
          <div>
            <Label>Tag</Label>
            <select
              className="w-full border border-border rounded-md bg-background px-2 py-2 text-sm"
              value={priorityForm.data.taxonomy_node_id}
              onChange={(e) => priorityForm.setData("taxonomy_node_id", e.target.value)}
              required
            >
              <option value="">— chọn tag —</option>
              {nodes.map((node) => (
                <option key={node.id} value={node.id}>
                  {node.path} — {node.label}
                </option>
              ))}
            </select>
          </div>
          <div>
            <Label>Weight</Label>
            <Input
              type="number"
              min={1}
              max={100}
              value={priorityForm.data.weight}
              onChange={(e) => priorityForm.setData("weight", e.target.value)}
            />
          </div>
          <div className="flex items-end">
            <Button type="submit" disabled={priorityForm.processing}>
              Thêm ưu tiên
            </Button>
          </div>
        </form>
        <ul className="space-y-2 text-sm">
          {priorityEntries.map(([path, weight]) => (
            <li
              key={path}
              className="flex items-center justify-between gap-3 rounded-md border border-border bg-card px-3 py-2"
            >
              <span className="text-foreground">
                <span className="font-medium">{nodeByPath.get(path)?.label ?? path}</span>
                <span className="text-muted-foreground ml-2 font-mono text-xs">{path}</span>
                <span className="text-muted-foreground ml-2">· weight {weight}</span>
              </span>
              <Button type="button" variant="outline" size="sm" onClick={() => removePriority(path)}>
                Bỏ
              </Button>
            </li>
          ))}
          {priorityEntries.length === 0 && (
            <li className="text-muted-foreground">Chưa có tag ưu tiên.</li>
          )}
        </ul>
      </section>

      <section className="mb-8 max-w-3xl">
        <h2 className="text-lg font-semibold text-foreground mb-3">Tag hạn chế</h2>
        <p className="text-xs text-muted-foreground mb-3">
          Bài gắn tag này sẽ bị loại khi lấy / xếp digest.
        </p>
        <form onSubmit={submitRestricted} className="grid grid-cols-[1fr_auto] gap-2 mb-4">
          <div>
            <Label>Tag</Label>
            <select
              className="w-full border border-border rounded-md bg-background px-2 py-2 text-sm"
              value={restrictedForm.data.taxonomy_node_id}
              onChange={(e) => restrictedForm.setData("taxonomy_node_id", e.target.value)}
              required
            >
              <option value="">— chọn tag —</option>
              {nodes.map((node) => (
                <option key={node.id} value={node.id}>
                  {node.path} — {node.label}
                </option>
              ))}
            </select>
          </div>
          <div className="flex items-end">
            <Button type="submit" variant="secondary" disabled={restrictedForm.processing}>
              Thêm hạn chế
            </Button>
          </div>
        </form>
        <ul className="space-y-2 text-sm">
          {restrictedNodes.map((node) => (
            <li
              key={node.id}
              className="flex items-center justify-between gap-3 rounded-md border border-border bg-card px-3 py-2"
            >
              <span className="text-foreground">
                <span className="font-medium">{node.label}</span>
                <span className="text-muted-foreground ml-2 font-mono text-xs">{node.path}</span>
              </span>
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => removeRestricted({ taxonomy_node_id: node.id })}
              >
                Bỏ
              </Button>
            </li>
          ))}
          {orphanIgnoredTopics.map((path) => (
            <li
              key={path}
              className="flex items-center justify-between gap-3 rounded-md border border-border bg-card px-3 py-2"
            >
              <span className="text-foreground font-mono text-xs">{path}</span>
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => removeRestricted({ path })}
              >
                Bỏ
              </Button>
            </li>
          ))}
          {restrictedNodes.length === 0 && orphanIgnoredTopics.length === 0 && (
            <li className="text-muted-foreground">Chưa có tag hạn chế.</li>
          )}
        </ul>
      </section>

      <section className="mb-8 max-w-3xl">
        <h2 className="text-lg font-semibold text-foreground mb-3">Thêm tag / category</h2>
        <form onSubmit={submitCreate} className="grid grid-cols-3 gap-2 mb-6">
          <div>
            <Label>Label</Label>
            <Input
              value={createForm.data.label}
              onChange={(e) => createForm.setData("label", e.target.value)}
              required
            />
          </div>
          <div>
            <Label>Slug</Label>
            <Input
              value={createForm.data.slug}
              onChange={(e) => createForm.setData("slug", e.target.value)}
              required
            />
          </div>
          <div>
            <Label>Parent</Label>
            <select
              className="w-full border border-border rounded-md bg-background px-2 py-2 text-sm"
              value={createForm.data.parent_id}
              onChange={(e) => createForm.setData("parent_id", e.target.value)}
            >
              <option value="">— root —</option>
              {nodes.map((node) => (
                <option key={node.id} value={node.id}>
                  {node.path}
                </option>
              ))}
            </select>
          </div>
          <div className="col-span-3">
            <Button type="submit" disabled={createForm.processing}>
              Thêm node
            </Button>
          </div>
        </form>
        <h3 className="text-sm font-semibold text-foreground mb-2">Tất cả taxonomy</h3>
        <ul className="text-sm text-foreground space-y-1 font-mono">
          {nodes.map((node) => {
            const isPriority = Object.prototype.hasOwnProperty.call(favoriteTopics, node.path);
            const isRestricted = ignoredTaxonomyIds.includes(node.id);
            return (
              <li key={node.id} className="flex flex-wrap items-center gap-2">
                <span>
                  {node.path} — {node.label}
                </span>
                {isPriority && (
                  <span className="rounded bg-emerald-600/20 px-1.5 py-0.5 text-[10px] uppercase tracking-wide text-emerald-400">
                    ưu tiên
                  </span>
                )}
                {isRestricted && (
                  <span className="rounded bg-red-600/20 px-1.5 py-0.5 text-[10px] uppercase tracking-wide text-red-400">
                    hạn chế
                  </span>
                )}
              </li>
            );
          })}
          {nodes.length === 0 && <li className="text-muted-foreground font-sans">Chưa có taxonomy node.</li>}
        </ul>
      </section>
    </PrivateLayout>
  );
};

export default TaxonomyPage;
