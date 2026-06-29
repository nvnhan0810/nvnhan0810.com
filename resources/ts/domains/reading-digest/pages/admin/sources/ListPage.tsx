import { Button } from "@/ts/components/ui/button";
import PrivateLayout, { RootProps } from "@/ts/layouts/PrivateLayout";
import { router } from "@inertiajs/react";
import { Plus } from "lucide-react";
import { useRoute } from "ziggy-js";
import ReadingDigestNav from "../../../components/ReadingDigestNav";
import type { RdSource } from "../../../types";

type Props = RootProps & {
  sources: RdSource[];
};

const ListPage = ({ auth, sources }: Props) => {
  const route = useRoute();

  const fetchNow = (id: string) => {
    router.post(route("admin.reading-digest.sources.fetch", id));
  };

  return (
    <PrivateLayout auth={auth}>
      <ReadingDigestNav />
      <div className="flex justify-between items-center mb-2">
        <h1 className="text-2xl font-bold text-gray-100">Trusted Sources</h1>
        <Button variant="outline" onClick={() => router.get(route("admin.reading-digest.sources.create"))}>
          <Plus className="w-4 h-4 mr-1" /> Add source
        </Button>
      </div>
      <p className="text-sm text-muted-foreground mb-4">
        Websites and feeds you add in admin. Fetched once daily at digest time (not on a background interval).
      </p>
      <table className="w-full text-sm">
        <thead className="bg-gray-100 text-gray-900">
          <tr>
            <th className="px-3 py-2 border">Name</th>
            <th className="px-3 py-2 border">Feed URL</th>
            <th className="px-3 py-2 border">Last fetch</th>
            <th className="px-3 py-2 border">Status</th>
            <th className="px-3 py-2 border">Actions</th>
          </tr>
        </thead>
        <tbody className="text-gray-300">
          {sources.length === 0 && (
            <tr>
              <td colSpan={5} className="px-3 py-6 border text-center text-muted-foreground">
                No sources yet. Add Tuổi Trẻ, Thanh Niên, dev.to, or another RSS feed.
              </td>
            </tr>
          )}
          {sources.map((source) => (
            <tr key={source.id}>
              <td className="px-3 py-2 border">
                <div>{source.name}</div>
                {!source.enabled && <span className="text-xs text-amber-400">disabled</span>}
              </td>
              <td className="px-3 py-2 border max-w-xs truncate">
                <a href={source.url} target="_blank" rel="noreferrer" className="text-blue-400">{source.url}</a>
              </td>
              <td className="px-3 py-2 border">{source.last_fetch_at?.slice(0, 16) ?? "—"}</td>
              <td className="px-3 py-2 border">
                {source.last_fetch_status ?? "—"}
                {source.last_fetch_error && (
                  <div className="text-xs text-red-400 truncate max-w-[12rem]" title={source.last_fetch_error}>
                    {source.last_fetch_error}
                  </div>
                )}
              </td>
              <td className="px-3 py-2 border space-x-2">
                <a href={route("admin.reading-digest.sources.edit", source.id)} className="text-blue-400">Edit</a>
                <button type="button" className="text-green-400" onClick={() => fetchNow(source.id)}>Fetch now</button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </PrivateLayout>
  );
};

export default ListPage;
