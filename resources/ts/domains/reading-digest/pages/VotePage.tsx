import { Button } from "@/ts/components/ui/button";
import { Input } from "@/ts/components/ui/input";
import { Label } from "@/ts/components/ui/label";
import PrivateLayout, { RootProps } from "@/ts/layouts/PrivateLayout";
import { router, usePage } from "@inertiajs/react";
import { useState } from "react";
import { useRoute } from "ziggy-js";
import type { RdArticle, RdSubject } from "../types";

type VoteItem = {
  id: string;
  tracking_token: string;
  read_url: string;
  subject?: Pick<RdSubject, "id" | "name">;
  article: RdArticle;
};

type Props = RootProps & {
  item: VoteItem;
};

const VotePage = ({ auth, item }: Props) => {
  const route = useRoute();
  const { flash } = usePage<{ flash?: { success?: string } }>().props;
  const [customTags, setCustomTags] = useState<string[]>([]);
  const [tagInput, setTagInput] = useState("");
  const [note, setNote] = useState("");
  const [processing, setProcessing] = useState(false);

  const existingUserTags = (item.article.metadata?.user_tags as string[] | undefined) ?? [];

  const addTag = () => {
    const tag = tagInput.trim().toLowerCase();
    if (!tag || customTags.includes(tag)) {
      return;
    }
    setCustomTags([...customTags, tag]);
    setTagInput("");
  };

  const removeTag = (tag: string) => {
    setCustomTags(customTags.filter((t) => t !== tag));
  };

  const submitVote = (event: "liked" | "disliked" | "dismissed") => {
    setProcessing(true);
    router.post(
      route("reading-digest.article.vote.store", item.tracking_token),
      { event, custom_tags: customTags, note: note || null },
      {
        preserveScroll: true,
        onFinish: () => setProcessing(false),
      },
    );
  };

  return (
    <PrivateLayout auth={auth}>
      <div className="max-w-2xl mx-auto py-6">
        <p className="text-sm text-muted-foreground mb-2">{item.subject?.name}</p>
        <h1 className="text-2xl font-bold text-gray-100 mb-2">{item.article.title}</h1>
        {item.article.source?.name && (
          <p className="text-sm text-muted-foreground mb-4">Nguồn: {item.article.source.name}</p>
        )}
        {item.article.summary && (
          <p className="text-gray-300 mb-4 leading-relaxed">{item.article.summary}</p>
        )}
        {existingUserTags.length > 0 && (
          <div className="flex flex-wrap gap-2 mb-4">
            {existingUserTags.map((tag) => (
              <span key={tag} className="text-xs px-2 py-1 rounded-full bg-blue-950 text-blue-300">{tag}</span>
            ))}
          </div>
        )}

        <a
          href={item.read_url}
          target="_blank"
          rel="noreferrer"
          className="inline-block text-blue-400 hover:underline mb-6"
        >
          Đọc bài trên site gốc →
        </a>

        {flash?.success && (
          <p className="text-green-400 text-sm mb-4">{flash.success}</p>
        )}

        <div className="border border-border rounded-lg p-4 bg-card space-y-4">
          <h2 className="font-semibold text-gray-100">Đánh giá bài này</h2>
          <p className="text-sm text-muted-foreground">
            Vote sau khi đọc. Tag tuỳ chọn giúp hệ thống tránh gửi bài tương tự sau này.
          </p>

          <div>
            <Label>Tag tuỳ chọn</Label>
            <div className="flex gap-2 mt-1">
              <Input
                placeholder="VD: quảng cáo, nông"
                value={tagInput}
                onChange={(e) => setTagInput(e.target.value)}
                onKeyDown={(e) => e.key === "Enter" && (e.preventDefault(), addTag())}
              />
              <Button type="button" variant="outline" onClick={addTag}>Thêm</Button>
            </div>
            {customTags.length > 0 && (
              <div className="flex flex-wrap gap-2 mt-2">
                {customTags.map((tag) => (
                  <button
                    key={tag}
                    type="button"
                    className="text-xs px-2 py-1 rounded-full bg-primary/20 text-primary"
                    onClick={() => removeTag(tag)}
                  >
                    {tag} ×
                  </button>
                ))}
              </div>
            )}
          </div>

          <div>
            <Label>Ghi chú (tuỳ chọn)</Label>
            <Input
              className="mt-1"
              value={note}
              onChange={(e) => setNote(e.target.value)}
              placeholder="Lý do?"
            />
          </div>

          <div className="flex flex-wrap gap-2 pt-2">
            <Button type="button" disabled={processing} onClick={() => submitVote("liked")}>
              👍 Hay
            </Button>
            <Button type="button" variant="outline" disabled={processing} onClick={() => submitVote("disliked")}>
              👎 Không hay
            </Button>
            <Button type="button" variant="destructive" disabled={processing} onClick={() => submitVote("dismissed")}>
              Đừng gửi bài kiểu này nữa
            </Button>
          </div>
        </div>
      </div>
    </PrivateLayout>
  );
};

export default VotePage;
