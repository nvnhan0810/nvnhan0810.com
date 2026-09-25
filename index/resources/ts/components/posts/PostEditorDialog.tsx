import PostPreviewDialog from "@/ts/components/posts/PostPreviewDialog";
import { Button } from "@/ts/components/ui/button";
import { Checkbox } from "@/ts/components/ui/checkbox";
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/ts/components/ui/dialog";
import { Input } from "@/ts/components/ui/input";
import { Label } from "@/ts/components/ui/label";
import { Textarea } from "@/ts/components/ui/textarea";
import { BLOG_COPY } from "@/ts/constants/blogCopy";
import {
  isPostStatus,
  POST_STATUS_OPTIONS,
  PostStatus,
  type PostStatusValue,
} from "@/ts/constants/postStatus";
import type { Post, PostPayload } from "@/ts/types/post";
import type { Series } from "@/ts/types/series";
import {
  buildDocFromPost,
  buildPreviewPost,
  parseMarkdownToPostFields,
} from "@/ts/utils/postMarkdown";
import { useEffect, useState, type JSX } from "react";

type Props = {
  open: boolean;
  mode: "create" | "edit";
  initialPost?: Post | null;
  series: Series[];
  selectedSeriesIds?: number[];
  onOpenChange: (open: boolean) => void;
  onSave: (payload: PostPayload) => void;
  saving?: boolean;
};

const emptyDoc = "# \n\n";

const PostEditorDialog = ({
  open,
  mode,
  initialPost = null,
  series,
  selectedSeriesIds = [],
  onOpenChange,
  onSave,
  saving = false,
}: Props): JSX.Element => {
  const [doc, setDoc] = useState(emptyDoc);
  const [sourceUrl, setSourceUrl] = useState("");
  const [status, setStatus] = useState<PostStatusValue>(PostStatus.Draft);
  const [seriesIds, setSeriesIds] = useState<number[]>(selectedSeriesIds);
  const [errors, setErrors] = useState<string[]>([]);
  const [previewOpen, setPreviewOpen] = useState(false);
  const [previewPost, setPreviewPost] = useState<Post | null>(null);

  useEffect(() => {
    if (!open) {
      return;
    }

    if (initialPost) {
      setDoc(buildDocFromPost(initialPost));
      setSourceUrl(initialPost.source_url ?? "");
      setStatus(
        isPostStatus(initialPost.status) ? initialPost.status : PostStatus.Draft
      );
      setSeriesIds(selectedSeriesIds);
    } else {
      setDoc(emptyDoc);
      setSourceUrl("");
      setStatus(PostStatus.Draft);
      setSeriesIds([]);
    }

    setErrors([]);
    setPreviewOpen(false);
    setPreviewPost(null);
  }, [open, initialPost, selectedSeriesIds]);

  const handlePreview = (): void => {
    const parsed = parseMarkdownToPostFields(doc);
    const validationErrors: string[] = [];

    if (parsed.title.trim() === "") {
      validationErrors.push("Cần có Title (# …).");
    }

    if (validationErrors.length > 0) {
      setErrors(validationErrors);
      return;
    }

    setErrors([]);
    setPreviewPost(
      buildPreviewPost(parsed, {
        id: initialPost?.id ?? 0,
        slug: initialPost?.slug ?? "",
        published_at: initialPost?.published_at ?? new Date().toISOString(),
        status,
        source_url: sourceUrl.trim() || null,
      })
    );
    setPreviewOpen(true);
  };

  const handleSave = (): void => {
    const parsed = parseMarkdownToPostFields(doc);
    const validationErrors: string[] = [];

    if (parsed.title.trim() === "") {
      validationErrors.push("Cần đủ Title và Body.");
    }

    if (parsed.content.trim() === "") {
      validationErrors.push("Cần đủ Title và Body.");
    }

    if (validationErrors.length > 0) {
      setErrors([...new Set(validationErrors)]);
      return;
    }

    setErrors([]);

    const trimmedSourceUrl = sourceUrl.trim();

    onSave({
      title: parsed.title,
      description: parsed.description ?? null,
      content: parsed.content,
      source_url: trimmedSourceUrl !== "" ? trimmedSourceUrl : null,
      published_at: initialPost?.published_at ?? new Date().toISOString(),
      status,
      tags: parsed.tags,
      series_ids: seriesIds,
    });
  };

  return (
    <>
      <Dialog open={open} onOpenChange={onOpenChange}>
        <DialogContent
          className="flex h-[calc(100dvh-1.5rem)] max-h-[calc(100dvh-1.5rem)] w-[calc(100%-2rem)] max-w-5xl flex-col gap-4 overflow-hidden"
          onPointerDownOutside={(event) => event.preventDefault()}
          onInteractOutside={(event) => event.preventDefault()}
        >
          <DialogHeader className="shrink-0">
            <DialogTitle>
              {mode === "create" ? BLOG_COPY.createPost : BLOG_COPY.editPost}
            </DialogTitle>
          </DialogHeader>

          <div className="flex min-h-0 flex-1 flex-col gap-3 overflow-hidden pr-1">
            {errors.length > 0 && (
              <div className="shrink-0 text-sm text-red-500">
                {errors.map((error) => (
                  <p key={error}>{error}</p>
                ))}
              </div>
            )}

            <div className="grid shrink-0 grid-cols-1 gap-3 sm:grid-cols-[10rem_1fr]">
              <div className="space-y-2">
                <Label htmlFor="post-status">{BLOG_COPY.status}</Label>
                <select
                  id="post-status"
                  className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                  value={status}
                  onChange={(event) => {
                    const value = event.target.value;
                    if (isPostStatus(value)) {
                      setStatus(value);
                    }
                  }}
                >
                  {POST_STATUS_OPTIONS.map((option) => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
              </div>

              <div className="space-y-2">
                <Label htmlFor="post-source-url">Source URL</Label>
                <Input
                  id="post-source-url"
                  type="url"
                  placeholder="https://example.com/original-post"
                  value={sourceUrl}
                  onChange={(event) => setSourceUrl(event.target.value)}
                />
              </div>
            </div>

            <div className="flex min-h-0 flex-1 flex-col gap-2">
              <Label htmlFor="post-markdown" className="shrink-0">
                Markdown
              </Label>
              <div className="min-h-0 flex-1">
                <Textarea
                  id="post-markdown"
                  placeholder="# Tiêu đề bài viết"
                  className="h-full min-h-0 resize-none font-mono text-sm"
                  value={doc}
                  onChange={(event) => setDoc(event.target.value)}
                />
              </div>
              <p className="shrink-0 text-xs text-muted-foreground">
                Dòng đầu: <code># Title</code>, tùy chọn{" "}
                <code>Tags: a,b</code>, <code>&gt; mô tả</code>, rồi body.
              </p>
            </div>

            {series.length > 0 && (
              <div className="shrink-0 space-y-2">
                <Label>Series</Label>
                <div className="flex max-h-32 flex-col gap-2 overflow-y-auto rounded-md border border-border p-2">
                  {series.map((seriesItem) => (
                    <label
                      key={seriesItem.id}
                      className="flex items-center gap-3 text-sm"
                    >
                      <Checkbox
                        checked={seriesIds.includes(seriesItem.id)}
                        onCheckedChange={(checked) => {
                          if (checked) {
                            setSeriesIds([...seriesIds, seriesItem.id]);
                          } else {
                            setSeriesIds(
                              seriesIds.filter((id) => id !== seriesItem.id)
                            );
                          }
                        }}
                      />
                      <span>{seriesItem.name}</span>
                    </label>
                  ))}
                </div>
              </div>
            )}
          </div>

          <DialogFooter className="shrink-0 gap-2 sm:justify-between">
            <Button type="button" variant="outline" onClick={handlePreview}>
              {BLOG_COPY.previewPost}
            </Button>
            <div className="flex gap-2">
              <Button
                type="button"
                variant="outline"
                onClick={() => onOpenChange(false)}
              >
                {BLOG_COPY.cancel}
              </Button>
              <Button type="button" onClick={handleSave} disabled={saving}>
                {BLOG_COPY.savePost}
              </Button>
            </div>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <PostPreviewDialog
        open={previewOpen}
        onOpenChange={setPreviewOpen}
        post={previewPost}
      />
    </>
  );
};

export default PostEditorDialog;
