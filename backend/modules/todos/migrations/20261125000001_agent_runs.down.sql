DROP TABLE IF EXISTS todo_agent_events;
DROP INDEX IF EXISTS todos_agent_live_claims;
ALTER TABLE todos
  DROP CONSTRAINT IF EXISTS todos_agent_live_needs_claim,
  DROP COLUMN IF EXISTS agent_result_md,
  DROP COLUMN IF EXISTS agent_pr_has_conflict,
  DROP COLUMN IF EXISTS agent_pr_url,
  DROP COLUMN IF EXISTS agent_branch,
  DROP COLUMN IF EXISTS agent_chat_url,
  DROP COLUMN IF EXISTS agent_attempts,
  DROP COLUMN IF EXISTS agent_lease_until,
  DROP COLUMN IF EXISTS agent_claim_id,
  DROP COLUMN IF EXISTS agent_state;
