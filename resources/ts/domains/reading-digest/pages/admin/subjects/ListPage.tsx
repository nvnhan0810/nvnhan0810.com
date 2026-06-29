import { Button } from "@/ts/components/ui/button";
import PrivateLayout, { RootProps } from "@/ts/layouts/PrivateLayout";
import { router } from "@inertiajs/react";
import { Plus } from "lucide-react";
import { useRoute } from "ziggy-js";
import ReadingDigestNav from "../../../components/ReadingDigestNav";
import type { RdSubject } from "../../../types";

type Props = RootProps & {
  subjects: RdSubject[];
};

const ListPage = ({ auth, subjects }: Props) => {
  const route = useRoute();

  return (
    <PrivateLayout auth={auth}>
      <ReadingDigestNav />
      <div className="flex justify-between items-center mb-4">
        <h1 className="text-2xl font-bold text-gray-100">Reading Digest — Subjects</h1>
        <Button variant="outline" onClick={() => router.get(route("admin.reading-digest.subjects.create"))}>
          <Plus className="w-4 h-4 mr-1" /> New
        </Button>
      </div>
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead className="bg-gray-100 text-gray-900">
            <tr>
              <th className="px-3 py-2 border">Name</th>
              <th className="px-3 py-2 border">Sources</th>
              <th className="px-3 py-2 border">Per digest</th>
              <th className="px-3 py-2 border">Enabled</th>
              <th className="px-3 py-2 border">Actions</th>
            </tr>
          </thead>
          <tbody className="text-gray-300">
            {subjects.map((subject) => (
              <tr key={subject.id}>
                <td className="px-3 py-2 border">{subject.name}</td>
                <td className="px-3 py-2 border text-center">{subject.sources_count ?? 0}</td>
                <td className="px-3 py-2 border text-center">{subject.articles_per_digest}</td>
                <td className="px-3 py-2 border text-center">{subject.enabled ? "Yes" : "No"}</td>
                <td className="px-3 py-2 border text-center">
                  <a href={route("admin.reading-digest.subjects.edit", subject.id)} className="text-blue-400">Edit</a>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </PrivateLayout>
  );
};

export default ListPage;
