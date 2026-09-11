import type { Post } from "@/ts/types/post";

export type Series = {
  id: number;
  name: string;
  slug: string;
  description: string;
  created_at: string;
  updated_at: string;

  posts: Array<Post & { pivot: { order: number } }>;
};
