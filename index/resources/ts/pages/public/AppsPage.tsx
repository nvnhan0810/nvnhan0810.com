import SeoHead from "@/ts/components/common/SeoHead";
import { appsCatalog } from "@/ts/constants/apps";
import PublicLayout, { type RootProps } from "@/ts/layouts/PublicLayout";
import { useTranslation } from "@/ts/providers/i18n-provider";
import { Link } from "@inertiajs/react";
import { ArrowRight, ExternalLink, Github, Package } from "lucide-react";
import { useRoute } from "ziggy-js";

const AppsPage = ({ auth, locale }: RootProps) => {
  const route = useRoute();
  const { t, messages } = useTranslation();

  return (
    <PublicLayout auth={auth} locale={locale} active="apps">
      <SeoHead
        title={`${t("apps.title")} | ${t("apps.label")}`}
        description={t("apps.metaDescription")}
        url={route("apps.index", undefined, true)}
        locale={locale === "vi" ? "vi_VN" : "en_US"}
      />
      <header className="mb-10">
        <p className="mb-2 text-sm font-medium uppercase tracking-widest text-emerald-500">
          {t("apps.label")}
        </p>
        <h1 className="text-3xl font-extrabold tracking-tight text-foreground md:text-4xl">
          {t("apps.title")}
        </h1>
        <p className="mt-3 max-w-2xl text-muted-foreground">
          {t("apps.description")}
        </p>
      </header>

      <div className="grid gap-6">
        {appsCatalog.map((app) => {
          const content = messages.apps.items[app.slug];
          const detailHref =
            app.kind === "project"
              ? route("apps.show", { slug: app.slug })
              : null;

          return (
            <article
              key={app.slug}
              className="overflow-hidden rounded-xl border border-border bg-card"
            >
              <div className="border-b border-border bg-muted/30 px-6 py-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                  <div>
                    <h2 className="text-xl font-bold text-foreground">
                      {content.name}
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                      {app.label}
                    </p>
                  </div>
                  <div className="flex flex-wrap gap-2">
                    {app.tags.map((tag) => (
                      <span
                        key={tag}
                        className="rounded-full border border-emerald-600/30 bg-emerald-600/10 px-2.5 py-0.5 text-xs font-medium text-emerald-500"
                      >
                        {tag}
                      </span>
                    ))}
                  </div>
                </div>
              </div>

              <div className="space-y-5 px-6 py-5">
                <p className="leading-relaxed text-muted-foreground">
                  {content.summary}
                </p>

                <div>
                  <h3 className="mb-2 text-sm font-semibold uppercase tracking-wide text-foreground">
                    {t("apps.featuresLabel")}
                  </h3>
                  <ul className="list-inside list-disc space-y-1 text-sm text-muted-foreground">
                    {content.features.map((feature) => (
                      <li key={feature}>{feature}</li>
                    ))}
                  </ul>
                </div>

                <div className="flex flex-wrap gap-3 pt-1">
                  {detailHref && (
                    <Link
                      href={detailHref}
                      className="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-emerald-500"
                    >
                      {t("apps.viewDetails")}
                      <ArrowRight className="h-4 w-4" />
                    </Link>
                  )}
                  {app.packagistUrl && (
                    <a
                      href={app.packagistUrl}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="inline-flex items-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground transition-colors hover:border-emerald-600/40 hover:bg-emerald-600/5 hover:text-emerald-500"
                    >
                      <Package className="h-4 w-4" />
                      {t("apps.viewOnPackagist")}
                      <ExternalLink className="h-3.5 w-3.5 opacity-60" />
                    </a>
                  )}
                  {app.npmUrl && (
                    <a
                      href={app.npmUrl}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="inline-flex items-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground transition-colors hover:border-emerald-600/40 hover:bg-emerald-600/5 hover:text-emerald-500"
                    >
                      <Package className="h-4 w-4" />
                      {t("apps.viewOnNpm")}
                      <ExternalLink className="h-3.5 w-3.5 opacity-60" />
                    </a>
                  )}
                  <a
                    href={app.githubUrl}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex items-center gap-2 rounded-lg border border-border px-4 py-2 text-sm font-medium text-foreground transition-colors hover:border-emerald-600/40 hover:bg-emerald-600/5 hover:text-emerald-500"
                  >
                    <Github className="h-4 w-4" />
                    {t("apps.viewOnGithub")}
                    <ExternalLink className="h-3.5 w-3.5 opacity-60" />
                  </a>
                </div>
              </div>
            </article>
          );
        })}
      </div>
    </PublicLayout>
  );
};

export default AppsPage;
