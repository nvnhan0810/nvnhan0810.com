-- Todo <-> Agent account link (Phase 2). One link per Todo user.
CREATE TABLE todo_agent_links (
  user_id            UUID PRIMARY KEY REFERENCES identity_users(id) ON DELETE CASCADE,
  email_at_link      TEXT NOT NULL,
  inbound_token_hash TEXT NOT NULL UNIQUE,   -- sha256 of tdi_ (Agent calls Todo)
  outbound_token_enc TEXT NOT NULL,          -- agi_ sealed with AES-256-GCM (Todo calls Agent)
  status             TEXT NOT NULL CHECK (status IN ('active','revoked')),
  last_seen_at       TIMESTAMPTZ,
  created_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
  revoked_at         TIMESTAMPTZ
);
-- One-time consent requests started from the Todo UI.
CREATE TABLE todo_agent_link_requests (
  id         UUID PRIMARY KEY,
  user_id    UUID NOT NULL REFERENCES identity_users(id) ON DELETE CASCADE,
  code_hash  TEXT NOT NULL,
  expires_at TIMESTAMPTZ NOT NULL,
  used_at    TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX todo_agent_link_requests_user ON todo_agent_link_requests(user_id, created_at);
