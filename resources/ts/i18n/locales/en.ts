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
    title: "Open-source apps & packages",
    description:
      "Packages, libraries, and side projects I build and maintain — on Packagist, npm, and GitHub.",
    metaDescription:
      "Open-source apps and packages by Nguyen Van Nhan — FLC language learning, GL Database Client, Laravel Telegram logging, React Markdown Preview, and more.",
    featuresLabel: "Features",
    viewOnPackagist: "View on Packagist",
    viewOnNpm: "View on npm",
    viewOnGithub: "View on GitHub",
    backToApps: "Back to apps",
    viewDetails: "View details",
    items: {
      "foreign-language-course": {
        name: "FLC — Foreign Language Companion",
        summary:
          "Chrome Extension + Flutter mobile + Laravel API for learning English: English–English lookup, vocabulary, listening, quiz, and review reminders — one account, synced everywhere.",
        features: [
          "Look up words in context while browsing (Chrome extension)",
          "Save vocabulary and sync across extension & mobile",
          "Listening practice with YouTube/audio links",
          "Vocabulary & listening quizzes with spaced reminders",
        ],
      },
      "db-management-tool": {
        name: "GL Database Client",
        summary:
          "Cross-platform desktop client for MySQL and PostgreSQL — connect directly or over SSH, browse schema and data, run SQL, and import/export large scripts with progress and cancellation.",
        features: [
          "MySQL & PostgreSQL with SSH tunneling (password or private key)",
          "Multi-tab workspace: structure, data grid, SQL editor",
          "Streaming import/export for large SQL files",
          "Credentials stored in OS keychain with encrypted fallback",
        ],
      },
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
    details: {
      "foreign-language-course": {
        name: "FLC — Foreign Language Companion",
        tagline: "Chrome Extension + Flutter mobile + Laravel API",
        metaDescription:
          "FLC helps you learn English with English–English dictionary lookup, vocabulary sync, listening practice, and quizzes across Chrome extension and mobile app.",
        summary:
          "FLC is a full-stack language learning companion built around a simple loop: encounter a word → look it up in an English–English dictionary → save it → practice listening → review with quizzes. One Google account keeps your vocabulary and progress in sync between the Chrome extension and the Flutter mobile app.",
        highlights: [
          "English–English dictionary — no Vietnamese translation",
          "One account syncs data between extension and mobile",
          "Chrome notifications for quiz reminders and listening schedules",
          "Mobile push notifications (FCM) at 11:00 & 20:00 VN time",
          "Admin panel for allowlist, users, vocabulary, and media",
        ],
        learningFlow: {
          title: "Learning flow",
          intro:
            "Extension and mobile complement each other in the same learning loop:",
          loop: ["Encounter word", "Look up", "Save", "Listen", "Quiz"],
          stepColumn: "Step",
          extensionColumn: "Chrome Extension",
          mobileColumn: "Mobile app",
          steps: [
            {
              step: "Look up",
              extension:
                "Select text → right-click Look up with FLC, or popup Lookup tab",
              mobile: "Lookup tab",
            },
            {
              step: "Save",
              extension: "My Words tab",
              mobile: "Vocabulary tab (synced)",
            },
            {
              step: "Listen",
              extension: "Add YouTube/audio links, listening reminders",
              mobile: "Listen tab — YouTube/MP3, listening quiz",
            },
            {
              step: "Quiz",
              extension: "Quiz tab, Chrome notifications",
              mobile: "Quiz tab, FCM push at 11:00 & 20:00",
            },
            {
              step: "Progress",
              extension: "Options / sync",
              mobile: "Profile tab — stats & history",
            },
          ],
          note: "Requires at least 4 saved words to start a vocabulary quiz.",
        },
        whenToUse: {
          title: "Extension or mobile?",
          situationColumn: "Situation",
          suggestionColumn: "Suggestion",
          items: [
            {
              situation: "Reading web, docs, forums in Chrome",
              suggestion: "Chrome Extension",
            },
            {
              situation: "Learning on phone, quiz push reminders",
              suggestion: "Mobile app",
            },
            {
              situation: "Adding your own YouTube links to replay",
              suggestion: "Chrome Extension",
            },
            {
              situation: "Curated listening + listening quiz from admin",
              suggestion: "Mobile app",
            },
          ],
        },
        layers: {
          title: "Project components",
          items: [
            {
              name: "Backend",
              description:
                "REST API, admin panel, Google OAuth, FCM push scheduling",
              stack: "Laravel · PostgreSQL · Sanctum",
            },
            {
              name: "Chrome Extension",
              description:
                "In-page lookup, popup, vocabulary, quiz, notifications",
              stack: "Chrome MV3 · TypeScript · Vite",
            },
            {
              name: "Mobile app",
              description:
                "Lookup, vocabulary, listening, quiz, profile & stats",
              stack: "Flutter · Riverpod · Firebase Cloud Messaging",
            },
          ],
        },
        techStack: {
          title: "Tech stack",
          items: [
            { component: "Backend", tech: "Laravel · PostgreSQL · Sanctum" },
            {
              component: "Extension",
              tech: "Chrome MV3 · TypeScript · Vite",
            },
            { component: "Mobile", tech: "Flutter · Riverpod · FCM" },
            {
              component: "Dictionary",
              tech: "Free Dictionary API (dictionaryapi.dev)",
            },
          ],
        },
        repoStructure: {
          title: "Repository structure",
          items: [
            { folder: "backend/", description: "Laravel API + Admin (Sail)" },
            { folder: "extension/", description: "Chrome Extension MV3" },
            { folder: "mobile/", description: "Flutter app (iOS / Android)" },
            { folder: "docs/", description: "Documentation & screenshots" },
          ],
        },
      },
      "db-management-tool": {
        name: "GL Database Client",
        tagline: "Cross-platform desktop client for MySQL & PostgreSQL",
        metaDescription:
          "GL Database Client — Electron desktop app to connect to MySQL and PostgreSQL over SSH, browse schema, run SQL, and stream large import/export jobs.",
        summary:
          "GL Database Client is a native desktop database client built with Electron, Vue 3, and TypeScript. Connect directly or over SSH, browse schema and data in a multi-tab workspace, run SQL with history, and handle large import/export workflows without loading entire tables into memory.",
        highlights: [
          "MySQL & PostgreSQL — connect, query, introspect schema, manage tables",
          "SSH tunneling with password or private key (+ optional passphrase)",
          "Passwords and SSH secrets stored via OS keychain (keytar) with encrypted fallback",
          "Streaming export/import to disk with progress, cancel, and batch processing",
          "Multi-tab workspace with session restore and keyboard shortcuts",
        ],
        featureGroups: [
          {
            title: "Connection management",
            items: [
              "Create, edit, delete, and reorder saved connection profiles",
              "Export / import connection lists as JSON (credentials stay in OS secret store)",
              "Connect with or without a default database; switch database from workspace",
              "Optional SSH tunnel per connection (host, port, username, password or private key)",
              "Prompt for missing credentials when opening a profile without stored secrets",
              "Drop database with confirmation (system databases protected)",
            ],
          },
          {
            title: "Workspace & navigation",
            items: [
              "Home — searchable connection list with context menu (edit, delete, export)",
              "Schema browser (tables/views), multi-tab UI for tables and queries",
              "Structure view — columns, types, nullability, defaults, foreign keys, row counts",
              "Data view — paginated grid, sorting, filtering, inline editing where supported",
              "Open related rows in a new tab from foreign key cells",
              "Optional row detail sidebar; SQL history panel with success/error status",
              "Session restore — reopen recent workspace tabs (passwords not persisted in localStorage)",
            ],
          },
          {
            title: "SQL editor",
            items: [
              "Dedicated query tab per connection",
              "Run full script or selected text (Ctrl/Cmd + Enter)",
              "Result grid with row counts and execution feedback",
              "Bottom SQL history across query and table operations",
            ],
          },
          {
            title: "Import & export",
            items: [
              "Export tables to SQL: structure and data, structure only, or data only",
              "Stream export directly to disk — avoids loading entire tables into memory",
              "Batch row export with progress events per table",
              "Import SQL with streaming parser (comments, quoted strings, PostgreSQL dollar-quotes)",
              "Cancel long-running import/export jobs; drop table (table vs view aware)",
            ],
          },
          {
            title: "Security & privacy",
            items: [
              "Database and SSH passwords in macOS Keychain / system credential store via keytar",
              "Master key and encrypted file fallback when keychain is unavailable",
              "Workspace state in localStorage redacts passwords and SSH keys",
              "Connection errors in main-process logs redact sensitive fields",
              "IPC uses strict preload allowlist (contextIsolation, no Node in renderer)",
            ],
          },
        ],
        shortcuts: {
          title: "Keyboard shortcuts",
          shortcutColumn: "Shortcut",
          actionColumn: "Action",
          items: [
            {
              shortcut: "Ctrl/Cmd + R",
              action: "Reload active connection (tables + open tabs)",
            },
            {
              shortcut: "Ctrl/Cmd + Shift + R",
              action: "Reload current tab data only",
            },
            { shortcut: "Ctrl/Cmd + S", action: "Save pending row edits" },
            { shortcut: "Ctrl/Cmd + K", action: "Focus table search" },
            { shortcut: "Ctrl/Cmd + W", action: "Close active tab" },
            { shortcut: "Ctrl/Cmd + Enter", action: "Run query (query editor)" },
            {
              shortcut: "Ctrl/Cmd + A",
              action: "Select all filtered tables (sidebar)",
            },
          ],
        },
        layers: {
          title: "Architecture",
          description:
            "Layered layout: domain types, infrastructure (database drivers, storage, IPC), and presentation (Vue components and stores). MySQL and PostgreSQL each have a dedicated driver; DatabaseService orchestrates connections, SSH tunnels, and IPC-facing APIs.",
          items: [
            {
              name: "Domain",
              description: "Connection & query types, pure logic",
              stack: "TypeScript",
            },
            {
              name: "Infrastructure",
              description: "DatabaseService, SQL splitter, MySQL/PG drivers, storage, IPC",
              stack: "mysql2 · pg · ssh2 · keytar",
            },
            {
              name: "Presentation",
              description: "Vue views, components, Pinia stores",
              stack: "Vue 3 · Element Plus · Pinia",
            },
          ],
        },
        techStack: {
          title: "Tech stack",
          items: [
            {
              component: "Desktop shell",
              tech: "Electron 40 · Electron Forge · Vite",
            },
            {
              component: "UI",
              tech: "Vue 3 · Pinia · Vue Router · Element Plus",
            },
            { component: "Language", tech: "TypeScript" },
            { component: "Databases", tech: "mysql2 · pg" },
            { component: "SSH", tech: "ssh2" },
            { component: "Secrets", tech: "keytar (native)" },
          ],
        },
        repoStructure: {
          title: "Project layout",
          items: [
            {
              folder: "src/domain/",
              description: "Connection & query types, pure logic",
            },
            {
              folder: "src/infrastructure/",
              description: "DatabaseService, drivers, storage, IPC",
            },
            {
              folder: "src/presentation/",
              description: "Vue views, components, Pinia stores",
            },
            { folder: "docs/screenshots/", description: "README screenshots" },
            {
              folder: "db-init/",
              description: "Docker init SQL + seed scripts for local testing",
            },
            { folder: "scripts/", description: "Packaging helpers" },
          ],
        },
        platformsNote:
          "Built and tested primarily on macOS (arm64). Electron Forge also targets Windows (Squirrel) and Linux (deb/rpm/zip). Native modules require a rebuild per platform.",
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
