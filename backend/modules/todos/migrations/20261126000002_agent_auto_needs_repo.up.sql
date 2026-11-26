-- Reverts 20261126000001_agent_root_auto: auto-run needs a repo again (no workspace-root runs).
-- Auto tasks without a repo cannot exist under this rule: turn them off first.
UPDATE todos SET agent_auto=false WHERE agent_auto AND agent_repo_id IS NULL;
ALTER TABLE todos
  DROP CONSTRAINT todos_agent_auto_needs_project,
  ADD CONSTRAINT todos_agent_auto_needs_repo CHECK (NOT agent_auto OR (agent_repo_id IS NOT NULL AND project_id IS NOT NULL));
