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
    downloadCv: "Tải CV (PDF)",
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
    title: "Package mã nguồn mở",
    description:
      "Các package và công cụ mã nguồn mở tôi xây dựng, duy trì — phát hành trên Packagist, npm và GitHub.",
    metaDescription:
      "Package mã nguồn mở của Nguyễn Văn Nhàn — Laravel Telegram logging, React Markdown Preview và hơn thế nữa.",
    featuresLabel: "Tính năng",
    viewOnPackagist: "Xem trên Packagist",
    viewOnNpm: "Xem trên npm",
    viewOnGithub: "Xem trên GitHub",
    items: {
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
  },
  common: {
    language: "Ngôn ngữ",
  },
  cv: {
    name: "Nguyễn Văn Nhàn",
    title: "Lập trình viên Senior",
    phone: "0799833537",
    email: "nguyenvannhan0810@gmail.com",
    location: "Thủ Đức, TP. Hồ Chí Minh",
    summary:
      "Lập trình viên Senior với 8 năm kinh nghiệm xây dựng ứng dụng web và mobile có khả năng mở rộng. Thành thạo phát triển full-stack, mobile và thiết kế kiến trúc hệ thống. Cam kết mang đến giải pháp chất lượng cao, đáng tin cậy cho khách hàng quốc tế.",
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
