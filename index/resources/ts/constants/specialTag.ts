export const SPECIAL_TAG = {
  Install: {
    name: "Install",
    slug: "install",
  },
} as const;

/** Query param: when "1", admin blog home includes Install-tagged posts. */
export const INCLUDE_INSTALL_QUERY = "include_install" as const;

export function isProtectedTagSlug(slug: string): boolean {
  return slug === SPECIAL_TAG.Install.slug;
}
