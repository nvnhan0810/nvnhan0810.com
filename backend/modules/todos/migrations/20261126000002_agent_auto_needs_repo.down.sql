ALTER TABLE todos
  DROP CONSTRAINT todos_agent_auto_needs_repo,
  ADD CONSTRAINT todos_agent_auto_needs_project CHECK (NOT agent_auto OR project_id IS NOT NULL);
