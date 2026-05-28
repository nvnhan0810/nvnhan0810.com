import type { Locale } from "@/ts/i18n";
import type { Tag } from "./tag";

export type PostTranslationFields = {
  locale: Locale;
  title: string;
  description?: string;
  content?: string | null;
  source_url?: string | null;
};

export type Post = {
  id: number;
  title: string;
  slug: string;
  description?: string;
  content: string | null;
  source_url?: string | null;
  published_at: string | null;
  is_published: boolean;
  translations?: Partial<Record<Locale, PostTranslationFields>>;
  public_tags?: Tag[];
  tags?: Tag[];
};

export type PostPayload = {
  translations: Partial<Record<Locale, PostTranslationFields>>;
  published_at: string | null;
  is_published: boolean;
  tags: string[];
  series_ids: number[];
};
