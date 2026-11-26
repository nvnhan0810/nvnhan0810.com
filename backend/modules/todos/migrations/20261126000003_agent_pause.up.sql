-- Pause / resume of an Agent run, and the cleanup queue the Agent drains.
-- agent_pause_requested: the user asked (on Todo) to pause a live run; the Agent reads it on heartbeat.
-- agent_cleanup: set when a paused (or live) task is closed (done/rejected) or deleted, so the
-- Agent removes its clone; 'discard' also closes the PR and deletes the branch, 'keep' leaves them.
ALTER TABLE todos DROP CONSTRAINT IF EXISTS todos_agent_state_check;
ALTER TABLE todos
  ADD CONSTRAINT todos_agent_state_check
    CHECK (agent_state IN ('idle','claimed','running','paused','succeeded','failed','cancelled')),
  ADD COLUMN agent_pause_requested BOOLEAN NOT NULL DEFAULT false,
  ADD COLUMN agent_cleanup TEXT CHECK (agent_cleanup IN ('keep','discard'));
CREATE INDEX todos_agent_cleanup ON todos(user_id) WHERE agent_cleanup IS NOT NULL;
