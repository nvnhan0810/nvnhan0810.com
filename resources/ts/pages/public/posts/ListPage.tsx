import PaginationBar from "@/ts/components/common/PaginationBar";
import SeoHead from "@/ts/components/common/SeoHead";
import PostListItem from "@/ts/components/posts/PostListItem";
import SearchForm from "@/ts/components/posts/SearchForm";
import TagBadge from "@/ts/components/tags/TagBadge";
import PublicLayout from "@/ts/layouts/PublicLayout";
import { useTranslation } from "@/ts/providers/i18n-provider";
import type { AuthUser } from "@/ts/types/auth";
import type { Pagination } from "@/ts/types/common";
import type { Tag } from "@/ts/types/tag";
import { router } from "@inertiajs/react";
import type { Post } from "@ts/types/post";
import { useRoute } from "ziggy-js";

const ListPage = ({
  posts,
  tags,
  auth,
}: {
  posts: Pagination<Post>;
  tags: Tag[];
  auth: AuthUser | null;
}) => {
  const route = useRoute();
  const { t, locale } = useTranslation();
  const { data } = posts;

  const handleSearch = (search: string) => {
    router.get(
      route("posts.index"),
      { search: search },
      {
        preserveUrl: true,
        preserveScroll: true,
        replace: true,
      }
    );
  };

  const currentTag =
    typeof window === "undefined"
      ? null
      : new URLSearchParams(window.location.search).get("tag");

  return (
    <PublicLayout auth={auth} locale={locale}>
      <SeoHead
        title={
          currentTag
            ? `${t("blog.taggedPosts", { tag: currentTag })} | Blog`
            : `${t("blog.latestPosts")} | Blog`
        }
        description={t("blog.metaDescription")}
        url={route("posts.index", undefined, true)}
        locale={locale === "vi" ? "vi_VN" : "en_US"}
      />
      <header className="mb-10">
        <p className="mb-2 text-sm font-medium uppercase tracking-widest text-emerald-500">
          {t("blog.label")}
        </p>
        <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
          <h1 className="text-3xl font-bold tracking-tight sm:text-4xl">
            {currentTag
              ? t("blog.taggedPosts", { tag: currentTag })
              : t("blog.latestPosts")}
          </h1>
          <div className="w-full sm:w-auto">
            <SearchForm onSearch={handleSearch} />
          </div>
        </div>
      </header>

      <div className="flex flex-col gap-8 lg:flex-row">
        <div className="min-w-0 flex-1">
          {data.length > 0 ? (
            <>
              <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
                {data.map((post: Post) => (
                  <PostListItem key={post.id} post={post} />
                ))}
              </div>
              <div className="mt-10 flex justify-center">
                <PaginationBar pagination={posts} />
              </div>
            </>
          ) : (
            <div className="rounded-xl border border-border bg-card py-16 text-center">
              <p className="text-lg text-muted-foreground">{t("blog.noPosts")}</p>
            </div>
          )}
        </div>

        <aside className="lg:w-72 lg:flex-shrink-0">
          <div className="sticky top-20 rounded-xl border border-border bg-card p-5">
            <h3 className="mb-4 text-lg font-semibold">{t("blog.tags")}</h3>
            <div className="flex flex-wrap gap-2">
              {tags.map((tag: Tag) => (
                <TagBadge
                  key={tag.id}
                  tag={tag}
                  useLink={true}
                  classes={
                    currentTag === tag.slug
                      ? "border-emerald-600/50 bg-emerald-600/20 text-emerald-400 hover:bg-emerald-600/30"
                      : ""
                  }
                />
              ))}
            </div>
          </div>
        </aside>
      </div>
    </PublicLayout>
  );
};

export default ListPage;
