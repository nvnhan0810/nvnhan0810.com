export type PostAgentEdits = {
  markdown?: string | null;
  source_url?: string | null;
};

export type PostAgentMessage = {
  role: "user" | "assistant";
  content: string;
  edits?: PostAgentEdits;
  created_at?: string;
};

export type PostAgentChatContext = {
  doc: string;
  source_url: string;
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
