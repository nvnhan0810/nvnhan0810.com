import type { Locale } from "@/ts/i18n";
import { Tag } from "./tag";

export type PostTranslationFields = {
  locale: Locale;
  title: string;
  description?: string;
  content: string;
};

export type Post = {
  id: number;
  title: string;
  slug: string;
  description?: string;
  content: string;
  published_at: string;
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
