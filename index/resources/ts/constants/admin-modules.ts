/** Shared admin module entries for header + hub. */
export const ADMIN_MODULES = [
  {
    key: "admin",
    label: "Admin",
    title: "Admin hub",
    route: "admin.index",
  },
  {
    key: "posts",
    label: "Posts",
    title: "Quản lý bài viết",
    route: "posts.index",
  },
  {
    key: "tags",
    label: "Tags",
    title: "Quản lý thẻ",
    route: "admin.tags.index",
  },
  {
    key: "series",
    label: "Series",
    title: "Quản lý series",
    route: "admin.series.index",
  },
  {
    key: "reading-digest",
    label: "Digest",
    title: "Reading Digest",
    route: "admin.reading-digest.today",
  },
  {
    key: "sso-clients",
    label: "SSO",
    title: "SSO clients",
    route: "admin.sso-clients.index",
  },
] as const;
