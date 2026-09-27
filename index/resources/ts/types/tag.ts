export type Tag = {
  id: number;
  name: string;
  slug: string;
  public_posts_count?: number;
  posts_count?: number;
  is_protected?: boolean;
};
