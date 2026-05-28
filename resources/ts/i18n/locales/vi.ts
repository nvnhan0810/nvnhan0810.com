import type { Messages } from "../types";

const vi: Messages = {
  nav: {
    portfolio: "Portfolio",
    blog: "Blog",
    about: "Giới thiệu",
    skills: "Kỹ năng",
    experience: "Kinh nghiệm",
    projects: "Dự án",
    contact: "Liên hệ",
    admin: "Quản trị",
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
    workExperience: "Kinh nghiệm làm việc",
    projects: "Dự án",
    contact: "Liên hệ",
    interests: "Sở thích",
    readBlog: "Đọc blog của tôi",
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
    skills: [
      { name: "PHP / Laravel", level: 4 },
      { name: "JavaScript", level: 4 },
      { name: "HTML5 / CSS3", level: 4 },
      { name: "Vue.js / Nuxt", level: 3 },
      { name: "MySQL", level: 4 },
      { name: "React.js", level: 3 },
      { name: "Flutter", level: 3 },
      { name: "Docker / AWS / GCP", level: 2 },
    ],
    experience: [
      {
        company: "THK Holdings Vietnam",
        period: "02/2025 – Hiện tại",
        highlights: [
          "Thiết lập và cấu hình server triển khai hệ thống nội bộ.",
          "Triển khai và tùy chỉnh Redmine; thiết kế quy trình phát triển cho team kỹ thuật.",
          "Dẫn dắt phát triển hệ thống ERP của công ty.",
          "Quản lý team 20 người xây dựng dự án Loyalty System cho khách hàng Nhật.",
        ],
      },
      {
        company: "ZIGExN VeNtura",
        period: "12/2022 – 06/2024",
        highlights: [
          "Phát triển website và ứng dụng mobile cho nền tảng nhượng quyền.",
          "Quản lý và tối ưu hạ tầng cloud trên Google Cloud Platform.",
          "Nghiên cứu giải pháp kỹ thuật cải thiện hiệu năng và khả năng mở rộng.",
          "Review code và đào tạo thực tập sinh.",
        ],
      },
      {
        company: "CMC Global Co., LTD",
        period: "01/2022 – 11/2022",
        highlights: [
          "Tối ưu và bảo trì hệ thống quản lý phòng gym của khách hàng.",
          "Phát triển hệ thống mới theo yêu cầu khách hàng.",
        ],
      },
      {
        company: "Công ty Poste Vietnam",
        period: "08/2021 – 12/2021",
        highlights: [
          "Phát triển ứng dụng mobile giao hàng.",
          "Tối ưu website tin tức và CMS.",
          "Xây dựng và bảo trì pipeline CI/CD với Jenkins.",
        ],
      },
      {
        company: "Vitalify Asia Co., LTD",
        period: "11/2020 – 07/2021",
        highlights: [
          "Phát triển và tối ưu dự án cho khách hàng Nhật Bản.",
          "Tham gia phân tích yêu cầu và ước lượng effort.",
        ],
      },
      {
        company: "Công ty Poste Vietnam",
        period: "08/2017 – 08/2020",
        highlights: [
          "Phát triển và bảo trì website tin tức.",
          "Xây dựng hệ thống quản lý bán hàng.",
        ],
      },
    ],
    projects: [
      {
        name: "Dự án Odoo ERP",
        description:
          "Tùy chỉnh ERP trên Odoo với các module mở rộng. Phát triển và phát hành ứng dụng mobile quản lý kho và barcode trên Google Play và App Store.",
        links: [
          {
            label: "Google Play",
            href: "https://play.google.com/store/apps/details?id=com.os4u.odoo_stock_barcode&hl=vi",
          },
          {
            label: "App Store",
            href: "https://apps.apple.com/vn/app/kho-barcode/id6736581892?l=vi",
          },
        ],
        stack: ["Odoo", "Flutter", "Mobile"],
      },
      {
        name: "Hệ thống Nhượng quyền (Nhật Bản)",
        description:
          "CMS với Laravel và Inertia (React). Ứng dụng Flutter. Kiến trúc serverless trên GCP, Cloud Build và Cloud Run. Tích hợp Twilio Conversations API cho chat realtime.",
        stack: ["Laravel", "Inertia", "React", "Flutter", "GCP", "Twilio"],
      },
      {
        name: "Hệ thống Quản lý Gym (Nhật Bản)",
        description:
          "Portal quản trị thiết bị gym và theo dõi mật độ. API check-in/out. Dịch vụ Node.js cho IoT. Tích hợp AWS IoT. CI/CD Jenkins trên AWS.",
        stack: ["Laravel", "Node.js", "AWS IoT", "Jenkins"],
      },
      {
        name: "Ứng dụng Y tế",
        description:
          "Frontend React và API Laravel. Chat realtime với Pusher và QuickBlox. AWS Lambda cho tác vụ định kỳ.",
        stack: ["React", "Laravel", "Pusher", "AWS Lambda"],
      },
      {
        name: "Website tin tức Poste",
        description:
          "Website tin tức về Việt Nam, Campuchia, Myanmar cho cộng đồng người Nhật. Laravel, Bootstrap 4, jQuery.",
        stack: ["Laravel", "Bootstrap", "jQuery"],
      },
      {
        name: "Quản lý License và Bán hàng",
        description:
          "Quản lý trạng thái license và thông tin bán ứng dụng. API Laravel Sanctum, frontend Vue.js 2.",
        stack: ["Laravel", "Sanctum", "Vue.js"],
      },
    ],
    languages:
      "Tiếng Anh — đọc hiểu tốt; giao tiếp cơ bản.",
    interests: ["Đọc sách", "Nghe nhạc", "Xem phim"],
  },
};

export default vi;
