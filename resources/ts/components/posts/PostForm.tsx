import usePostPreview from "@/ts/hooks/usePostPreview";
import type { Locale } from "@/ts/i18n";
import type { Post, PostPayload } from "@/ts/types/post";
import type { PostAgentEdits } from "@/ts/types/postAgent";
import { Link, usePage } from "@inertiajs/react";
import { format } from "date-fns";
import { ChevronDownIcon, PlusIcon } from "lucide-react";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { useRoute } from "ziggy-js";
import PostAgentChat from "./PostAgentChat";
import { Button } from "../ui/button";
import { Calendar } from "../ui/calendar";
import { Label } from "../ui/label";
import { Popover, PopoverContent, PopoverTrigger } from "../ui/popover";
import { Switch } from "../ui/switch";
import { Textarea } from "../ui/textarea";
import { Input } from "../ui/input";
import PostDetail from "./PostDetail";
import { cn } from "@/ts/utils";
import type { Series } from "@/ts/types/series";
import { Checkbox } from "../ui/checkbox";
import {
  buildDocsFromPost,
  buildPreviewPost,
  buildTranslationsFromDocs,
  parseMarkdownToPostFields,
} from "@/ts/utils/postMarkdown";

type Props = {
  initialPost?: Post;
  onSave: (payload: PostPayload) => void;
  series: Series[];
  selectedSeriesIds?: number[];
};

const localeLabels: Record<Locale, string> = {
  en: "English",
  vi: "Tiếng Việt",
};

type SharedPageProps = {
  postAgent?: {
    configured: boolean;
  } | null;
};

const PostForm = ({
  initialPost,
  onSave,
  series,
  selectedSeriesIds = [],
}: Props) => {
  const route = useRoute();
  const { postAgent } = usePage<SharedPageProps>().props;
  const postDetailRef = useRef<HTMLDivElement>(null);
  const textareaRef = useRef<HTMLTextAreaElement>(null);

  const [seriesIds, setSeriesIds] = useState<number[]>(selectedSeriesIds);
  const [activeLocale, setActiveLocale] = useState<Locale>("en");
  const [docs, setDocs] = useState<Record<Locale, string>>({ en: "", vi: "" });
  const [sourceUrls, setSourceUrls] = useState<Record<Locale, string>>({
    en: "",
    vi: "",
  });
  const [openDatePicker, setOpenDatePicker] = useState(false);
  const [formErrors, setFormErrors] = useState<string[]>([]);

  const basePost = useMemo(
    () =>
      initialPost ?? {
        id: 0,
        slug: "",
        title: "",
        content: "",
        published_at: new Date().toISOString(),
        is_published: false,
      },
    [initialPost]
  );

  const [meta, setMeta] = useState({
    published_at: basePost.published_at,
    is_published: basePost.is_published,
  });

  const { post, parseContentToPost, setPost } = usePostPreview({
    initialPost: basePost,
  });

  useEffect(() => {
    if (initialPost) {
      setDocs(buildDocsFromPost(initialPost));
      setSourceUrls({
        en: initialPost.translations?.en?.source_url ?? "",
        vi: initialPost.translations?.vi?.source_url ?? "",
      });
      setMeta({
        published_at: initialPost.published_at,
        is_published: initialPost.is_published,
      });
    }
  }, [initialPost]);

  useEffect(() => {
    parseContentToPost(docs[activeLocale], { requireContent: false });
  }, [docs, activeLocale, parseContentToPost]);

  useEffect(() => {
    const syncHeight = () => {
      if (postDetailRef.current && textareaRef.current) {
        textareaRef.current.style.height = `${postDetailRef.current.offsetHeight}px`;
      }
    };

    syncHeight();
    window.addEventListener("resize", syncHeight);

    if (postDetailRef.current) {
      const resizeObserver = new ResizeObserver(syncHeight);
      resizeObserver.observe(postDetailRef.current);

      return () => {
        resizeObserver.disconnect();
        window.removeEventListener("resize", syncHeight);
      };
    }

    return () => window.removeEventListener("resize", syncHeight);
  }, []);

  const handleSave = () => {
    const enParsed = parseMarkdownToPostFields(docs.en);
    const viParsed = parseMarkdownToPostFields(docs.vi);
    const hasEnContent =
      enParsed.title.trim() !== "" && enParsed.content.trim() !== "";
    const hasViContent =
      viParsed.title.trim() !== "" && viParsed.content.trim() !== "";
    const validationErrors: string[] = [];

    if (!hasEnContent && !hasViContent) {
      validationErrors.push("Cần ít nhất một locale (EN hoặc VI) có đủ Title và Body.");
    }

    if (validationErrors.length > 0) {
      setFormErrors(validationErrors);
      setActiveLocale(hasEnContent ? "vi" : "en");
      return;
    }

    const translations = buildTranslationsFromDocs(docs, sourceUrls);
    const enTags = enParsed.tags;

    onSave({
      translations,
      published_at: meta.published_at,
      is_published: meta.is_published,
      tags: enTags,
      series_ids: seriesIds,
    });
  };

  const handleApplyEdits = useCallback(
    (edits: PostAgentEdits) => {
      setDocs((current) => {
        const next = { ...current };

        for (const locale of Object.keys(edits.locales) as Locale[]) {
          const markdown = edits.locales[locale];

          if (markdown) {
            next[locale] = markdown;
          }
        }

        const activeMarkdown = next[activeLocale];
        const parsed = parseMarkdownToPostFields(activeMarkdown);
        setPost(buildPreviewPost(parsed, { ...basePost, ...meta }));

        return next;
      });

      if (edits.source_urls && Object.keys(edits.source_urls).length > 0) {
        setSourceUrls((current) => ({
          ...current,
          ...edits.source_urls,
        }));
      }

      setFormErrors([]);
    },
    [activeLocale, basePost, meta, setPost]
  );

  const handleDocChange = (value: string) => {
    setDocs((current) => ({
      ...current,
      [activeLocale]: value,
    }));
    const parsed = parseMarkdownToPostFields(value);
    setPost(buildPreviewPost(parsed, { ...basePost, ...meta }));
    const enParsed = parseMarkdownToPostFields(
      activeLocale === "en" ? value : docs.en
    );
    const viParsed = parseMarkdownToPostFields(
      activeLocale === "vi" ? value : docs.vi
    );
    const hasEnContent =
      enParsed.title.trim() !== "" && enParsed.content.trim() !== "";
    const hasViContent =
      viParsed.title.trim() !== "" && viParsed.content.trim() !== "";

    setFormErrors(
      !hasEnContent && !hasViContent
        ? ["Cần ít nhất một locale (EN hoặc VI) có đủ Title và Body."]
        : []
    );
  };

  const displayErrors = [...new Set(formErrors)];

  return (
    <div className="flex flex-col gap-4">
      {displayErrors.length > 0 && (
        <div className="text-red-500">
          {displayErrors.map((error) => (
            <p key={error}>{error}</p>
          ))}
        </div>
      )}

      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="inline-flex rounded-md border border-border p-0.5">
          {(["en", "vi"] as Locale[]).map((locale) => (
            <button
              key={locale}
              type="button"
              onClick={() => setActiveLocale(locale)}
              className={cn(
                "rounded px-3 py-1.5 text-sm transition-colors",
                activeLocale === locale
                  ? "bg-emerald-600 text-white"
                  : "text-muted-foreground hover:text-foreground"
              )}
            >
              {localeLabels[locale]}
            </button>
          ))}
        </div>
        <p className="text-sm text-muted-foreground">
          Đang chỉnh sửa: <span className="text-emerald-500">{localeLabels[activeLocale]}</span>
        </p>
      </div>

      <div className="flex flex-col gap-4 xl:flex-row xl:items-start">
        <div className="flex min-w-0 flex-1 flex-col gap-4 lg:flex-row">
          <div className="flex w-full flex-col gap-2 lg:w-1/2">
            <div className="space-y-2 rounded-md border border-gray-700 bg-zinc-900 p-3">
              <Label htmlFor={`source-url-${activeLocale}`}>
                Source URL ({localeLabels[activeLocale]})
              </Label>
              <Input
                id={`source-url-${activeLocale}`}
                type="url"
                placeholder="https://example.com/original-post"
                value={sourceUrls[activeLocale]}
                onChange={(e) =>
                  setSourceUrls((current) => ({
                    ...current,
                    [activeLocale]: e.target.value,
                  }))
                }
              />
              <p className="text-xs text-muted-foreground">
                Source URL chỉ hiển thị tham khảo ở trang detail, không tự redirect.
              </p>
            </div>
            <Textarea
              ref={textareaRef}
              placeholder={`# Title (${localeLabels[activeLocale]})`}
              className="min-h-[200px] resize-none overflow-y-auto border-gray-700"
              value={docs[activeLocale]}
              onChange={(e) => handleDocChange(e.target.value)}
            />
          </div>
          <div className="flex w-full flex-col gap-2 px-0 lg:w-1/2 lg:px-4">
            <div ref={postDetailRef}>{post && <PostDetail post={post} />}</div>
          </div>
        </div>

        <div className="w-full shrink-0 xl:sticky xl:top-20 xl:z-20 xl:w-96 xl:self-start">
          <PostAgentChat
            postId={initialPost?.id || undefined}
            docs={docs}
            sourceUrls={sourceUrls}
            activeLocale={activeLocale}
            configured={postAgent?.configured ?? false}
            onApplyEdits={handleApplyEdits}
          />
        </div>
      </div>

      <div className="mt-6 flex flex-col gap-4 rounded-md border border-gray-700 bg-zinc-900 p-4">
        <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div className="flex flex-col gap-2">
            <div className="flex items-center gap-2">
              <Label>Xuất bản</Label>
              <Switch
                checked={meta.is_published}
                onCheckedChange={(checked) => {
                  setMeta((current) => ({
                    ...current,
                    is_published: checked as boolean,
                  }));
                }}
              />
            </div>

            <div className="flex flex-col gap-2 md:flex-row md:items-center md:gap-4">
              <Label>Ngày xuất bản</Label>
              <Popover open={openDatePicker} onOpenChange={setOpenDatePicker}>
                <PopoverTrigger asChild>
                  <Button
                    variant="outline"
                    id="date"
                    className="w-full justify-between font-normal md:w-48"
                  >
                    {meta.published_at
                      ? format(meta.published_at, "dd/MM/yyyy")
                      : "Chọn ngày"}
                    <ChevronDownIcon />
                  </Button>
                </PopoverTrigger>
                <PopoverContent
                  className="w-auto overflow-hidden bg-slate-800 p-0"
                  align="start"
                >
                  <Calendar
                    mode="single"
                    selected={
                      meta.published_at ? new Date(meta.published_at) : undefined
                    }
                    captionLayout="dropdown"
                    onSelect={(date) => {
                      setMeta((current) => ({
                        ...current,
                        published_at: date?.toISOString() ?? "",
                      }));
                      setOpenDatePicker(false);
                    }}
                  />
                </PopoverContent>
              </Popover>
            </div>
          </div>

          <div className="flex flex-col gap-2 md:items-end">
            <Label>Danh sách series</Label>
            <div className="flex max-h-40 flex-col gap-2 overflow-y-auto rounded-md border border-gray-700 p-2">
              {series.map((seriesItem) => (
                <div key={seriesItem.id} className="flex items-center gap-3">
                  <Checkbox
                    checked={seriesIds?.includes(seriesItem.id)}
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
                </div>
              ))}
            </div>
            <Button variant="outline" type="button">
              <PlusIcon className="h-4 w-4" />
            </Button>
          </div>
        </div>

        <div className="flex justify-end gap-2 border-t border-gray-700 pt-4">
          <Button variant="outline" asChild className="text-gray-100">
            <Link href={route("admin.index")}>Quay Lại</Link>
          </Button>
          <Button
            variant="outline"
            onClick={handleSave}
          >
            Lưu
          </Button>
        </div>
      </div>
    </div>
  );
};

export default PostForm;
