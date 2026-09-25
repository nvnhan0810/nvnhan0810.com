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

export function isPostStatus(value: string): value is PostStatusValue {
  return (
    value === PostStatus.Public ||
    value === PostStatus.Private ||
    value === PostStatus.Draft
  );
}
