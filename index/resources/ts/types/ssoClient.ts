export type SsoClient = {
  id: string;
  client_id: string;
  name: string;
  domain: string | null;
  redirect_uris: string[];
  redirect_uri_patterns: string[];
  enabled: boolean;
};
