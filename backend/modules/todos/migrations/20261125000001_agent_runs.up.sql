-- Phase 4: claim / lease / report state on each task, plus a timeline of agent events.
-- agent_state is written only by the Agent integration (claim, heartbeat, report, release, sweeper)
-- and by the cancel guard in the user action path. Status changes are decided by the server.
ALTER TABLE todos
  ADD COLUMN agent_state          TEXT NOT NULL DEFAULT 'idle'
    CHECK (agent_state IN ('idle','claimed','running','succeeded','failed','cancelled')),
  ADD COLUMN agent_claim_id       UUID,
  ADD COLUMN agent_lease_until    TIMESTAMPTZ,
  ADD COLUMN agent_attempts       INTEGER NOT NULL DEFAULT 0 CHECK (agent_attempts >= 0),
  ADD COLUMN agent_chat_url       TEXT CHECK (agent_chat_url IS NULL OR length(agent_chat_url) <= 2000),
  ADD COLUMN agent_branch         TEXT CHECK (agent_branch IS NULL OR length(agent_branch) <= 200),
  ADD COLUMN agent_pr_url         TEXT CHECK (agent_pr_url IS NULL OR length(agent_pr_url) <= 2000),
  ADD COLUMN agent_pr_has_conflict BOOLEAN NOT NULL DEFAULT false,
  ADD COLUMN agent_result_md      TEXT CHECK (agent_result_md IS NULL OR length(agent_result_md) <= 20000),
  ADD CONSTRAINT todos_agent_live_needs_claim CHECK (agent_state NOT IN ('claimed','running') OR (agent_claim_id IS NOT NULL AND agent_lease_until IS NOT NULL));

-- Sweeper and cancel guard only look at live claims.
CREATE INDEX todos_agent_live_claims ON todos(agent_lease_until) WHERE agent_state IN ('claimed','running') AND deleted_at IS NULL;

CREATE TABLE todo_agent_events (
  id         BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  user_id    UUID NOT NULL REFERENCES identity_users(id) ON DELETE CASCADE,
  todo_id    BIGINT NOT NULL,
  kind       TEXT NOT NULL CHECK (length(kind) BETWEEN 1 AND 40),
  message    TEXT CHECK (message IS NULL OR length(message) <= 2000),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  FOREIGN KEY(user_id,todo_id) REFERENCES todos(user_id,id) ON DELETE CASCADE
);
CREATE INDEX todo_agent_events_task ON todo_agent_events(user_id,todo_id,id);
