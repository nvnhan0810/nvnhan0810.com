export type AppCatalogItem = {
  slug: string;
  packageName: string;
  githubUrl: string;
  packagistUrl?: string;
  npmUrl?: string;
  tags: string[];
};

export const appsCatalog: AppCatalogItem[] = [
  {
    slug: "laravel-telegram-logging",
    packageName: "nvnhan0810/laravel-telegram-logging",
    githubUrl: "https://github.com/nvnhan0810/laravel-telegram-logging",
    packagistUrl:
      "https://packagist.org/packages/nvnhan0810/laravel-telegram-logging",
    tags: ["Laravel", "Logging", "Telegram"],
  },
  {
    slug: "react-markdown-preview",
    packageName: "react-markdown-preview",
    githubUrl: "https://github.com/nvnhan0810/react-markdown-preview",
    npmUrl: "https://www.npmjs.com/package/react-markdown-preview",
    tags: ["React", "Markdown", "TypeScript"],
  },
];
