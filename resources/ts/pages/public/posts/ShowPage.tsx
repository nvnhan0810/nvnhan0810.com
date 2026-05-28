import PostDetail from "@/ts/components/posts/PostDetail";
import PublicLayout, { RootProps } from "@/ts/layouts/PublicLayout";
import { useTranslation } from "@/ts/providers/i18n-provider";
import { Post } from "@/ts/types/post";
import type { Series } from "@/ts/types/series";
import { cn } from "@/ts/utils";
import { Link } from "@inertiajs/react";
import { ArrowLeft } from "lucide-react";
import { useRoute } from "ziggy-js";

type Props = RootProps & {
  post: Post;
  series: Series[];
};

const PostDetailPage = ({ post, auth, locale, series = [] }: Props) => {
  const route = useRoute();
  const { t } = useTranslation();
  const sourceUrl = post.source_url?.trim() ?? "";

  return (
    <PublicLayout auth={auth} locale={locale}>
      <Link
        href={route("posts.index")}
        className="mb-8 inline-flex items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-emerald-500"
      >
        <ArrowLeft className="h-4 w-4" />
        {t("blog.backToBlog")}
      </Link>

      <div className="grid grid-cols-1 gap-8 lg:grid-cols-12">
        <div
          className={cn("col-span-1", {
            "lg:col-span-8": series.length > 0,
            "lg:col-span-10 lg:col-start-2": series.length === 0,
          })}
        >
          <article className="rounded-xl border border-border bg-card p-6 md:p-10">
            <p className="mb-3 text-sm font-medium uppercase tracking-widest text-emerald-500">
              {t("blog.article")}
            </p>
            {sourceUrl !== "" && (
              <div className="mb-4 rounded-md border border-emerald-600/30 bg-emerald-600/10 px-3 py-2 text-sm text-emerald-400">
                {t("blog.sourceOriginal")}{" "}
                <a
                  href={sourceUrl}
                  target="_blank"
                  rel="noreferrer"
                  className="font-medium underline"
                >
                  {sourceUrl}
                </a>
              </div>
            )}
            <PostDetail post={post} useTagLink={true} />
          </article>
        </div>

        {series.length > 0 && (
          <aside className="col-span-1 space-y-6 lg:col-span-4">
            <div className="sticky top-20">
              <h2 className="mb-4 border-b border-border pb-2 text-lg font-bold">
                {t("blog.series")}
              </h2>
              <div className="flex flex-col gap-5">
                {series.map((item) => (
                  <div
                    key={item.id}
                    className="overflow-hidden rounded-xl border border-border bg-card"
                  >
                    <div className="border-b border-border bg-muted/50 px-4 py-3 font-semibold">
                      {item.name}
                    </div>
                    <ul className="divide-y divide-border">
                      {item.posts.map((postItem) => (
                        <li
                          key={postItem.id}
                          className={cn("transition-colors", {
                            "bg-emerald-600/10": postItem.id === post.id,
                            "hover:bg-muted/50": postItem.id !== post.id,
                          })}
                        >
                          {postItem.id !== post.id ? (
                            <Link
                              href={route("posts.show", {
                                slug: postItem.slug,
                              })}
                              className="block px-4 py-3 text-sm text-muted-foreground transition-colors hover:text-emerald-500"
                            >
                              {postItem.title}
                            </Link>
                          ) : (
                            <div className="flex items-center gap-2 px-4 py-3 text-sm font-medium text-emerald-500">
                              <span className="h-1.5 w-1.5 flex-shrink-0 rounded-full bg-emerald-500" />
                              {postItem.title}
                            </div>
                          )}
                        </li>
                      ))}
                    </ul>
                  </div>
                ))}
              </div>
            </div>
          </aside>
        )}
      </div>
    </PublicLayout>
  );
};

export default PostDetailPage;
