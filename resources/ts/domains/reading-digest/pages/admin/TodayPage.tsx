import PrivateLayout, { RootProps } from "@/ts/layouts/PrivateLayout";
import { Link } from "@inertiajs/react";
import { useRoute } from "ziggy-js";
import ReadingDigestNav from "../../components/ReadingDigestNav";
import type { RdDigestRun } from "../../../types";

type Props = RootProps & {
  run: RdDigestRun | null;
};

const TodayPage = ({ auth, run }: Props) => {
  const route = useRoute();

  return (
    <PrivateLayout auth={auth}>
      <ReadingDigestNav />
      <h1 className="text-2xl font-bold text-gray-100 mb-2">Today&apos;s Digest</h1>
      <p className="text-sm text-muted-foreground mb-4">
        Ranked picks from your subjects. Read on the original site; vote on the feedback page to train future digests.
      </p>
      {!run && (
        <p className="text-gray-400">
          No digest run for today yet. Add sources to a subject, then use Settings → Fetch &amp; send now.
        </p>
      )}
      {run?.items?.map((item) => (
        <div key={item.id} className="border border-border rounded-lg p-4 mb-4 bg-card">
          <h2 className="text-lg font-semibold text-gray-100">{item.article?.title}</h2>
          <p className="text-sm text-muted-foreground mt-1">
            {item.subject?.name}
            {item.article?.source?.name ? ` · ${item.article.source.name}` : ""}
          </p>
          {item.article?.summary && (
            <p className="text-sm text-gray-400 mt-2 line-clamp-3">{item.article.summary}</p>
          )}
          <div className="flex flex-wrap gap-4 mt-3 text-sm">
            {item.tracking_token && (
              <>
                <a
                  href={route("reading-digest.article.redirect", item.tracking_token)}
                  className="text-blue-400 hover:underline"
                >
                  📖 Read (tracked)
                </a>
                <Link
                  href={route("reading-digest.article.vote", item.tracking_token)}
                  className="text-blue-400 hover:underline"
                >
                  👍 Vote / tag
                </Link>
              </>
            )}
          </div>
        </div>
      ))}
    </PrivateLayout>
  );
};

export default TodayPage;
