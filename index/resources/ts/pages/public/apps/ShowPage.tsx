import SeoHead from "@/ts/components/common/SeoHead";
import { getAppBySlug } from "@/ts/constants/apps";
import PublicLayout, { type RootProps } from "@/ts/layouts/PublicLayout";
import { useTranslation } from "@/ts/providers/i18n-provider";
import { Link } from "@inertiajs/react";
import { ArrowLeft, ExternalLink, Github } from "lucide-react";
import { useRoute } from "ziggy-js";

type Props = RootProps & {
  slug: string;
};

const ShowPage = ({ auth, locale, slug }: Props) => {
  const route = useRoute();
  const { t, messages } = useTranslation();
  const app = getAppBySlug(slug);
  const detail = messages.apps.details[slug];

  if (!app || !detail) {
    return null;
  }

  return (
    <PublicLayout auth={auth} locale={locale} active="apps">
      <SeoHead
        title={`${detail.name} | ${t("apps.label")}`}
        description={detail.metaDescription}
        url={route("apps.show", { slug }, true)}
        locale={locale === "vi" ? "vi_VN" : "en_US"}
      />

      <Link
        href={route("apps.index")}
        className="mb-8 inline-flex items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-emerald-500"
      >
        <ArrowLeft className="h-4 w-4" />
        {t("apps.backToApps")}
      </Link>

      <header className="mb-10">
        <p className="mb-2 text-sm font-medium uppercase tracking-widest text-emerald-500">
          {t("apps.label")}
        </p>
        <h1 className="text-3xl font-extrabold tracking-tight text-foreground md:text-4xl">
          {detail.name}
        </h1>
        <p className="mt-2 text-lg text-muted-foreground">{detail.tagline}</p>
        <div className="mt-4 flex flex-wrap gap-2">
          {app.tags.map((tag) => (
            <span
              key={tag}
              className="rounded-full border border-emerald-600/30 bg-emerald-600/10 px-2.5 py-0.5 text-xs font-medium text-emerald-500"
            >
              {tag}
            </span>
          ))}
        </div>
      </header>

      <div className="space-y-10">
        <section className="rounded-xl border border-border bg-card p-6 md:p-8">
          <p className="leading-relaxed text-muted-foreground">{detail.summary}</p>
          <ul className="mt-5 list-inside list-disc space-y-1.5 text-sm text-muted-foreground">
            {detail.highlights.map((item) => (
              <li key={item}>{item}</li>
            ))}
          </ul>
        </section>

        {detail.featureGroups?.map((group) => (
          <section key={group.title}>
            <h2 className="mb-4 text-xl font-bold">{group.title}</h2>
            <ul className="list-inside list-disc space-y-1.5 rounded-xl border border-border bg-card px-6 py-5 text-sm text-muted-foreground">
              {group.items.map((item) => (
                <li key={item}>{item}</li>
              ))}
            </ul>
          </section>
        ))}

        {detail.learningFlow && (
          <section>
            <h2 className="mb-2 text-xl font-bold">{detail.learningFlow.title}</h2>
            <p className="mb-4 text-sm text-muted-foreground">
              {detail.learningFlow.intro}
            </p>
            <div className="mb-6 flex flex-wrap items-center gap-2 text-sm font-medium text-emerald-500">
              {detail.learningFlow.loop.map((step, index) => (
                <span key={step} className="inline-flex items-center gap-2">
                  {index > 0 && (
                    <span className="text-muted-foreground">→</span>
                  )}
                  <span className="rounded-md border border-emerald-600/30 bg-emerald-600/10 px-2.5 py-1">
                    {step}
                  </span>
                </span>
              ))}
            </div>
            <div className="overflow-x-auto rounded-xl border border-border">
              <table className="w-full min-w-[640px] text-sm">
                <thead className="bg-muted/50 text-left">
                  <tr>
                    <th className="px-4 py-3 font-semibold">
                      {detail.learningFlow.stepColumn}
                    </th>
                    <th className="px-4 py-3 font-semibold">
                      {detail.learningFlow.extensionColumn}
                    </th>
                    <th className="px-4 py-3 font-semibold">
                      {detail.learningFlow.mobileColumn}
                    </th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border">
                  {detail.learningFlow.steps.map((row) => (
                    <tr key={row.step} className="bg-card">
                      <td className="px-4 py-3 font-medium">{row.step}</td>
                      <td className="px-4 py-3 text-muted-foreground">
                        {row.extension}
                      </td>
                      <td className="px-4 py-3 text-muted-foreground">
                        {row.mobile}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <p className="mt-3 text-sm text-muted-foreground">
              {detail.learningFlow.note}
            </p>
          </section>
        )}

        {detail.whenToUse && (
          <section>
            <h2 className="mb-4 text-xl font-bold">{detail.whenToUse.title}</h2>
            <div className="overflow-x-auto rounded-xl border border-border">
              <table className="w-full min-w-[480px] text-sm">
                <thead className="bg-muted/50 text-left">
                  <tr>
                    <th className="px-4 py-3 font-semibold">
                      {detail.whenToUse.situationColumn}
                    </th>
                    <th className="px-4 py-3 font-semibold">
                      {detail.whenToUse.suggestionColumn}
                    </th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border">
                  {detail.whenToUse.items.map((row) => (
                    <tr key={row.situation} className="bg-card">
                      <td className="px-4 py-3 text-muted-foreground">
                        {row.situation}
                      </td>
                      <td className="px-4 py-3 font-medium">{row.suggestion}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>
        )}

        {detail.shortcuts && (
          <section>
            <h2 className="mb-4 text-xl font-bold">{detail.shortcuts.title}</h2>
            <div className="overflow-x-auto rounded-xl border border-border">
              <table className="w-full min-w-[480px] text-sm">
                <thead className="bg-muted/50 text-left">
                  <tr>
                    <th className="px-4 py-3 font-semibold">
                      {detail.shortcuts.shortcutColumn}
                    </th>
                    <th className="px-4 py-3 font-semibold">
                      {detail.shortcuts.actionColumn}
                    </th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border">
                  {detail.shortcuts.items.map((row) => (
                    <tr key={row.shortcut} className="bg-card">
                      <td className="px-4 py-3 font-mono text-emerald-500">
                        {row.shortcut}
                      </td>
                      <td className="px-4 py-3 text-muted-foreground">
                        {row.action}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>
        )}

        {detail.layers && (
          <section>
            <h2 className="mb-2 text-xl font-bold">{detail.layers.title}</h2>
            {detail.layers.description && (
              <p className="mb-4 text-sm text-muted-foreground">
                {detail.layers.description}
              </p>
            )}
            <div className="grid gap-4 md:grid-cols-3">
              {detail.layers.items.map((layer) => (
                <div
                  key={layer.name}
                  className="rounded-xl border border-border bg-card p-5"
                >
                  <h3 className="font-semibold text-foreground">{layer.name}</h3>
                  <p className="mt-2 text-sm text-muted-foreground">
                    {layer.description}
                  </p>
                  <p className="mt-3 font-mono text-xs text-emerald-500">
                    {layer.stack}
                  </p>
                </div>
              ))}
            </div>
          </section>
        )}

        <section>
          <h2 className="mb-4 text-xl font-bold">{detail.techStack.title}</h2>
          <div className="overflow-x-auto rounded-xl border border-border">
            <table className="w-full text-sm">
              <tbody className="divide-y divide-border">
                {detail.techStack.items.map((row) => (
                  <tr key={row.component} className="bg-card">
                    <td className="px-4 py-3 font-medium">{row.component}</td>
                    <td className="px-4 py-3 text-muted-foreground">{row.tech}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>

        <section>
          <h2 className="mb-4 text-xl font-bold">{detail.repoStructure.title}</h2>
          <div className="divide-y divide-border rounded-xl border border-border bg-card">
            {detail.repoStructure.items.map((row) => (
              <div
                key={row.folder}
                className="flex flex-col gap-1 px-5 py-4 sm:flex-row sm:items-center sm:gap-4"
              >
                <code className="shrink-0 font-mono text-sm text-emerald-500">
                  {row.folder}
                </code>
                <span className="text-sm text-muted-foreground">
                  {row.description}
                </span>
              </div>
            ))}
          </div>
        </section>

        {detail.platformsNote && (
          <p className="text-sm text-muted-foreground">{detail.platformsNote}</p>
        )}

        <div className="pt-2">
          <a
            href={app.githubUrl}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-medium text-white transition-colors hover:bg-emerald-500"
          >
            <Github className="h-4 w-4" />
            {t("apps.viewOnGithub")}
            <ExternalLink className="h-3.5 w-3.5 opacity-80" />
          </a>
        </div>
      </div>
    </PublicLayout>
  );
};

export default ShowPage;
