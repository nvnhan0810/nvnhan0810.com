export const PostStatus = {
  Public: "public",
  Private: "private",
  Draft: "draft",
} as const;

export type PostStatusValue = (typeof PostStatus)[keyof typeof PostStatus];

export const POST_STATUS_OPTIONS: ReadonlyArray<{
  value: PostStatusValue;
  label: string;
}> = [
  { value: PostStatus.Public, label: "Công khai" },
  { value: PostStatus.Private, label: "Riêng tư" },
  { value: PostStatus.Draft, label: "Nháp" },
];

/** Card surface by publish status — public keeps the default card look. */
export const POST_STATUS_CARD_CLASS: Record<PostStatusValue, string> = {
  [PostStatus.Public]:
    "border-border bg-card hover:border-emerald-600/40 hover:bg-emerald-600/5",
  [PostStatus.Private]:
    "border-amber-500/40 bg-amber-500/10 hover:border-amber-500/55 hover:bg-amber-500/15",
  [PostStatus.Draft]:
    "border-violet-500/45 bg-violet-500/15 hover:border-violet-500/60 hover:bg-violet-500/20",
};

/** Detail article surface — public stays neutral; private/draft tinted. */
export const POST_STATUS_DETAIL_CLASS: Record<PostStatusValue, string> = {
  [PostStatus.Public]: "",
  [PostStatus.Private]:
    "rounded-2xl border border-amber-500/35 bg-amber-500/10 p-5 sm:p-6",
  [PostStatus.Draft]:
    "rounded-2xl border border-violet-500/40 bg-violet-500/15 p-5 sm:p-6",
};

export const POST_STATUS_META_CLASS: Record<PostStatusValue, string> = {
  [PostStatus.Public]: "text-emerald-500",
  [PostStatus.Private]: "text-amber-500",
  [PostStatus.Draft]: "text-violet-400",
};

export function isPostStatus(value: string): value is PostStatusValue {
  return (
    value === PostStatus.Public ||
    value === PostStatus.Private ||
    value === PostStatus.Draft
  );
}

export function postStatusCardClass(status: string): string {
  if (isPostStatus(status)) {
    return POST_STATUS_CARD_CLASS[status];
  }
  return POST_STATUS_CARD_CLASS[PostStatus.Public];
}

export function postStatusDetailClass(status: string): string {
  if (isPostStatus(status)) {
    return POST_STATUS_DETAIL_CLASS[status];
  }
  return POST_STATUS_DETAIL_CLASS[PostStatus.Public];
}

export function postStatusLabel(status: string): string {
  if (!isPostStatus(status)) {
    return status;
  }
  return (
    POST_STATUS_OPTIONS.find((option) => option.value === status)?.label ??
    status
  );
}

export function postStatusMetaClass(status: string): string {
  if (isPostStatus(status)) {
    return POST_STATUS_META_CLASS[status];
  }
  return POST_STATUS_META_CLASS[PostStatus.Public];
}
