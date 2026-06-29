export type RdSubject = {
  id: string;
  name: string;
  slug: string;
  description?: string | null;
  articles_per_digest: number;
  max_age_days: number;
  enabled: boolean;
  sources_count?: number;
  sources?: RdSource[];
};

export type RdSource = {
  id: string;
  name: string;
  type: string;
  url: string;
  fetch_interval_minutes: number;
  enabled: boolean;
  config?: Record<string, unknown> | null;
  last_fetch_status?: string | null;
  last_fetch_at?: string | null;
  last_fetch_error?: string | null;
  tag_mappings?: RdTagMapping[];
};

export type RdTagMapping = {
  id?: string;
  raw_tag: string;
  taxonomy_node_id: string;
  taxonomy_node?: RdTaxonomyNode;
};

export type RdTaxonomyNode = {
  id: string;
  label: string;
  slug: string;
  path: string;
  parent_id?: string | null;
};

export type RdArticle = {
  id: string;
  title: string;
  url: string;
  summary?: string | null;
  language: string;
  estimated_read_time_minutes?: number | null;
  metadata?: Record<string, unknown> | null;
  published_at?: string | null;
  force_include: boolean;
  force_exclude: boolean;
  source?: { id: string; name: string };
  taxonomy_nodes?: RdTaxonomyNode[];
};

export type RdDigestSettings = {
  id: string;
  notification_time: string;
  timezone: string;
  settings?: Record<string, unknown> | null;
};

export type RdProfile = {
  id: string;
  preferences: Record<string, unknown>;
};

export type RdDigestRun = {
  id: string;
  run_date: string;
  status: string;
  items?: RdDigestRunItem[];
};

export type RdDigestRunItem = {
  id: string;
  rank: number;
  retrieval_score?: number | null;
  llm_score?: number | null;
  llm_reason?: string | null;
  article?: RdArticle;
  subject?: RdSubject;
};

export type RdInterestScore = {
  id: string;
  score: number;
  taxonomy_node?: RdTaxonomyNode;
};

export type SourceTypeOption = {
  value: string;
  label: string;
};
