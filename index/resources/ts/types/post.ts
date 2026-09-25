import type { Tag } from "./tag";
import type { PostStatusValue } from "../constants/postStatus";

export type Post = {
  id: number;
  title: string;
  slug: string;
  description?: string | null;
  content: string | null;
  source_url?: string | null;
  published_at: string | null;
  created_at?: string | null;
  is_published: boolean;
  status: PostStatusValue;
  og_image_url?: string;
  public_tags?: Tag[];
  tags?: Tag[];
};

export type PostPayload = {
  title: string;
  description?: string | null;
  content: string;
  source_url?: string | null;
  published_at: string | null;
  status: PostStatusValue;
  tags: string[];
  series_ids: number[];
};
