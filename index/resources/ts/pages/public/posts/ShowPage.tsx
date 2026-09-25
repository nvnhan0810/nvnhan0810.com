import PostDetail from "@/ts/components/posts/PostDetail";
import PostEditorDialog from "@/ts/components/posts/PostEditorDialog";
import SeoHead from "@/ts/components/common/SeoHead";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/ts/components/ui/alert-dialog";
import { BLOG_COPY } from "@/ts/constants/blogCopy";
import PublicLayout, { type RootProps } from "@/ts/layouts/PublicLayout";
import type { Post, PostPayload } from "@/ts/types/post";
import type { Series } from "@/ts/types/series";
import { cn } from "@/ts/utils";
import { stripMarkdown, truncateDescription } from "@/ts/utils/seo";
import { Link, router } from "@inertiajs/react";
import { format } from "date-fns";
import { ArrowLeft, Pencil, Trash2 } from "lucide-react";
import { useState, type JSX } from "react";
import { useRoute } from "ziggy-js";

type Props = RootProps & {
  post: Post;
  series: Series[];
  editorSeries?: Series[];
  selectedSeriesIds?: number[];
  canManage?: boolean;
};

const PostDetailPage = ({
  post,
  auth,
  locale,
  series = [],
  editorSeries = [],
  selectedSeriesIds = [],
  canManage = false,
}: Props): JSX.Element => {
  const route = useRoute();
  const sourceUrl = post.source_url?.trim() ?? "";
  const seoDescription = post.description?.trim()
    ? post.description
    : truncateDescription(stripMarkdown(post.content ?? ""));

  const [editorOpen, setEditorOpen] = useState(false);
  const [deleteOpen, setDeleteOpen] = useState(false);
  const [saving, setSaving] = useState(false);

  const handleSave = (payload: PostPayload): void => {
    setSaving(true);

    router.put(
      route("admin.posts.update", { post: post.id }),
      {
        title: payload.title,
        description: payload.description,
        content: payload.content,
        source_url: payload.source_url,
        tags: payload.tags,
        published_at: payload.published_at
          ? format(payload.published_at, "yyyy-MM-dd")
          : null,
        status: payload.status,
        series_ids: payload.series_ids,
      },
      {
        onFinish: () => setSaving(false),
        onSuccess: () => setEditorOpen(false),
      }
    );
  };

  const confirmDelete = (): void => {
    router.delete(route("admin.posts.destroy", { post: post.id }));
  };

  return (
    <PublicLayout auth={auth} locale={locale}>
      <SeoHead
        title={`${post.title} | Blog`}
        description={seoDescription}
        url={route("posts.show", { slug: post.slug }, true)}
        type="article"
        locale={BLOG_COPY.ogLocale}
        publishedAt={post.published_at ?? undefined}
        imageUrl={post.og_image_url}
        imageAlt={post.title}
      />

      <div className="mb-8">
        <Link
          href={route("posts.index")}
          className="inline-flex items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-emerald-500"
        >
          <ArrowLeft className="h-4 w-4" />
          {BLOG_COPY.backToBlog}
        </Link>
      </div>

      {sourceUrl !== "" && (
        <p className="mb-6 text-sm text-muted-foreground">
          {BLOG_COPY.sourceOriginal}{" "}
          <a
            href={sourceUrl}
            target="_blank"
            rel="noreferrer"
            className="text-emerald-500 underline underline-offset-2 break-all"
          >
            {sourceUrl}
          </a>
        </p>
      )}

      <div className="grid grid-cols-1 gap-8 lg:grid-cols-12">
        <div
          className={cn("group relative col-span-1 min-w-0", {
            "lg:col-span-8": series.length > 0,
            "lg:col-span-12": series.length === 0,
          })}
        >
          {canManage && (
            <div className="absolute right-0 top-0 z-10 flex gap-1 opacity-0 transition-opacity group-hover:opacity-100 group-focus-within:opacity-100">
              <button
                type="button"
                onClick={() => setEditorOpen(true)}
                className="flex h-7 w-7 items-center justify-center rounded-md border border-border bg-background/95 text-muted-foreground shadow-sm transition-colors hover:text-emerald-500"
                title={BLOG_COPY.editPost}
              >
                <Pencil className="h-3 w-3" />
              </button>
              <button
                type="button"
                onClick={() => setDeleteOpen(true)}
                className="flex h-7 w-7 items-center justify-center rounded-md border border-border bg-background/95 text-muted-foreground shadow-sm transition-colors hover:text-red-500"
                title={BLOG_COPY.deletePost}
              >
                <Trash2 className="h-3 w-3" />
              </button>
            </div>
          )}
          <PostDetail post={post} useTagLink={true} />
        </div>

        {series.length > 0 && (
          <aside className="col-span-1 space-y-6 lg:col-span-4">
            <div className="sticky top-20">
              <h2 className="mb-4 border-b border-border pb-2 text-lg font-bold">
                {BLOG_COPY.series}
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

      {canManage && (
        <>
          <PostEditorDialog
            open={editorOpen}
            mode="edit"
            initialPost={post}
            series={editorSeries}
            selectedSeriesIds={selectedSeriesIds}
            onOpenChange={setEditorOpen}
            onSave={handleSave}
            saving={saving}
          />

          <AlertDialog open={deleteOpen} onOpenChange={setDeleteOpen}>
            <AlertDialogContent>
              <AlertDialogHeader>
                <AlertDialogTitle>{BLOG_COPY.deletePost}</AlertDialogTitle>
                <AlertDialogDescription>
                  {BLOG_COPY.confirmDelete}
                </AlertDialogDescription>
              </AlertDialogHeader>
              <AlertDialogFooter>
                <AlertDialogCancel>{BLOG_COPY.cancel}</AlertDialogCancel>
                <AlertDialogAction onClick={confirmDelete}>
                  {BLOG_COPY.deletePost}
                </AlertDialogAction>
              </AlertDialogFooter>
            </AlertDialogContent>
          </AlertDialog>
        </>
      )}
    </PublicLayout>
  );
};

export default PostDetailPage;
