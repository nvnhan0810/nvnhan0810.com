import SeoHead from "@/ts/components/common/SeoHead";
import PublicLayout, { type RootProps } from "@/ts/layouts/PublicLayout";
import { Link, router } from "@inertiajs/react";
import { ExternalLink, ThumbsDown, ThumbsUp } from "lucide-react";
import { useRoute } from "ziggy-js";

type NewsArticle = {
  id: string;
  title: string;
  url: string;
  summary?: string | null;
  image_url?: string | null;
  published_at?: string | null;
  source?: { id: string; name: string } | null;
};

type DigestItem = {
  id: string;
  rank: number;
  tracking_token: string;
  subject?: { id: string; name: string } | null;
  article: NewsArticle;
};

type Props = RootProps & {
  run: {
    id: string;
    run_date: string;
    status: string;
    telegram_sent_at?: string | null;
  } | null;
  groups: Record<string, DigestItem[]>;
};

const NewsTodayPage = ({ auth, locale, run, groups }: Props) => {
  const route = useRoute();
  const sourceNames = Object.keys(groups);
  const total = sourceNames.reduce((n, key) => n + groups[key].length, 0);

  const vote = (token: string, event: "liked" | "disliked") => {
    router.post(route("news.vote", token), { event }, { preserveScroll: true });
  };

  return (
    <PublicLayout auth={auth} locale={locale} active="news">
      <SeoHead
        title="Today's Digest"
        description="Today's selected reading digest."
        url={route("news.today", undefined, true)}
        locale={locale === "vi" ? "vi_VN" : "en_US"}
      />

      <header className="mb-10">
        <p className="mb-2 text-sm font-medium uppercase tracking-widest text-emerald-500">
          Digest
        </p>
        <h1 className="text-3xl font-extrabold tracking-tight text-foreground md:text-4xl">
          Today
        </h1>
        <p className="mt-3 max-w-2xl text-muted-foreground">
          {run
            ? `${total} article(s) selected for ${run.run_date}. Open originals to count reads; vote to train the next digests.`
            : "No digest run for today yet."}
        </p>
        <p className="mt-4">
          <Link
            href={route("news.index")}
            className="text-sm font-medium text-emerald-500 hover:underline"
          >
            ← All news
          </Link>
        </p>
      </header>

      {!run && (
        <p className="text-muted-foreground">
          Nothing selected yet. Add sources to a subject, then use Admin → Settings → Fetch &amp; send.
        </p>
      )}

      {sourceNames.map((sourceName) => (
        <section key={sourceName} className="mb-12">
          <h2 className="mb-6 border-b border-border pb-2 text-lg font-semibold tracking-tight text-foreground">
            {sourceName}
          </h2>
          <div className="grid gap-8">
            {groups[sourceName].map((item) => {
              const article = item.article;
              return (
                <article key={item.id} className="grid gap-4 sm:grid-cols-[180px_1fr] sm:gap-6">
                  {article.image_url ? (
                    <a
                      href={route("news.open", item.tracking_token)}
                      className="block overflow-hidden rounded-lg bg-muted"
                    >
                      <img
                        src={article.image_url}
                        alt=""
                        className="aspect-[4/3] h-full w-full object-cover"
                        loading="lazy"
                      />
                    </a>
                  ) : (
                    <div className="hidden rounded-lg bg-muted/40 sm:block" />
                  )}

                  <div>
                    {item.subject?.name && (
                      <p className="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        {item.subject.name}
                      </p>
                    )}
                    <h3 className="text-xl font-bold tracking-tight text-foreground">
                      <a
                        href={route("news.open", item.tracking_token)}
                        className="hover:text-emerald-500"
                      >
                        {article.title}
                      </a>
                    </h3>
                    {article.summary && (
                      <p className="mt-2 line-clamp-4 text-sm text-muted-foreground">
                        {article.summary}
                      </p>
                    )}

                    <div className="mt-4 flex flex-wrap items-center gap-3">
                      <a
                        href={route("news.open", item.tracking_token)}
                        className="inline-flex items-center gap-1.5 rounded-md bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-500"
                      >
                        Xem bài gốc
                        <ExternalLink className="h-3.5 w-3.5" />
                      </a>
                      <button
                        type="button"
                        onClick={() => vote(item.tracking_token, "liked")}
                        className="inline-flex items-center gap-1.5 rounded-md border border-border px-3 py-1.5 text-sm text-foreground hover:bg-muted"
                      >
                        <ThumbsUp className="h-3.5 w-3.5" />
                        Upvote
                      </button>
                      <button
                        type="button"
                        onClick={() => vote(item.tracking_token, "disliked")}
                        className="inline-flex items-center gap-1.5 rounded-md border border-border px-3 py-1.5 text-sm text-foreground hover:bg-muted"
                      >
                        <ThumbsDown className="h-3.5 w-3.5" />
                        Downvote
                      </button>
                    </div>
                  </div>
                </article>
              );
            })}
          </div>
        </section>
      ))}
    </PublicLayout>
  );
};

export default NewsTodayPage;
