import usePostAgent from "@/ts/hooks/usePostAgent";
import type { Locale } from "@/ts/i18n";
import type { PostAgentEdits } from "@/ts/types/postAgent";
import { BotIcon, Loader2Icon, SendIcon, SparklesIcon, XIcon } from "lucide-react";
import { FormEvent, useEffect, useRef, useState } from "react";
import { Button } from "../ui/button";
import { Textarea } from "../ui/textarea";
import { cn } from "@/ts/utils";

type Props = {
  postId?: number;
  docs: Record<Locale, string>;
  sourceUrls: Record<Locale, string>;
  activeLocale: Locale;
  configured: boolean;
  onApplyEdits: (edits: PostAgentEdits) => void;
};

const localeLabels: Record<Locale, string> = {
  en: "EN",
  vi: "VI",
};

const PostAgentChat = ({
  postId,
  docs,
  sourceUrls,
  activeLocale,
  configured,
  onApplyEdits,
}: Props) => {
  const [input, setInput] = useState("");
  const scrollRef = useRef<HTMLDivElement>(null);

  const { messages, isLoading, isBootstrapping, error, sendMessage, cancelMessage } =
    usePostAgent({
      postId,
      configured,
      context: {
        docs,
        source_urls: sourceUrls,
        active_locale: activeLocale,
      },
      onApplyEdits,
    });

  useEffect(() => {
    if (scrollRef.current) {
      scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
    }
  }, [messages, isLoading]);

  const handleCancel = async () => {
    const pendingMessage = await cancelMessage();

    if (pendingMessage) {
      setInput(pendingMessage);
    }
  };

  const submitMessage = async () => {
    const message = input.trim();

    if (!message || isLoading || !configured) {
      return;
    }

    setInput("");
    await sendMessage(message);
  };

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault();
    await submitMessage();
  };

  const formatEditsSummary = (edits?: PostAgentEdits) => {
    if (!edits) {
      return null;
    }

    const changedLocales = Object.keys(edits.locales ?? {}) as Locale[];
    const changedUrls = Object.keys(edits.source_urls ?? {}) as Locale[];

    if (changedLocales.length === 0 && changedUrls.length === 0) {
      return null;
    }

    const parts: string[] = [];

    if (changedLocales.length > 0) {
      parts.push(
        `Đã cập nhật nội dung: ${changedLocales.map((locale) => localeLabels[locale]).join(", ")}`
      );
    }

    if (changedUrls.length > 0) {
      parts.push(
        `Đã cập nhật source URL: ${changedUrls.map((locale) => localeLabels[locale]).join(", ")}`
      );
    }

    return parts.join(" · ");
  };

  return (
    <div className="flex h-[50dvh] max-h-[50dvh] flex-col overflow-hidden rounded-md border border-gray-700 bg-zinc-900">
      <div className="flex items-center gap-2 border-b border-gray-700 px-4 py-3">
        <SparklesIcon className="h-4 w-4 text-emerald-400" />
        <div>
          <p className="text-sm font-medium text-gray-100">Post Agent</p>
          <p className="text-xs text-muted-foreground">
            Hỗ trợ chỉnh sửa EN + VI — bấm Lưu để ghi DB
          </p>
        </div>
      </div>

      {!configured && (
        <div className="border-b border-amber-900/50 bg-amber-950/30 px-4 py-3 text-sm text-amber-200">
          Chưa cấu hình <code className="text-amber-100">CURSOR_API_KEY</code>{" "}
          trong file .env.
        </div>
      )}

      <div ref={scrollRef} className="min-h-0 flex-1 space-y-3 overflow-y-auto p-4">
        {isBootstrapping ? (
          <div className="flex items-center gap-2 text-sm text-muted-foreground">
            <Loader2Icon className="h-4 w-4 animate-spin" />
            Đang tải lịch sử chat...
          </div>
        ) : messages.length === 0 ? (
          <div className="rounded-md border border-dashed border-gray-700 p-4 text-sm text-muted-foreground">
            <p className="flex items-center gap-2 font-medium text-gray-200">
              <BotIcon className="h-4 w-4" />
              Gợi ý lệnh
            </p>
            <ul className="mt-2 list-disc space-y-1 pl-5">
              <li>Viết draft bài về [chủ đề] bằng tiếng Anh và tiếng Việt</li>
              <li>Dịch phần intro sang tiếng Việt, giữ nguyên EN</li>
              <li>Rút gọn body, thêm ví dụ code</li>
              <li>Đề xuất title và tags phù hợp SEO</li>
            </ul>
          </div>
        ) : (
          messages.map((message, index) => (
            <div
              key={`${message.role}-${index}-${message.created_at ?? index}`}
              className={cn(
                "rounded-lg px-3 py-2 text-sm",
                message.role === "user"
                  ? "ml-8 bg-emerald-900/40 text-emerald-50"
                  : "mr-4 bg-zinc-800 text-gray-100"
              )}
            >
              <p className="mb-1 text-[11px] uppercase tracking-wide text-muted-foreground">
                {message.role === "user" ? "Bạn" : "Agent"}
              </p>
              <p className="whitespace-pre-wrap">{message.content}</p>
              {message.role === "assistant" && formatEditsSummary(message.edits) && (
                <p className="mt-2 text-xs text-emerald-300">
                  {formatEditsSummary(message.edits)}
                </p>
              )}
            </div>
          ))
        )}

        {isLoading && (
          <div className="flex items-center justify-between gap-2 rounded-md border border-gray-700 bg-zinc-950 px-3 py-2 text-sm text-muted-foreground">
            <div className="flex items-center gap-2">
              <Loader2Icon className="h-4 w-4 animate-spin" />
              Agent đang xử lý...
            </div>
            <Button
              type="button"
              size="sm"
              variant="outline"
              onClick={() => void handleCancel()}
            >
              <XIcon className="h-4 w-4" />
              Hủy
            </Button>
          </div>
        )}
      </div>

      {error && (
        <div className="border-t border-red-900/50 bg-red-950/30 px-4 py-2 text-sm text-red-300">
          {error}
        </div>
      )}

      <form
        onSubmit={handleSubmit}
        className="shrink-0 border-t border-gray-700 p-3"
      >
        <p className="mb-2 text-xs text-muted-foreground">
          Shift+Enter xuống dòng
        </p>
        <Textarea
          value={input}
          onChange={(event) => setInput(event.target.value)}
          placeholder="Nhờ agent viết, dịch, hoặc chỉnh sửa bài (EN + VI)..."
          className="min-h-[88px] resize-none border-gray-700 bg-zinc-950"
          disabled={!configured || isLoading}
          onKeyDown={(event) => {
            if (event.key === "Enter" && !event.shiftKey) {
              event.preventDefault();
              void submitMessage();
            }
          }}
        />
        <div className="mt-2 flex justify-end gap-2">
          {isLoading ? (
            <Button
              type="button"
              size="sm"
              variant="outline"
              onClick={() => void handleCancel()}
            >
              <XIcon className="h-4 w-4" />
              Hủy
            </Button>
          ) : (
            <Button
              type="submit"
              size="sm"
              disabled={!configured || input.trim() === ""}
            >
              <SendIcon className="h-4 w-4" />
              Gửi
            </Button>
          )}
        </div>
      </form>
    </div>
  );
};

export default PostAgentChat;
