import type { Locale } from "@/ts/i18n";

export type PostAgentEdits = {
  locales: Partial<Record<Locale, string>>;
  source_urls: Partial<Record<Locale, string>>;
};

export type PostAgentMessage = {
  role: "user" | "assistant";
  content: string;
  edits?: PostAgentEdits;
  created_at?: string;
};

export type PostAgentChatContext = {
  docs: Record<Locale, string>;
  source_urls: Record<Locale, string>;
  active_locale: Locale;
};

export type PostAgentChatResponse = {
  session_id: string;
  reply: string;
  edits: PostAgentEdits;
  messages: PostAgentMessage[];
};

export type PostAgentSessionResponse = {
  configured: boolean;
  session_id: string;
  messages: PostAgentMessage[];
};
