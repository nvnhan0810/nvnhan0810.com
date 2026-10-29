import type { Portfolio } from '../../domain/entities/portfolio'

// Copied from index/resources/ts/constants and i18n/locales on 2026-10-29.
// Blog posts were server props in Laravel; no database content is bundled here.
export const portfolioData = {
  messages: {
    en: {
      nav: {
        portfolio: 'Portfolio',
        blog: 'Blog',
        about: 'About',
        skills: 'Skills',
        contact: 'Contact',
        admin: 'Admin',
        apps: 'Apps',
        news: 'News',
        todosApp: 'Todo',
      },
      home: {
        portfolioLabel: 'Portfolio',
        contactMe: 'Contact me',
        about: 'About',
        location: 'Location',
        phone: 'Phone',
        education: 'Education',
        skills: 'Skills',
        contact: 'Contact',
        interests: 'Interests',
        readBlog: 'Read my blog',
        latestPosts: 'Latest posts',
        viewAllPosts: 'View all posts',
        allPosts: 'All posts',
        appsTitle: 'Open-source packages',
        viewAllApps: 'View all apps',
      },
      blog: {
        label: 'Blog',
        noPosts: 'No posts found.',
      },
      apps: {
        label: 'Apps',
        description:
          'Packages, libraries, and side projects I build and maintain — on Packagist, npm, and GitHub.',
        items: {
          'foreign-language-course': {
            name: 'FLC — Foreign Language Companion',
            summary:
              'Chrome Extension + Flutter mobile + Laravel API for learning English: English–English lookup, vocabulary, listening, quiz, and review reminders — one account, synced everywhere.',
            features: [
              'Look up words in context while browsing (Chrome extension)',
              'Save vocabulary and sync across extension & mobile',
              'Listening practice with YouTube/audio links',
              'Vocabulary & listening quizzes with spaced reminders',
            ],
          },
          'db-management-tool': {
            name: 'GL Database Client',
            summary:
              'Cross-platform desktop client for MySQL and PostgreSQL — connect directly or over SSH, browse schema and data, run SQL, and import/export large scripts with progress and cancellation.',
            features: [
              'MySQL & PostgreSQL with SSH tunneling (password or private key)',
              'Multi-tab workspace: structure, data grid, SQL editor',
              'Streaming import/export for large SQL files',
              'Credentials stored in OS keychain with encrypted fallback',
            ],
          },
          'laravel-telegram-logging': {
            name: 'Laravel Telegram Logging',
            summary:
              'A Laravel log channel that forwards warning and error logs to Telegram. Handy for small teams that want instant alerts without a full observability stack.',
            features: [
              'Configurable minimum log level (TELEGRAM_LOG_LEVEL)',
              'Optional queue support so HTTP requests are not blocked',
              'Custom HTML message templates with placeholders',
              'Dedupe identical messages within a time window',
              'Artisan command: php artisan telegram-log:test',
            ],
          },
          'react-markdown-preview': {
            name: 'React Markdown Preview',
            summary:
              "A lightweight React component for rendering Markdown with GFM, syntax highlighting, and heading anchors — used on this site's blog.",
            features: [
              'Simple API: <MarkdownPreview doc={doc} />',
              'GitHub Flavored Markdown via remark-gfm',
              'Syntax highlighting with rehype-highlight',
              'Auto-linked headings with slug anchors',
              'Bundled light/dark markdown and highlight styles',
            ],
          },
        },
      },
      common: {
        language: 'Language',
      },
      cv: {
        name: 'Nguyen Van Nhan',
        title: 'Software Developer',
        phone: '0799833537',
        email: 'nguyenvannhan0810@gmail.com',
        location: 'Thu Duc, Ho Chi Minh',
        summary:
          'Software Developer with 8 years of experience building scalable web and mobile applications. Strong expertise in full-stack web development, mobile application development, and system architecture design. Committed to delivering reliable, high-quality solutions for international clients.',
        education: {
          school: 'Ho Chi Minh University of Technology and Education (HCMUTE)',
          major: 'Information Technology Engineer',
          period: 'Aug 2013 – Mar 2018',
          gpa: '7.68',
        },
        languages: 'English — reading and comprehension well; basic communication.',
        interests: ['Reading', 'Music', 'Movies'],
      },
    },
    vi: {
      nav: {
        portfolio: 'Portfolio',
        blog: 'Blog',
        about: 'Giới thiệu',
        skills: 'Kỹ năng',
        contact: 'Liên hệ',
        admin: 'Quản trị',
        apps: 'Ứng dụng',
        news: 'Tin tức',
        todosApp: 'Quản lý Công việc',
      },
      home: {
        portfolioLabel: 'Portfolio',
        contactMe: 'Liên hệ',
        about: 'Giới thiệu',
        location: 'Địa chỉ',
        phone: 'Điện thoại',
        education: 'Học vấn',
        skills: 'Kỹ năng',
        contact: 'Liên hệ',
        interests: 'Sở thích',
        readBlog: 'Đọc blog của tôi',
        latestPosts: 'Bài viết mới nhất',
        viewAllPosts: 'Xem tất cả bài viết',
        allPosts: 'Tất cả bài viết',
        appsTitle: 'Package mã nguồn mở',
        viewAllApps: 'Xem tất cả ứng dụng',
      },
      blog: {
        label: 'Blog',
        noPosts: 'Không tìm thấy bài viết.',
      },
      apps: {
        label: 'Ứng dụng',
        description:
          'Package, thư viện và side project tôi xây dựng, duy trì — trên Packagist, npm và GitHub.',
        items: {
          'foreign-language-course': {
            name: 'FLC — Foreign Language Companion',
            summary:
              'Chrome Extension + app Flutter + API Laravel để học tiếng Anh: tra từ Anh–Anh, lưu từ vựng, luyện nghe, quiz và nhắc ôn — một tài khoản, đồng bộ mọi nơi.',
            features: [
              'Tra từ ngay trên trang web (Chrome extension)',
              'Lưu từ vựng, đồng bộ giữa extension và mobile',
              'Luyện nghe với link YouTube/audio',
              'Quiz từ vựng & nghe, nhắc ôn tập định kỳ',
            ],
          },
          'db-management-tool': {
            name: 'GL Database Client',
            summary:
              'Desktop client đa nền tảng cho MySQL và PostgreSQL — kết nối trực tiếp hoặc qua SSH, duyệt schema/dữ liệu, chạy SQL, import/export file lớn có tiến trình và hủy.',
            features: [
              'MySQL & PostgreSQL, SSH tunnel (password hoặc private key)',
              'Workspace đa tab: cấu trúc bảng, data grid, SQL editor',
              'Import/export streaming cho file SQL lớn',
              'Credential lưu trong OS keychain, fallback mã hóa',
            ],
          },
          'laravel-telegram-logging': {
            name: 'Laravel Telegram Logging',
            summary:
              'Kênh log Laravel gửi warning/error lên Telegram — phù hợp team nhỏ cần cảnh báo nhanh mà không cần hệ thống observability đầy đủ.',
            features: [
              'Cấu hình mức log tối thiểu (TELEGRAM_LOG_LEVEL)',
              'Hỗ trợ queue — không chặn HTTP request',
              'Template HTML tùy chỉnh với placeholder',
              'Gộp tin nhắn trùng trong khoảng thời gian',
              'Lệnh Artisan: php artisan telegram-log:test',
            ],
          },
          'react-markdown-preview': {
            name: 'React Markdown Preview',
            summary:
              'Component React nhẹ để render Markdown với GFM, highlight code và anchor heading — đang dùng cho blog trên site này.',
            features: [
              'API đơn giản: <MarkdownPreview doc={doc} />',
              'GitHub Flavored Markdown qua remark-gfm',
              'Highlight syntax với rehype-highlight',
              'Heading tự động gắn link anchor',
              'Kèm CSS markdown light/dark và highlight',
            ],
          },
        },
      },
      common: {
        language: 'Ngôn ngữ',
      },
      cv: {
        name: 'Nguyễn Văn Nhàn',
        title: 'Lập trình viên',
        phone: '0799833537',
        email: 'nguyenvannhan0810@gmail.com',
        location: 'Thủ Đức, TP. Hồ Chí Minh',
        summary:
          'Lập trình viên với 8 năm kinh nghiệm xây dựng ứng dụng web và mobile có khả năng mở rộng. Thành thạo phát triển full-stack, mobile và thiết kế kiến trúc hệ thống. Cam kết mang đến giải pháp chất lượng cao, đáng tin cậy cho khách hàng quốc tế.',
        education: {
          school: 'Trường Đại học Sư phạm Kỹ thuật TP. Hồ Chí Minh (HCMUTE)',
          major: 'Kỹ sư Công nghệ Thông tin',
          period: '08/2013 – 03/2018',
          gpa: '7.68',
        },
        languages: 'Tiếng Anh — đọc hiểu tốt; giao tiếp cơ bản.',
        interests: ['Đọc sách', 'Nghe nhạc', 'Xem phim'],
      },
    },
  },
  profile: {
    githubLink: 'https://github.com/nvnhan0810',
    linkedinLink: 'https://linkedin.com/in/nvnhan0810',
    email: 'nguyenvannhan0810@gmail.com',
    avatarPath: '/images/avatar.png',
    cvPdfPath: '/cv/[Senior_Developer]_Nguyen_Van_Nhan.pdf',
  },
  techStack: [
    {
      name: 'PHP',
      slug: 'php',
      color: '777BB4',
    },
    {
      name: 'Laravel',
      slug: 'laravel',
      color: 'FF2D20',
    },
    {
      name: 'JavaScript',
      slug: 'javascript',
      color: 'F7DF1E',
    },
    {
      name: 'TypeScript',
      slug: 'typescript',
      color: '3178C6',
    },
    {
      name: 'React',
      slug: 'react',
      color: '61DAFB',
    },
    {
      name: 'Vue.js',
      slug: 'vuedotjs',
      color: '4FC08D',
    },
    {
      name: 'Nuxt',
      slug: 'nuxt',
      color: '00DC82',
    },
    {
      name: 'Inertia',
      slug: 'inertia',
      color: '9553E9',
    },
    {
      name: 'Tailwind CSS',
      slug: 'tailwindcss',
      color: '06B6D4',
    },
    {
      name: 'MySQL',
      slug: 'mysql',
      color: '4479A1',
    },
    {
      name: 'Redis',
      slug: 'redis',
      color: 'FF4438',
    },
    {
      name: 'Flutter',
      slug: 'flutter',
      color: '02569B',
    },
    {
      name: 'Docker',
      slug: 'docker',
      color: '2496ED',
    },
    {
      name: 'AWS',
      slug: 'amazonwebservices',
      color: 'FF9900',
      iconUrl: '/images/tech/aws.svg',
    },
    {
      name: 'Google Cloud',
      slug: 'googlecloud',
      color: '4285F4',
    },
    {
      name: 'Git',
      slug: 'git',
      color: 'F05032',
    },
    {
      name: 'Nginx',
      slug: 'nginx',
      color: '009639',
    },
  ],
  apps: [
    {
      slug: 'foreign-language-course',
      kind: 'project',
      label: 'FLC — Foreign Language Companion',
      githubUrl: 'https://github.com/nvnhan0810/foreign-language-course',
      tags: ['Laravel', 'Flutter', 'Chrome Extension'],
    },
    {
      slug: 'db-management-tool',
      kind: 'project',
      label: 'GL Database Client',
      githubUrl: 'https://github.com/nvnhan0810/db-management-tool',
      tags: ['Electron', 'Vue', 'MySQL', 'PostgreSQL'],
    },
    {
      slug: 'laravel-telegram-logging',
      kind: 'package',
      label: 'nvnhan0810/laravel-telegram-logging',
      githubUrl: 'https://github.com/nvnhan0810/laravel-telegram-logging',
      packagistUrl: 'https://packagist.org/packages/nvnhan0810/laravel-telegram-logging',
      tags: ['Laravel', 'Logging', 'Telegram'],
    },
    {
      slug: 'react-markdown-preview',
      kind: 'package',
      label: 'react-markdown-preview',
      githubUrl: 'https://github.com/nvnhan0810/react-markdown-preview',
      npmUrl: 'https://www.npmjs.com/package/react-markdown-preview',
      tags: ['React', 'Markdown', 'TypeScript'],
    },
  ],
  posts: [],
} satisfies Portfolio
