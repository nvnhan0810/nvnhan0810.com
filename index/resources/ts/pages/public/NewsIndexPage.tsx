import SeoHead from "@/ts/components/common/SeoHead";
import PublicLayout, { type RootProps } from "@/ts/layouts/PublicLayout";
import { Link } from "@inertiajs/react";
import { ExternalLink, Newspaper } from "lucide-react";
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

type Paginated<T> = {
  data: T[];
  links: { url: string | null; label: string; active: boolean }[];
  current_page: number;
  last_page: number;
};

type Props = RootProps & {
  articles: Paginated<NewsArticle>;
};

const NewsIndexPage = ({ auth, locale, articles }: Props) => {
  const route = useRoute();

  return (
    <PublicLayout auth={auth} locale={locale} active="news">
      <SeoHead
        title="News"
        description="Latest articles from reading sources."
        url={route("news.index", undefined, true)}
        locale={locale === "vi" ? "vi_VN" : "en_US"}
      />

      <header className="mb-10">
        <p className="mb-2 text-sm font-medium uppercase tracking-widest text-emerald-500">
          News
        </p>
        <h1 className="flex items-center gap-3 text-3xl font-extrabold tracking-tight text-foreground md:text-4xl">
          <Newspaper className="h-8 w-8 text-emerald-500" />
          Latest
        </h1>
        <p className="mt-3 max-w-2xl text-muted-foreground">
          Newest articles from connected sources, newest first.
        </p>
        {auth && (
          <p className="mt-4">
            <Link
              href={route("news.today")}
              className="text-sm font-medium text-emerald-500 hover:underline"
            >
              Today&apos;s digest →
            </Link>
          </p>
        )}
      </header>

      <div className="grid gap-8">
        {articles.data.length === 0 && (
          <p className="text-muted-foreground">No articles yet.</p>
        )}

        {articles.data.map((article) => (
          <article key={article.id} className="border-b border-border/60 pb-8 last:border-0">
            {article.image_url && (
              <a href={article.url} target="_blank" rel="noopener noreferrer" className="mb-4 block overflow-hidden rounded-lg">
                <img
                  src={article.image_url}
                  alt=""
                  className="aspect-[2/1] w-full object-cover"
                  loading="lazy"
                />
              </a>
            )}
            <p className="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground">
              {article.source?.name ?? "Source"}
              {article.published_at
                ? ` · ${new Date(article.published_at).toLocaleDateString()}`
                : ""}
            </p>
            <h2 className="text-xl font-bold tracking-tight text-foreground md:text-2xl">
              <a
                href={article.url}
                target="_blank"
                rel="noopener noreferrer"
                className="hover:text-emerald-500"
              >
                {article.title}
              </a>
            </h2>
            {article.summary && (
              <p className="mt-2 line-clamp-3 text-muted-foreground">{article.summary}</p>
            )}
            <a
              href={article.url}
              target="_blank"
              rel="noopener noreferrer"
              className="mt-3 inline-flex items-center gap-1.5 text-sm font-medium text-emerald-500 hover:underline"
            >
              Xem bài gốc
              <ExternalLink className="h-3.5 w-3.5" />
            </a>
          </article>
        ))}
      </div>

      {articles.last_page > 1 && (
        <nav className="mt-10 flex flex-wrap gap-2">
          {articles.links.map((link, i) =>
            link.url ? (
              <Link
                key={`${link.label}-${i}`}
                href={link.url}
                className={`rounded px-3 py-1 text-sm ${
                  link.active
                    ? "bg-emerald-600 text-white"
                    : "bg-muted text-muted-foreground hover:text-foreground"
                }`}
                dangerouslySetInnerHTML={{ __html: link.label }}
              />
            ) : (
              <span
                key={`${link.label}-${i}`}
                className="rounded px-3 py-1 text-sm text-muted-foreground/50"
                dangerouslySetInnerHTML={{ __html: link.label }}
              />
            ),
          )}
        </nav>
      )}
    </PublicLayout>
  );
};

export default NewsIndexPage;
