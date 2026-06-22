import LocaleSwitcher from "@/ts/components/common/LocaleSwitcher";
import SeoHead from "@/ts/components/common/SeoHead";
import PostListItem from "@/ts/components/posts/PostListItem";
import TechStackGrid from "@/ts/components/portfolio/TechStackGrid";
import { appsCatalog } from "@/ts/constants/apps";
import { profile } from "@/ts/constants/profile";
import { useTranslation } from "@/ts/providers/i18n-provider";
import PortfolioLayout from "@/ts/layouts/PortfolioLayout";
import type { Post } from "@/ts/types/post";
import { Link } from "@inertiajs/react";
import {
  ArrowRight,
  BookOpen,
  Github,
  Linkedin,
  Mail,
  MapPin,
  Phone,
} from "lucide-react";
import { useMemo } from "react";
import { useRoute } from "ziggy-js";

type Props = {
  posts: Post[];
};

const HomePage = ({ posts }: Props) => {
  const route = useRoute();
  const { cv, t, locale, messages } = useTranslation();

  const navItems = useMemo(
    () => [
      { id: "about", label: t("nav.about") },
      { id: "skills", label: t("nav.skills") },
      { id: "apps", label: t("nav.apps") },
      { id: "blog", label: t("nav.blog") },
      { id: "contact", label: t("nav.contact") },
    ],
    [t]
  );

  return (
    <PortfolioLayout>
      <SeoHead
        title={`${cv.name} — ${cv.title}`}
        description={cv.summary}
        url={route("home", undefined, true)}
        imageUrl={`/og/home.png?locale=${locale}`}
        locale={locale === "vi" ? "vi_VN" : "en_US"}
        imageAlt={`${cv.name} — ${cv.title}`}
      />
      <nav className="sticky top-0 z-50 border-b border-border/60 bg-background/80 backdrop-blur-md">
        <div className="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
          <a href="#top" className="text-sm font-semibold tracking-tight">
            {cv.name}
          </a>
          <div className="hidden items-center gap-4 text-sm text-muted-foreground sm:flex">
            {navItems.map((item) => (
              <a
                key={item.id}
                href={`#${item.id}`}
                className="transition-colors hover:text-foreground"
              >
                {item.label}
              </a>
            ))}
            <Link
              href={route("posts.index")}
              className="inline-flex items-center gap-1.5 text-emerald-500 transition-colors hover:text-emerald-400"
            >
              <BookOpen className="h-4 w-4" />
              {t("home.allPosts")}
            </Link>
            <LocaleSwitcher />
          </div>
          <div className="flex items-center gap-3 sm:hidden">
            <Link
              href={route("posts.index")}
              className="inline-flex items-center gap-1.5 text-sm text-emerald-500"
            >
              <BookOpen className="h-4 w-4" />
              {t("home.allPosts")}
            </Link>
            <LocaleSwitcher />
          </div>
        </div>
      </nav>

      <main id="top" className="mx-auto max-w-5xl px-4 pb-16 pt-12 sm:px-6 sm:pt-16">
        <section className="mb-20 text-center sm:text-left">
          <p className="mb-3 text-sm font-medium uppercase tracking-widest text-emerald-500">
            {t("home.portfolioLabel")}
          </p>
          <h1 className="text-4xl font-bold tracking-tight sm:text-5xl lg:text-6xl">
            {cv.name}
          </h1>
          <p className="mt-3 text-xl text-muted-foreground sm:text-2xl">
            {cv.title}
          </p>
          <p className="mx-auto mt-6 max-w-2xl text-base leading-relaxed text-muted-foreground sm:mx-0">
            {cv.summary}
          </p>
          <div className="mt-8 flex flex-wrap items-center justify-center gap-3 sm:justify-start">
            <a
              href={`mailto:${cv.email}`}
              className="inline-flex items-center gap-2 rounded-md border border-border px-4 py-2 text-sm transition-colors hover:bg-muted"
            >
              <Mail className="h-4 w-4" />
              {t("home.contactMe")}
            </a>
            <a
              href={profile.githubLink}
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex items-center gap-2 rounded-md border border-border px-4 py-2 text-sm transition-colors hover:bg-muted"
            >
              <Github className="h-4 w-4" />
              GitHub
            </a>
            <a
              href={profile.linkedinLink}
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex items-center gap-2 rounded-md border border-border px-4 py-2 text-sm transition-colors hover:bg-muted"
            >
              <Linkedin className="h-4 w-4" />
              LinkedIn
            </a>
          </div>
        </section>

        <section id="about" className="mb-20 scroll-mt-20">
          <h2 className="mb-6 text-2xl font-bold">{t("home.about")}</h2>
          <div className="grid gap-4 sm:grid-cols-2">
            <div className="rounded-xl border border-border bg-card p-5">
              <p className="text-sm text-muted-foreground">{t("home.location")}</p>
              <p className="mt-1 flex items-center gap-2 font-medium">
                <MapPin className="h-4 w-4 text-emerald-500" />
                {cv.location}
              </p>
            </div>
            <div className="rounded-xl border border-border bg-card p-5">
              <p className="text-sm text-muted-foreground">{t("home.phone")}</p>
              <p className="mt-1 flex items-center gap-2 font-medium">
                <Phone className="h-4 w-4 text-emerald-500" />
                {cv.phone}
              </p>
            </div>
            <div className="rounded-xl border border-border bg-card p-5 sm:col-span-2">
              <p className="text-sm text-muted-foreground">{t("home.education")}</p>
              <p className="mt-1 font-medium">{cv.education.school}</p>
              <p className="mt-1 text-sm text-muted-foreground">
                {cv.education.major} · {cv.education.period} · GPA{" "}
                {cv.education.gpa}
              </p>
            </div>
          </div>
        </section>

        <section id="skills" className="mb-20 scroll-mt-20">
          <h2 className="mb-6 text-2xl font-bold">{t("home.skills")}</h2>
          <TechStackGrid />
        </section>

        <section id="apps" className="mb-20 scroll-mt-20">
          <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <p className="mb-2 text-sm font-medium uppercase tracking-widest text-emerald-500">
                {t("apps.label")}
              </p>
              <h2 className="text-2xl font-bold">{t("home.appsTitle")}</h2>
              <p className="mt-2 max-w-2xl text-sm text-muted-foreground">
                {t("apps.description")}
              </p>
            </div>
            <Link
              href={route("apps.index")}
              className="inline-flex shrink-0 items-center gap-1.5 text-sm text-emerald-500 transition-colors hover:text-emerald-400"
            >
              {t("home.viewAllApps")}
              <ArrowRight className="h-4 w-4" />
            </Link>
          </div>
          <div className="grid gap-5 md:grid-cols-2">
            {appsCatalog.map((app) => {
              const content = messages.apps.items[app.slug];
              const href =
                app.kind === "project"
                  ? route("apps.show", { slug: app.slug })
                  : route("apps.index");

              return (
                <Link
                  key={app.slug}
                  href={href}
                  className="group rounded-xl border border-border bg-card p-5 transition-colors hover:border-emerald-600/40 hover:bg-emerald-600/5"
                >
                  <p className="text-xs text-muted-foreground">{app.label}</p>
                  <h3 className="mt-2 text-lg font-semibold text-foreground transition-colors group-hover:text-emerald-500">
                    {content.name}
                  </h3>
                  <p className="mt-2 line-clamp-3 text-sm leading-relaxed text-muted-foreground">
                    {content.summary}
                  </p>
                  <div className="mt-4 flex flex-wrap gap-2">
                    {app.tags.map((tag) => (
                      <span
                        key={tag}
                        className="rounded-full border border-emerald-600/30 bg-emerald-600/10 px-2 py-0.5 text-xs font-medium text-emerald-500"
                      >
                        {tag}
                      </span>
                    ))}
                  </div>
                </Link>
              );
            })}
          </div>
        </section>

        <section id="blog" className="mb-20 scroll-mt-20">
          <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <p className="mb-2 text-sm font-medium uppercase tracking-widest text-emerald-500">
                {t("blog.label")}
              </p>
              <h2 className="text-2xl font-bold">{t("home.latestPosts")}</h2>
            </div>
            <Link
              href={route("posts.index")}
              className="inline-flex items-center gap-1.5 text-sm text-emerald-500 transition-colors hover:text-emerald-400"
            >
              {t("home.viewAllPosts")}
              <ArrowRight className="h-4 w-4" />
            </Link>
          </div>
          {posts.length > 0 ? (
            <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
              {posts.map((post) => (
                <PostListItem key={post.id} post={post} />
              ))}
            </div>
          ) : (
            <div className="rounded-xl border border-border bg-card py-16 text-center">
              <p className="text-muted-foreground">{t("blog.noPosts")}</p>
            </div>
          )}
        </section>

        <section id="contact" className="scroll-mt-20">
          <h2 className="mb-6 text-2xl font-bold">{t("home.contact")}</h2>
          <div className="rounded-xl border border-border bg-card p-6">
            <p className="text-muted-foreground">{cv.languages}</p>
            <p className="mt-3 text-sm text-muted-foreground">
              {t("home.interests")}: {cv.interests.join(" · ")}
            </p>
            <div className="mt-6 flex flex-wrap gap-4">
              <a
                href={`mailto:${cv.email}`}
                className="text-sm text-foreground hover:text-emerald-500"
              >
                {cv.email}
              </a>
              <Link
                href={route("posts.index")}
                className="text-sm text-emerald-500 hover:text-emerald-400"
              >
                {t("home.readBlog")} →
              </Link>
            </div>
          </div>
        </section>

        <footer className="mt-16 border-t border-border pt-8 text-center text-sm text-muted-foreground">
          © {new Date().getFullYear()} {cv.name}
        </footer>
      </main>
    </PortfolioLayout>
  );
};

export default HomePage;
