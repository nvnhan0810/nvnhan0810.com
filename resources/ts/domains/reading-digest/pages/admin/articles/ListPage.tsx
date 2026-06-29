import PaginationBar from "@/ts/components/common/PaginationBar";
import PrivateLayout, { RootProps } from "@/ts/layouts/PrivateLayout";
import { Pagination } from "@/ts/types/common";
import { router } from "@inertiajs/react";
import { useRoute } from "ziggy-js";
import ReadingDigestNav from "../../../components/ReadingDigestNav";
import type { RdArticle } from "../../../types";

type Props = RootProps & {
  articles: Pagination<RdArticle>;
  filters: { search?: string };
};

const ListPage = ({ auth, articles }: Props) => {
  const route = useRoute();

  const toggle = (id: string, field: "force_include" | "force_exclude", value: boolean) => {
    router.patch(route("admin.reading-digest.articles.update", id), { [field]: value });
  };

  return (
    <PrivateLayout auth={auth}>
      <ReadingDigestNav />
      <h1 className="text-2xl font-bold text-gray-100 mb-4">Article Inbox</h1>
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead className="bg-gray-100 text-gray-900">
            <tr>
              <th className="px-3 py-2 border">Title</th>
              <th className="px-3 py-2 border">Source</th>
              <th className="px-3 py-2 border">Published</th>
              <th className="px-3 py-2 border">Include</th>
              <th className="px-3 py-2 border">Exclude</th>
            </tr>
          </thead>
          <tbody className="text-gray-300">
            {articles.data.map((article) => (
              <tr key={article.id}>
                <td className="px-3 py-2 border">
                  <a href={article.url} target="_blank" rel="noreferrer" className="text-blue-400">{article.title}</a>
                </td>
                <td className="px-3 py-2 border">{article.source?.name}</td>
                <td className="px-3 py-2 border">{article.published_at?.slice(0, 10) ?? "—"}</td>
                <td className="px-3 py-2 border text-center">
                  <input type="checkbox" checked={article.force_include} onChange={(e) => toggle(article.id, "force_include", e.target.checked)} />
                </td>
                <td className="px-3 py-2 border text-center">
                  <input type="checkbox" checked={article.force_exclude} onChange={(e) => toggle(article.id, "force_exclude", e.target.checked)} />
                </td>
              </tr>
            ))}
          </tbody>
          <tfoot>
            <tr>
              <td colSpan={5} className="p-4">
                <PaginationBar pagination={articles} />
              </td>
            </tr>
          </tfoot>
        </table>
      </div>
    </PrivateLayout>
  );
};

export default ListPage;
