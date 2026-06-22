import type { Messages } from "../types";

const vi: Messages = {
  nav: {
    portfolio: "Portfolio",
    blog: "Blog",
    about: "Giới thiệu",
    skills: "Kỹ năng",
    contact: "Liên hệ",
    admin: "Quản trị",
    apps: "Ứng dụng",
  },
  home: {
    portfolioLabel: "Portfolio",
    contactMe: "Liên hệ",
    about: "Giới thiệu",
    location: "Địa chỉ",
    phone: "Điện thoại",
    education: "Học vấn",
    skills: "Kỹ năng",
    contact: "Liên hệ",
    interests: "Sở thích",
    readBlog: "Đọc blog của tôi",
    latestPosts: "Bài viết mới nhất",
    viewAllPosts: "Xem tất cả bài viết",
    allPosts: "Tất cả bài viết",
    appsTitle: "Package mã nguồn mở",
    viewAllApps: "Xem tất cả ứng dụng",
  },
  blog: {
    label: "Blog",
    latestPosts: "Bài viết mới nhất",
    taggedPosts: 'Bài viết gắn thẻ "{{tag}}"',
    tags: "Thẻ",
    noPosts: "Không tìm thấy bài viết.",
    metaDescription: "Các bài viết kỹ thuật, ghi chú lập trình và kinh nghiệm triển khai dự án.",
    searchPlaceholder: "Tìm kiếm",
    search: "Tìm kiếm",
    backToBlog: "Quay lại blog",
    article: "Bài viết",
    series: "Thuộc series",
    sourceOriginal: "Source gốc:",
    editPost: "Chỉnh sửa bài viết",
  },
  apps: {
    label: "Ứng dụng",
    title: "App & package mã nguồn mở",
    description:
      "Package, thư viện và side project tôi xây dựng, duy trì — trên Packagist, npm và GitHub.",
    metaDescription:
      "App và package mã nguồn mở của Nguyễn Văn Nhàn — FLC học tiếng Anh, GL Database Client, Laravel Telegram logging, React Markdown Preview và hơn thế nữa.",
    featuresLabel: "Tính năng",
    viewOnPackagist: "Xem trên Packagist",
    viewOnNpm: "Xem trên npm",
    viewOnGithub: "Xem trên GitHub",
    backToApps: "Quay lại ứng dụng",
    viewDetails: "Xem chi tiết",
    items: {
      "foreign-language-course": {
        name: "FLC — Foreign Language Companion",
        summary:
          "Chrome Extension + app Flutter + API Laravel để học tiếng Anh: tra từ Anh–Anh, lưu từ vựng, luyện nghe, quiz và nhắc ôn — một tài khoản, đồng bộ mọi nơi.",
        features: [
          "Tra từ ngay trên trang web (Chrome extension)",
          "Lưu từ vựng, đồng bộ giữa extension và mobile",
          "Luyện nghe với link YouTube/audio",
          "Quiz từ vựng & nghe, nhắc ôn tập định kỳ",
        ],
      },
      "db-management-tool": {
        name: "GL Database Client",
        summary:
          "Desktop client đa nền tảng cho MySQL và PostgreSQL — kết nối trực tiếp hoặc qua SSH, duyệt schema/dữ liệu, chạy SQL, import/export file lớn có tiến trình và hủy.",
        features: [
          "MySQL & PostgreSQL, SSH tunnel (password hoặc private key)",
          "Workspace đa tab: cấu trúc bảng, data grid, SQL editor",
          "Import/export streaming cho file SQL lớn",
          "Credential lưu trong OS keychain, fallback mã hóa",
        ],
      },
      "laravel-telegram-logging": {
        name: "Laravel Telegram Logging",
        summary:
          "Kênh log Laravel gửi warning/error lên Telegram — phù hợp team nhỏ cần cảnh báo nhanh mà không cần hệ thống observability đầy đủ.",
        features: [
          "Cấu hình mức log tối thiểu (TELEGRAM_LOG_LEVEL)",
          "Hỗ trợ queue — không chặn HTTP request",
          "Template HTML tùy chỉnh với placeholder",
          "Gộp tin nhắn trùng trong khoảng thời gian",
          "Lệnh Artisan: php artisan telegram-log:test",
        ],
      },
      "react-markdown-preview": {
        name: "React Markdown Preview",
        summary:
          "Component React nhẹ để render Markdown với GFM, highlight code và anchor heading — đang dùng cho blog trên site này.",
        features: [
          "API đơn giản: <MarkdownPreview doc={doc} />",
          "GitHub Flavored Markdown qua remark-gfm",
          "Highlight syntax với rehype-highlight",
          "Heading tự động gắn link anchor",
          "Kèm CSS markdown light/dark và highlight",
        ],
      },
    },
    details: {
      "foreign-language-course": {
        name: "FLC — Foreign Language Companion",
        tagline: "Chrome Extension + app Flutter + API Laravel",
        metaDescription:
          "FLC giúp học tiếng Anh với tra từ Anh–Anh, đồng bộ từ vựng, luyện nghe và quiz trên Chrome extension và app mobile.",
        summary:
          "FLC là bộ công cụ học ngôn ngữ full-stack xoay quanh vòng lặp: gặp từ → tra Anh–Anh → lưu lại → luyện nghe → ôn bằng quiz. Một tài khoản Google giữ từ vựng và tiến độ đồng bộ giữa Chrome extension và app Flutter.",
        highlights: [
          "Từ điển Anh–Anh, không dịch sang tiếng Việt",
          "Một tài khoản đồng bộ dữ liệu extension và mobile",
          "Notification Chrome nhắc quiz và lịch nghe",
          "Push FCM trên mobile lúc 11:00 & 20:00 giờ VN",
          "Trang Admin: allowlist, users, từ vựng, media",
        ],
        learningFlow: {
          title: "Flow học tiếng Anh",
          intro:
            "Extension và mobile bổ sung cho nhau trong cùng một vòng học:",
          loop: ["Gặp từ", "Tra từ", "Lưu từ", "Nghe", "Quiz"],
          stepColumn: "Bước",
          extensionColumn: "Chrome Extension",
          mobileColumn: "Mobile app",
          steps: [
            {
              step: "Tra từ",
              extension:
                "Bôi đen → chuột phải Tra từ với FLC, hoặc tab Tra từ trong popup",
              mobile: "Tab Tra từ",
            },
            {
              step: "Lưu từ",
              extension: "Tab Từ của tôi",
              mobile: "Tab Từ vựng (đồng bộ)",
            },
            {
              step: "Nghe",
              extension: "Thêm link YouTube/audio, nhắc nghe lại",
              mobile: "Tab Nghe — YouTube/MP3, listening quiz",
            },
            {
              step: "Quiz",
              extension: "Tab Quiz, notification Chrome",
              mobile: "Tab Quiz, push FCM 11h & 20h",
            },
            {
              step: "Tiến độ",
              extension: "Options / sync",
              mobile: "Tab Cá nhân — thống kê, lịch sử",
            },
          ],
          note: "Cần ≥ 4 từ đã lưu để làm quiz từ vựng.",
        },
        whenToUse: {
          title: "Nên dùng extension hay app?",
          situationColumn: "Tình huống",
          suggestionColumn: "Gợi ý",
          items: [
            {
              situation: "Đọc web, docs, forum trên Chrome",
              suggestion: "Chrome Extension",
            },
            {
              situation: "Học trên điện thoại, nhận push nhắc quiz",
              suggestion: "Mobile app",
            },
            {
              situation: "Tự thêm link YouTube nghe lại",
              suggestion: "Chrome Extension",
            },
            {
              situation: "Bài nghe + listening quiz từ admin",
              suggestion: "Mobile app",
            },
          ],
        },
        layers: {
          title: "Thành phần dự án",
          items: [
            {
              name: "Backend",
              description: "REST API, admin, Google OAuth, lên lịch push FCM",
              stack: "Laravel · PostgreSQL · Sanctum",
            },
            {
              name: "Chrome Extension",
              description:
                "Tra từ trên trang, popup, từ vựng, quiz, notification",
              stack: "Chrome MV3 · TypeScript · Vite",
            },
            {
              name: "Mobile app",
              description: "Tra từ, từ vựng, nghe, quiz, cá nhân & thống kê",
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
              component: "Từ điển",
              tech: "Free Dictionary API (dictionaryapi.dev)",
            },
          ],
        },
        repoStructure: {
          title: "Cấu trúc repo",
          items: [
            { folder: "backend/", description: "Laravel API + Admin (Sail)" },
            { folder: "extension/", description: "Chrome Extension MV3" },
            { folder: "mobile/", description: "Flutter app (iOS / Android)" },
            { folder: "docs/", description: "Tài liệu & hình minh họa" },
          ],
        },
      },
      "db-management-tool": {
        name: "GL Database Client",
        tagline: "Desktop client đa nền tảng cho MySQL & PostgreSQL",
        metaDescription:
          "GL Database Client — app Electron kết nối MySQL/PostgreSQL qua SSH, duyệt schema, chạy SQL và import/export streaming file lớn.",
        summary:
          "GL Database Client là database client desktop native, xây bằng Electron, Vue 3 và TypeScript. Kết nối trực tiếp hoặc qua SSH, duyệt schema và dữ liệu trong workspace đa tab, chạy SQL có lịch sử, và xử lý import/export lớn mà không nạp cả bảng vào RAM.",
        highlights: [
          "MySQL & PostgreSQL — kết nối, query, introspect schema, quản lý bảng",
          "SSH tunnel với password hoặc private key (+ passphrase tùy chọn)",
          "Mật khẩu DB/SSH lưu qua OS keychain (keytar), fallback mã hóa",
          "Export/import streaming ra disk — tiến trình, hủy, xử lý theo batch",
          "Workspace đa tab, khôi phục session, phím tắt",
        ],
        featureGroups: [
          {
            title: "Quản lý kết nối",
            items: [
              "Tạo, sửa, xóa, sắp xếp profile kết nối",
              "Export/import danh sách kết nối JSON (credential giữ trong secret store OS)",
              "Kết nối có/không database mặc định; đổi database từ workspace",
              "SSH tunnel tùy chọn (host, port, user, password hoặc private key)",
              "Nhắc nhập credential khi mở profile chưa lưu secret",
              "Drop database có xác nhận (bảo vệ system database)",
            ],
          },
          {
            title: "Workspace & điều hướng",
            items: [
              "Home — danh sách kết nối có tìm kiếm, context menu (sửa, xóa, export)",
              "Schema browser (table/view), UI đa tab cho bảng và query",
              "Structure view — cột, kiểu, null, default, FK, số dòng",
              "Data view — grid phân trang, sort, filter, sửa inline khi hỗ trợ",
              "Mở hàng liên quan qua FK trong tab mới",
              "Sidebar chi tiết dòng; panel lịch sử SQL (success/error)",
              "Khôi phục session — mở lại tab workspace (password không lưu localStorage)",
            ],
          },
          {
            title: "SQL editor",
            items: [
              "Tab query riêng cho mỗi kết nối",
              "Chạy toàn script hoặc vùng chọn (Ctrl/Cmd + Enter)",
              "Grid kết quả kèm số dòng và phản hồi thực thi",
              "Lịch sử SQL phía dưới cho query và thao tác bảng",
            ],
          },
          {
            title: "Import & export",
            items: [
              "Export bảng ra SQL: cấu trúc + dữ liệu, chỉ cấu trúc, hoặc chỉ dữ liệu",
              "Stream export thẳng ra disk — không load cả bảng vào memory",
              "Export theo batch có sự kiện tiến trình từng bảng",
              "Import SQL với parser streaming (comment, chuỗi quoted, PostgreSQL dollar-quote)",
              "Hủy job import/export dài; drop table (phân biệt table/view)",
            ],
          },
          {
            title: "Bảo mật & riêng tư",
            items: [
              "Mật khẩu DB/SSH trong Keychain macOS / credential store qua keytar",
              "Master key và fallback file mã hóa khi keychain không dùng được",
              "State workspace trong localStorage redact password và SSH key",
              "Log lỗi kết nối ở main process redact trường nhạy cảm",
              "IPC preload allowlist (contextIsolation, renderer không có Node)",
            ],
          },
        ],
        shortcuts: {
          title: "Phím tắt",
          shortcutColumn: "Phím tắt",
          actionColumn: "Thao tác",
          items: [
            {
              shortcut: "Ctrl/Cmd + R",
              action: "Reload kết nối đang active (bảng + tab mở)",
            },
            {
              shortcut: "Ctrl/Cmd + Shift + R",
              action: "Reload chỉ dữ liệu tab hiện tại",
            },
            { shortcut: "Ctrl/Cmd + S", action: "Lưu chỉnh sửa dòng đang pending" },
            { shortcut: "Ctrl/Cmd + K", action: "Focus tìm kiếm bảng" },
            { shortcut: "Ctrl/Cmd + W", action: "Đóng tab đang active" },
            { shortcut: "Ctrl/Cmd + Enter", action: "Chạy query (SQL editor)" },
            {
              shortcut: "Ctrl/Cmd + A",
              action: "Chọn tất cả bảng đã lọc (sidebar)",
            },
          ],
        },
        layers: {
          title: "Kiến trúc",
          description:
            "Layout phân lớp: domain types, infrastructure (driver DB, storage, IPC), presentation (Vue components & stores). MySQL và PostgreSQL mỗi loại có driver riêng; DatabaseService điều phối kết nối, SSH tunnel và API IPC.",
          items: [
            {
              name: "Domain",
              description: "Kiểu connection & query, logic thuần",
              stack: "TypeScript",
            },
            {
              name: "Infrastructure",
              description: "DatabaseService, SQL splitter, driver MySQL/PG, storage, IPC",
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
          title: "Cấu trúc project",
          items: [
            {
              folder: "src/domain/",
              description: "Kiểu connection & query, logic thuần",
            },
            {
              folder: "src/infrastructure/",
              description: "DatabaseService, drivers, storage, IPC",
            },
            {
              folder: "src/presentation/",
              description: "Vue views, components, Pinia stores",
            },
            { folder: "docs/screenshots/", description: "Screenshot README" },
            {
              folder: "db-init/",
              description: "Docker init SQL + seed cho test local",
            },
            { folder: "scripts/", description: "Helper đóng gói" },
          ],
        },
        platformsNote:
          "Build và test chủ yếu trên macOS (arm64). Electron Forge cũng target Windows (Squirrel) và Linux (deb/rpm/zip). Native module cần rebuild theo từng nền tảng.",
      },
    },
  },
  common: {
    language: "Ngôn ngữ",
  },
  cv: {
    name: "Nguyễn Văn Nhàn",
    title: "Lập trình viên",
    phone: "0799833537",
    email: "nguyenvannhan0810@gmail.com",
    location: "Thủ Đức, TP. Hồ Chí Minh",
    summary:
      "Lập trình viên với 8 năm kinh nghiệm xây dựng ứng dụng web và mobile có khả năng mở rộng. Thành thạo phát triển full-stack, mobile và thiết kế kiến trúc hệ thống. Cam kết mang đến giải pháp chất lượng cao, đáng tin cậy cho khách hàng quốc tế.",
    education: {
      school: "Trường Đại học Sư phạm Kỹ thuật TP. Hồ Chí Minh (HCMUTE)",
      major: "Kỹ sư Công nghệ Thông tin",
      period: "08/2013 – 03/2018",
      gpa: "7.68",
    },
    languages:
      "Tiếng Anh — đọc hiểu tốt; giao tiếp cơ bản.",
    interests: ["Đọc sách", "Nghe nhạc", "Xem phim"],
  },
};

export default vi;
