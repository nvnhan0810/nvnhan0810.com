-- Auto-run no longer needs a repo: a task without one runs in its project's Agent workspace
-- root (the Agent picks the related repos itself; no branch or PR). It still needs a project.
ALTER TABLE todos
  DROP CONSTRAINT todos_agent_auto_needs_repo,
  ADD CONSTRAINT todos_agent_auto_needs_project CHECK (NOT agent_auto OR project_id IS NOT NULL);
