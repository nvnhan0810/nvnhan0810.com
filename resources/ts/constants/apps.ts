export type AppKind = "package" | "project";

export type AppCatalogItem = {
  slug: string;
  kind: AppKind;
  label: string;
  githubUrl: string;
  packagistUrl?: string;
  npmUrl?: string;
  tags: string[];
};

export const appsCatalog: AppCatalogItem[] = [
  {
    slug: "foreign-language-course",
    kind: "project",
    label: "FLC — Foreign Language Companion",
    githubUrl: "https://github.com/nvnhan0810/foreign-language-course",
    tags: ["Laravel", "Flutter", "Chrome Extension"],
  },
  {
    slug: "db-management-tool",
    kind: "project",
    label: "GL Database Client",
    githubUrl: "https://github.com/nvnhan0810/db-management-tool",
    tags: ["Electron", "Vue", "MySQL", "PostgreSQL"],
  },
  {
    slug: "laravel-telegram-logging",
    kind: "package",
    label: "nvnhan0810/laravel-telegram-logging",
    githubUrl: "https://github.com/nvnhan0810/laravel-telegram-logging",
    packagistUrl:
      "https://packagist.org/packages/nvnhan0810/laravel-telegram-logging",
    tags: ["Laravel", "Logging", "Telegram"],
  },
  {
    slug: "react-markdown-preview",
    kind: "package",
    label: "react-markdown-preview",
    githubUrl: "https://github.com/nvnhan0810/react-markdown-preview",
    npmUrl: "https://www.npmjs.com/package/react-markdown-preview",
    tags: ["React", "Markdown", "TypeScript"],
  },
];

export const projectAppSlugs = appsCatalog
  .filter((app) => app.kind === "project")
  .map((app) => app.slug);

export const getAppBySlug = (slug: string): AppCatalogItem | undefined =>
  appsCatalog.find((app) => app.slug === slug);
