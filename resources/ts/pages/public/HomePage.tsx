import LocaleSwitcher from "@/ts/components/common/LocaleSwitcher";
import SeoHead from "@/ts/components/common/SeoHead";
import { profile } from "@/ts/constants/profile";
import { useTranslation } from "@/ts/providers/i18n-provider";
import PortfolioLayout from "@/ts/layouts/PortfolioLayout";
import { Link } from "@inertiajs/react";
import {
  BookOpen,
  FileDown,
  Github,
  Linkedin,
  Mail,
  MapPin,
  Phone,
} from "lucide-react";
import { useMemo } from "react";
import { useRoute } from "ziggy-js";

const SkillBar = ({ name, level }: { name: string; level: number }) => (
  <div>
    <div className="mb-1 flex justify-between text-sm">
      <span className="text-foreground">{name}</span>
      <span className="text-muted-foreground">{level}/5</span>
    </div>
    <div className="h-2 overflow-hidden rounded-full bg-muted">
      <div
        className="h-full rounded-full bg-emerald-600 transition-all"
        style={{ width: `${(level / 5) * 100}%` }}
      />
    </div>
  </div>
);

const HomePage = () => {
  const route = useRoute();
  const { cv, t } = useTranslation();

  const navItems = useMemo(
    () => [
      { id: "about", label: t("nav.about") },
      { id: "skills", label: t("nav.skills") },
      { id: "experience", label: t("nav.experience") },
      { id: "projects", label: t("nav.projects") },
      { id: "contact", label: t("nav.contact") },
    ],
    [t]
  );

  return (
    <PortfolioLayout>
      <SeoHead
        title={`${cv.name} - ${cv.title}`}
        description={cv.summary}
        url={route("home", undefined, true)}
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
              {t("nav.blog")}
            </Link>
            <LocaleSwitcher />
          </div>
          <div className="flex items-center gap-3 sm:hidden">
            <Link
              href={route("posts.index")}
              className="inline-flex items-center gap-1.5 text-sm text-emerald-500"
            >
              <BookOpen className="h-4 w-4" />
              {t("nav.blog")}
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
              href={profile.cvPdfPath}
              download="[Senior_Developer]_Nguyen_Van_Nhan.pdf"
              className="inline-flex items-center gap-2 rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-emerald-500"
            >
              <FileDown className="h-4 w-4" />
              {t("home.downloadCv")}
            </a>
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
          <div className="grid gap-4 sm:grid-cols-2">
            {cv.skills.map((skill) => (
              <SkillBar key={skill.name} name={skill.name} level={skill.level} />
            ))}
          </div>
        </section>

        <section id="experience" className="mb-20 scroll-mt-20">
          <h2 className="mb-6 text-2xl font-bold">{t("home.workExperience")}</h2>
          <div className="space-y-6">
            {cv.experience.map((job) => (
              <article
                key={`${job.company}-${job.period}`}
                className="relative rounded-xl border border-border bg-card p-5 pl-6 sm:pl-8"
              >
                <span className="absolute left-3 top-6 h-2 w-2 rounded-full bg-emerald-500 sm:left-4" />
                <div className="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                  <h3 className="text-lg font-semibold">{job.company}</h3>
                  <span className="text-sm text-emerald-500">{job.period}</span>
                </div>
                <ul className="mt-3 list-disc space-y-1.5 pl-5 text-sm leading-relaxed text-muted-foreground">
                  {job.highlights.map((item) => (
                    <li key={item}>{item}</li>
                  ))}
                </ul>
              </article>
            ))}
          </div>
        </section>

        <section id="projects" className="mb-20 scroll-mt-20">
          <h2 className="mb-6 text-2xl font-bold">{t("home.projects")}</h2>
          <div className="grid gap-4 sm:grid-cols-2">
            {cv.projects.map((project) => (
              <article
                key={project.name}
                className="flex flex-col rounded-xl border border-border bg-card p-5"
              >
                <h3 className="font-semibold">{project.name}</h3>
                <p className="mt-2 flex-1 text-sm leading-relaxed text-muted-foreground">
                  {project.description}
                </p>
                <div className="mt-3 flex flex-wrap gap-2">
                  {project.stack.map((tech) => (
                    <span
                      key={tech}
                      className="rounded-full bg-muted px-2.5 py-0.5 text-xs text-muted-foreground"
                    >
                      {tech}
                    </span>
                  ))}
                </div>
                {project.links && (
                  <div className="mt-3 flex flex-wrap gap-3">
                    {project.links.map((link) => (
                      <a
                        key={link.href}
                        href={link.href}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-sm text-emerald-500 hover:text-emerald-400"
                      >
                        {link.label} →
                      </a>
                    ))}
                  </div>
                )}
              </article>
            ))}
          </div>
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
              <a
                href={profile.cvPdfPath}
                download="Nguyen-Van-Nhan-CV.pdf"
                className="inline-flex items-center gap-1.5 text-sm text-emerald-500 hover:text-emerald-400"
              >
                <FileDown className="h-4 w-4" />
                {t("home.downloadCv")}
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
