import PaginationBar from "@/ts/components/common/PaginationBar";
import PrivateLayout, { RootProps } from "@/ts/layouts/PrivateLayout";
import { Pagination } from "@/ts/types/common";
import ReadingDigestNav from "../../../components/ReadingDigestNav";
import type { RdArticle } from "../../../types";

type Props = RootProps & {
  articles: Pagination<RdArticle>;
  filters: { search?: string };
};

const ListPage = ({ auth, articles }: Props) => {
  return (
    <PrivateLayout auth={auth}>
      <ReadingDigestNav />
      <h1 className="text-2xl font-bold text-gray-100 mb-2">Hộp thư bài viết</h1>
      <p className="text-sm text-muted-foreground mb-4">
        Bài đã lấy từ các nguồn — chỉ hiện tóm tắt. Bấm tiêu đề để đọc trên site gốc.
      </p>
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead className="bg-gray-100 text-gray-900">
            <tr>
              <th className="px-3 py-2 border">Tiêu đề</th>
              <th className="px-3 py-2 border">Tóm tắt</th>
              <th className="px-3 py-2 border">Nguồn</th>
              <th className="px-3 py-2 border">Ngày đăng</th>
            </tr>
          </thead>
          <tbody className="text-gray-300">
            {articles.data.map((article) => (
              <tr key={article.id}>
                <td className="px-3 py-2 border align-top max-w-xs">
                  <a href={article.url} target="_blank" rel="noreferrer" className="text-blue-400 hover:underline">
                    {article.title}
                  </a>
                </td>
                <td className="px-3 py-2 border align-top max-w-md text-muted-foreground line-clamp-2">
                  {article.summary ?? "—"}
                </td>
                <td className="px-3 py-2 border align-top">{article.source?.name}</td>
                <td className="px-3 py-2 border align-top">{article.published_at?.slice(0, 10) ?? "—"}</td>
              </tr>
            ))}
          </tbody>
          <tfoot>
            <tr>
              <td colSpan={4} className="p-4">
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
