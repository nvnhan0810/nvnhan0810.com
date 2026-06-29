import { Button } from "@/ts/components/ui/button";
import PrivateLayout, { RootProps } from "@/ts/layouts/PrivateLayout";
import { router } from "@inertiajs/react";
import { Plus } from "lucide-react";
import { useRoute } from "ziggy-js";
import ReadingDigestNav from "../../../components/ReadingDigestNav";
import type { RdSource, SourceTypeOption } from "../../../types";

type Props = RootProps & {
  sources: RdSource[];
  sourceTypes: SourceTypeOption[];
};

const ListPage = ({ auth, sources }: Props) => {
  const route = useRoute();

  const fetchNow = (id: string) => {
    router.post(route("admin.reading-digest.sources.fetch", id));
  };

  return (
    <PrivateLayout auth={auth}>
      <ReadingDigestNav />
      <div className="flex justify-between items-center mb-4">
        <h1 className="text-2xl font-bold text-gray-100">Sources</h1>
        <Button variant="outline" onClick={() => router.get(route("admin.reading-digest.sources.create"))}>
          <Plus className="w-4 h-4 mr-1" /> New
        </Button>
      </div>
      <table className="w-full text-sm">
        <thead className="bg-gray-100 text-gray-900">
          <tr>
            <th className="px-3 py-2 border">Name</th>
            <th className="px-3 py-2 border">Type</th>
            <th className="px-3 py-2 border">Last fetch</th>
            <th className="px-3 py-2 border">Status</th>
            <th className="px-3 py-2 border">Actions</th>
          </tr>
        </thead>
        <tbody className="text-gray-300">
          {sources.map((source) => (
            <tr key={source.id}>
              <td className="px-3 py-2 border">{source.name}</td>
              <td className="px-3 py-2 border">{source.type}</td>
              <td className="px-3 py-2 border">{source.last_fetch_at ?? "—"}</td>
              <td className="px-3 py-2 border">{source.last_fetch_status ?? "—"}</td>
              <td className="px-3 py-2 border space-x-2">
                <a href={route("admin.reading-digest.sources.edit", source.id)} className="text-blue-400">Edit</a>
                <button type="button" className="text-green-400" onClick={() => fetchNow(source.id)}>Fetch</button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </PrivateLayout>
  );
};

export default ListPage;
