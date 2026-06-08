import type { Messages } from "../types";

const en: Messages = {
  nav: {
    portfolio: "Portfolio",
    blog: "Blog",
    about: "About",
    skills: "Skills",
    contact: "Contact",
    admin: "Admin",
    apps: "Apps",
  },
  home: {
    portfolioLabel: "Portfolio",
    downloadCv: "Download CV (PDF)",
    contactMe: "Contact me",
    about: "About",
    location: "Location",
    phone: "Phone",
    education: "Education",
    skills: "Skills",
    contact: "Contact",
    interests: "Interests",
    readBlog: "Read my blog",
    latestPosts: "Latest posts",
    viewAllPosts: "View all posts",
    allPosts: "All posts",
    appsTitle: "Open-source packages",
    viewAllApps: "View all apps",
  },
  blog: {
    label: "Blog",
    latestPosts: "Latest Posts",
    taggedPosts: 'Posts tagged "{{tag}}"',
    tags: "Tags",
    noPosts: "No posts found.",
    metaDescription: "Technical articles, engineering notes, and project lessons by Nguyen Van Nhan.",
    searchPlaceholder: "Search",
    search: "Search",
    backToBlog: "Back to blog",
    article: "Article",
    series: "In series",
    sourceOriginal: "Original source:",
    editPost: "Edit post",
  },
  apps: {
    label: "Apps",
    title: "Open-source packages",
    description:
      "Open-source packages and tools I build and maintain — published on Packagist, npm, and GitHub.",
    metaDescription:
      "Open-source packages by Nguyen Van Nhan — Laravel Telegram logging, React Markdown Preview, and more.",
    featuresLabel: "Features",
    viewOnPackagist: "View on Packagist",
    viewOnNpm: "View on npm",
    viewOnGithub: "View on GitHub",
    items: {
      "laravel-telegram-logging": {
        name: "Laravel Telegram Logging",
        summary:
          "A Laravel log channel that forwards warning and error logs to Telegram. Handy for small teams that want instant alerts without a full observability stack.",
        features: [
          "Configurable minimum log level (TELEGRAM_LOG_LEVEL)",
          "Optional queue support so HTTP requests are not blocked",
          "Custom HTML message templates with placeholders",
          "Dedupe identical messages within a time window",
          "Artisan command: php artisan telegram-log:test",
        ],
      },
      "react-markdown-preview": {
        name: "React Markdown Preview",
        summary:
          "A lightweight React component for rendering Markdown with GFM, syntax highlighting, and heading anchors — used on this site's blog.",
        features: [
          "Simple API: <MarkdownPreview doc={doc} />",
          "GitHub Flavored Markdown via remark-gfm",
          "Syntax highlighting with rehype-highlight",
          "Auto-linked headings with slug anchors",
          "Bundled light/dark markdown and highlight styles",
        ],
      },
    },
  },
  common: {
    language: "Language",
  },
  cv: {
    name: "Nguyen Van Nhan",
    title: "Senior Software Developer",
    phone: "0799833537",
    email: "nguyenvannhan0810@gmail.com",
    location: "Thu Duc, Ho Chi Minh",
    summary:
      "Senior Software Developer with 8 years of experience building scalable web and mobile applications. Strong expertise in full-stack web development, mobile application development, and system architecture design. Committed to delivering reliable, high-quality solutions for international clients.",
    education: {
      school: "Ho Chi Minh University of Technology and Education (HCMUTE)",
      major: "Information Technology Engineer",
      period: "Aug 2013 – Mar 2018",
      gpa: "7.68",
    },
    languages:
      "English — reading and comprehension well; basic communication.",
    interests: ["Reading", "Music", "Movies"],
  },
};

export default en;
