export const BLOG_COPY = {
  label: "Blog",
  latestPosts: "Bài viết mới nhất",
  taggedPosts: (tag: string): string => `Bài viết gắn thẻ "${tag}"`,
  tags: "Thẻ",
  noPosts: "Không tìm thấy bài viết.",
  metaDescription:
    "Các bài viết kỹ thuật, ghi chú lập trình và kinh nghiệm triển khai dự án.",
  searchPlaceholder: "Tìm kiếm",
  search: "Tìm kiếm",
  backToBlog: "Quay lại blog",
  article: "Bài viết",
  series: "Thuộc series",
  sourceOriginal: "Source gốc:",
  editPost: "Chỉnh sửa bài viết",
  ogLocale: "vi_VN",
} as const;
