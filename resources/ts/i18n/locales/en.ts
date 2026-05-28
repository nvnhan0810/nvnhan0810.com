import type { Messages } from "../types";

const en: Messages = {
  nav: {
    portfolio: "Portfolio",
    blog: "Blog",
    about: "About",
    skills: "Skills",
    experience: "Experience",
    projects: "Projects",
    contact: "Contact",
    admin: "Admin",
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
    workExperience: "Work Experience",
    projects: "Projects",
    contact: "Contact",
    interests: "Interests",
    readBlog: "Read my blog",
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
        period: "Feb 2025 – Present",
        highlights: [
          "Set up and configured servers to deploy internal systems.",
          "Implemented and customized Redmine; designed development workflows for the engineering team.",
          "Led development of the company's ERP system.",
          "Managed a team of 20 members on a Loyalty System project for a Japanese client.",
        ],
      },
      {
        company: "ZIGExN VeNtura",
        period: "Dec 2022 – Jun 2024",
        highlights: [
          "Developed website and mobile application for a franchising platform.",
          "Managed and optimized cloud infrastructure on Google Cloud Platform.",
          "Researched technical solutions to improve performance and scalability.",
          "Conducted code reviews and trained interns.",
        ],
      },
      {
        company: "CMC Global Co., LTD",
        period: "Jan 2022 – Nov 2022",
        highlights: [
          "Optimized and maintained the customer's Gym Management System.",
          "Developed a new system based on customer requirements.",
        ],
      },
      {
        company: "Poste Vietnam Company",
        period: "Aug 2021 – Dec 2021",
        highlights: [
          "Developed a delivery mobile application.",
          "Optimized news websites and CMS.",
          "Built and maintained CI/CD pipeline using Jenkins.",
        ],
      },
      {
        company: "Vitalify Asia Co., LTD",
        period: "Nov 2020 – Jul 2021",
        highlights: [
          "Developed and optimized projects for Japanese clients.",
          "Participated in requirement analysis and effort estimation.",
        ],
      },
      {
        company: "Poste Vietnam Company",
        period: "Aug 2017 – Aug 2020",
        highlights: [
          "Developed and maintained news websites.",
          "Built and maintained a sales management system.",
        ],
      },
    ],
    projects: [
      {
        name: "Odoo ERP Project",
        description:
          "Customized ERP on Odoo with extended modules. Built and published a mobile app for warehouse and barcode operations on Google Play and App Store.",
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
        name: "Franchising System (Japan)",
        description:
          "CMS with Laravel and Inertia (React). Flutter mobile app. Serverless on GCP with Cloud Build and Cloud Run. Twilio Conversations API for real-time chat.",
        stack: ["Laravel", "Inertia", "React", "Flutter", "GCP", "Twilio"],
      },
      {
        name: "Gym Management System (Japan)",
        description:
          "Admin portal for gym devices and crowd monitoring. APIs for check-in/out. Node.js services for IoT. AWS IoT integration. CI/CD with Jenkins on AWS.",
        stack: ["Laravel", "Node.js", "AWS IoT", "Jenkins"],
      },
      {
        name: "Healthcare Application",
        description:
          "React frontend and Laravel APIs. Real-time chat with Pusher and QuickBlox. AWS Lambda for scheduled tasks.",
        stack: ["React", "Laravel", "Pusher", "AWS Lambda"],
      },
      {
        name: "Poste News Websites",
        description:
          "News sites for Vietnam, Cambodia, and Myanmar targeting Japanese communities. Built with Laravel, Bootstrap 4, and jQuery.",
        stack: ["Laravel", "Bootstrap", "jQuery"],
      },
      {
        name: "Licenses and Sale Management",
        description:
          "License status and application sales management. Laravel Sanctum APIs with Vue.js 2 frontend.",
        stack: ["Laravel", "Sanctum", "Vue.js"],
      },
    ],
    languages:
      "English — reading and comprehension well; basic communication.",
    interests: ["Reading", "Music", "Movies"],
  },
};

export default en;
