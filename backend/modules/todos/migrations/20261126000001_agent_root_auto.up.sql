-- Auto-run no longer needs a repo: a task of a project with an Agent workspace runs in the
-- workspace root when it has no repo. Auto still needs a project (the workspace comes from it).
ALTER TABLE todos
  DROP CONSTRAINT todos_agent_auto_needs_repo,
  ADD CONSTRAINT todos_agent_auto_needs_project CHECK (NOT agent_auto OR project_id IS NOT NULL);
