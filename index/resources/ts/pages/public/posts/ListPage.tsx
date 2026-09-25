import PaginationBar from "@/ts/components/common/PaginationBar";
import SeoHead from "@/ts/components/common/SeoHead";
import PostEditorDialog from "@/ts/components/posts/PostEditorDialog";
import PostListItem from "@/ts/components/posts/PostListItem";
import SearchForm from "@/ts/components/posts/SearchForm";
import TagBadge from "@/ts/components/tags/TagBadge";
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
import { Button } from "@/ts/components/ui/button";
import { BLOG_COPY } from "@/ts/constants/blogCopy";
import {
  POST_STATUS_OPTIONS,
} from "@/ts/constants/postStatus";
import PublicLayout from "@/ts/layouts/PublicLayout";
import { useTranslation } from "@/ts/providers/i18n-provider";
import type { AuthUser } from "@/ts/types/auth";
import type { Pagination } from "@/ts/types/common";
import type { Post, PostPayload } from "@/ts/types/post";
import type { Series } from "@/ts/types/series";
import type { Tag } from "@/ts/types/tag";
import { cn } from "@/ts/utils";
import { router } from "@inertiajs/react";
import { format } from "date-fns";
import { Plus } from "lucide-react";
import { useEffect, useState, type JSX } from "react";
import { useRoute } from "ziggy-js";

type Filters = {
  search?: string | null;
  tag?: string | null;
  status?: string | null;
};

type Props = {
  posts: Pagination<Post>;
  tags: Tag[];
  auth: AuthUser | null;
  filters?: Filters;
  series?: Series[];
  editingPost?: Post | null;
  selectedSeriesIds?: number[];
  canManage?: boolean;
};

const ListPage = ({
  posts,
  tags,
  auth,
  filters,
  series = [],
  editingPost = null,
  selectedSeriesIds = [],
  canManage = false,
}: Props): JSX.Element => {
  const route = useRoute();
  const { locale } = useTranslation();
  const { data } = posts;

  const [editorOpen, setEditorOpen] = useState(false);
  const [editorMode, setEditorMode] = useState<"create" | "edit">("create");
  const [activePost, setActivePost] = useState<Post | null>(null);
  const [activeSeriesIds, setActiveSeriesIds] = useState<number[]>([]);
  const [deleteTarget, setDeleteTarget] = useState<Post | null>(null);
  const [saving, setSaving] = useState(false);

  const currentTag =
    filters?.tag ??
    (typeof window === "undefined"
      ? null
      : new URLSearchParams(window.location.search).get("tag"));

  const currentStatus = filters?.status ?? null;

  useEffect(() => {
    if (!canManage || !editingPost) {
      return;
    }

    setEditorMode("edit");
    setActivePost(editingPost);
    setActiveSeriesIds(selectedSeriesIds);
    setEditorOpen(true);
  }, [canManage, editingPost, selectedSeriesIds]);

  useEffect(() => {
    if (!canManage || typeof window === "undefined") {
      return;
    }

    const create = new URLSearchParams(window.location.search).get("create");
    if (create === "1") {
      openCreate();
    }
  }, [canManage]);

  const visitIndex = (params: Record<string, string | undefined>): void => {
    router.get(route("posts.index"), params, {
      preserveScroll: true,
      replace: true,
    });
  };

  const handleSearch = (search: string): void => {
    visitIndex({
      search: search || undefined,
      tag: currentTag || undefined,
      status: currentStatus || undefined,
    });
  };

  const handleStatusFilter = (status: string | null): void => {
    visitIndex({
      search: filters?.search || undefined,
      tag: currentTag || undefined,
      status: status || undefined,
    });
  };

  const openCreate = (): void => {
    setEditorMode("create");
    setActivePost(null);
    setActiveSeriesIds([]);
    setEditorOpen(true);
  };

  const openEdit = (post: Post): void => {
    router.get(
      route("posts.index"),
      {
        edit: String(post.id),
        search: filters?.search || undefined,
        tag: currentTag || undefined,
        status: currentStatus || undefined,
      },
      {
        preserveScroll: true,
        preserveState: true,
        only: ["editingPost", "selectedSeriesIds", "series"],
      }
    );
  };

  const closeEditor = (): void => {
    setEditorOpen(false);
    setActivePost(null);

    if (filters?.search || currentTag || currentStatus || editingPost) {
      visitIndex({
        search: filters?.search || undefined,
        tag: currentTag || undefined,
        status: currentStatus || undefined,
      });
    }
  };

  const handleSave = (payload: PostPayload): void => {
    setSaving(true);

    const body = {
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
    };

    const options = {
      onFinish: () => setSaving(false),
      onSuccess: () => {
        setEditorOpen(false);
        setActivePost(null);
      },
    };

    if (editorMode === "edit" && activePost) {
      router.put(route("admin.posts.update", { post: activePost.id }), body, options);
      return;
    }

    router.post(route("admin.posts.store"), body, options);
  };

  const confirmDelete = (): void => {
    if (!deleteTarget) {
      return;
    }

    router.delete(route("admin.posts.destroy", { post: deleteTarget.id }), {
      onFinish: () => setDeleteTarget(null),
    });
  };

  return (
    <PublicLayout auth={auth} locale={locale} wide>
      <SeoHead
        title={
          currentTag
            ? `${BLOG_COPY.taggedPosts(currentTag)} | Blog`
            : `${BLOG_COPY.latestPosts} | Blog`
        }
        description={BLOG_COPY.metaDescription}
        url={route("posts.index", undefined, true)}
        locale={BLOG_COPY.ogLocale}
      />
      <header className="mb-10 w-full">
        <p className="mb-2 text-sm font-medium uppercase tracking-widest text-emerald-500">
          {BLOG_COPY.label}
        </p>
        <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
          <h1 className="text-3xl font-bold tracking-tight sm:text-4xl">
            {currentTag
              ? BLOG_COPY.taggedPosts(currentTag)
              : BLOG_COPY.latestPosts}
          </h1>
          <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
            {canManage && (
              <Button
                type="button"
                size="icon"
                onClick={openCreate}
                title={BLOG_COPY.createPost}
                aria-label={BLOG_COPY.createPost}
              >
                <Plus className="h-4 w-4" />
              </Button>
            )}
            <SearchForm
              onSearch={handleSearch}
              initialSearch={filters?.search ?? ""}
            />
          </div>
        </div>

        {canManage && (
          <div className="mt-4 flex flex-wrap gap-2">
            <button
              type="button"
              onClick={() => handleStatusFilter(null)}
              className={cn(
                "rounded-full border px-3 py-1 text-xs font-medium transition-colors",
                currentStatus === null
                  ? "border-emerald-600/50 bg-emerald-600/20 text-emerald-400"
                  : "border-border text-muted-foreground hover:border-emerald-600/40"
              )}
            >
              {BLOG_COPY.allStatuses}
            </button>
            {POST_STATUS_OPTIONS.map((option) => (
              <button
                key={option.value}
                type="button"
                onClick={() => handleStatusFilter(option.value)}
                className={cn(
                  "rounded-full border px-3 py-1 text-xs font-medium transition-colors",
                  currentStatus === option.value
                    ? "border-emerald-600/50 bg-emerald-600/20 text-emerald-400"
                    : "border-border text-muted-foreground hover:border-emerald-600/40"
                )}
              >
                {option.label}
              </button>
            ))}
          </div>
        )}
      </header>

      <div className="flex w-full flex-col gap-8 lg:flex-row">
        <div className="min-w-0 flex-1">
          {data.length > 0 ? (
            <>
              <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                {data.map((post: Post) => (
                  <PostListItem
                    key={post.id}
                    post={post}
                    canManage={canManage}
                    onEdit={openEdit}
                    onDelete={setDeleteTarget}
                  />
                ))}
              </div>
              <div className="mt-10 flex justify-center">
                <PaginationBar pagination={posts} />
              </div>
            </>
          ) : (
            <div className="rounded-xl border border-border bg-card py-16 text-center">
              <p className="text-lg text-muted-foreground">{BLOG_COPY.noPosts}</p>
            </div>
          )}
        </div>

        <aside className="w-full lg:w-72 lg:flex-shrink-0">
          <div className="sticky top-20 rounded-xl border border-border bg-card p-5">
            <h3 className="mb-4 text-lg font-semibold">{BLOG_COPY.tags}</h3>
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

      {canManage && (
        <>
          <PostEditorDialog
            open={editorOpen}
            mode={editorMode}
            initialPost={activePost}
            series={series}
            selectedSeriesIds={activeSeriesIds}
            onOpenChange={(open) => {
              if (!open) {
                closeEditor();
                return;
              }
              setEditorOpen(true);
            }}
            onSave={handleSave}
            saving={saving}
          />

          <AlertDialog
            open={deleteTarget !== null}
            onOpenChange={(open) => {
              if (!open) {
                setDeleteTarget(null);
              }
            }}
          >
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

export default ListPage;
