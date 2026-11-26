-- Auto tasks without a repo cannot exist under the old rule: turn them off first.
UPDATE todos SET agent_auto=false WHERE agent_auto AND agent_repo_id IS NULL;
ALTER TABLE todos
  DROP CONSTRAINT todos_agent_auto_needs_project,
  ADD CONSTRAINT todos_agent_auto_needs_repo CHECK (NOT agent_auto OR (agent_repo_id IS NOT NULL AND project_id IS NOT NULL));
