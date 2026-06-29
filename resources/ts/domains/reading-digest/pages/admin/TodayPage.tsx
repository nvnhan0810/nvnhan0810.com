import { Button } from "@/ts/components/ui/button";
import PrivateLayout, { RootProps } from "@/ts/layouts/PrivateLayout";
import { useRoute } from "ziggy-js";
import ReadingDigestNav from "../../components/ReadingDigestNav";
import type { RdDigestRun } from "../../../types";

type Props = RootProps & {
  run: RdDigestRun | null;
};

const reasonChips = ["practical", "code_example", "benchmark", "marketing", "opinion"];

const TodayPage = ({ auth, run }: Props) => {
  const route = useRoute();

  const record = async (articleId: string, event: string, metadata?: Record<string, unknown>) => {
    await fetch(route("reading-digest.interactions.store"), {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? "",
      },
      body: JSON.stringify({ article_id: articleId, event, metadata }),
    });
  };

  const like = (articleId: string) => record(articleId, "liked", { sentiment: "positive", reasons: ["practical"] });
  const dislike = (articleId: string) => record(articleId, "disliked", { sentiment: "negative", reasons: ["opinion"] });
  const save = (articleId: string) => record(articleId, "saved");
  const dismiss = (articleId: string) => record(articleId, "dismissed");

  return (
    <PrivateLayout auth={auth}>
      <ReadingDigestNav />
      <h1 className="text-2xl font-bold text-gray-100 mb-4">Today&apos;s Digest</h1>
      {!run && <p className="text-gray-400">No digest run for today yet. Use Settings → Send now.</p>}
      {run?.items?.map((item) => (
        <div key={item.id} className="border border-border rounded-lg p-4 mb-4 bg-card">
          <div className="flex justify-between gap-4">
            <div>
              <h2 className="text-lg font-semibold text-gray-100">{item.article?.title}</h2>
              <p className="text-sm text-muted-foreground mt-1">{item.subject?.name}</p>
              <p className="text-sm text-gray-400 mt-2">{item.llm_reason}</p>
              <p className="text-xs text-gray-500 mt-1">
                Retrieval: {item.retrieval_score?.toFixed(1)} · LLM: {item.llm_score?.toFixed(0)}
              </p>
            </div>
            <div className="flex gap-2 items-start">
              <Button size="sm" variant="outline" onClick={() => like(item.article!.id)}>👍</Button>
              <Button size="sm" variant="outline" onClick={() => dislike(item.article!.id)}>👎</Button>
              <Button size="sm" variant="outline" onClick={() => save(item.article!.id)}>💾</Button>
              <Button size="sm" variant="outline" onClick={() => dismiss(item.article!.id)}>✕</Button>
            </div>
          </div>
          <div className="flex flex-wrap gap-1 mt-3">
            {reasonChips.map((chip) => (
              <span key={chip} className="text-xs px-2 py-0.5 rounded-full bg-muted text-muted-foreground">{chip}</span>
            ))}
          </div>
        </div>
      ))}
    </PrivateLayout>
  );
};

export default TodayPage;
