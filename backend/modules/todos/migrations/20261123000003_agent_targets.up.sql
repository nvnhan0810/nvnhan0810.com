-- Phase 3: which Agent workspace a Todo project maps to, and which repo a task runs in.
-- The *_name columns are display snapshots so the UI still works while the Agent is offline.
ALTER TABLE todo_projects
  ADD COLUMN agent_workspace_id   TEXT CHECK (agent_workspace_id IS NULL OR length(agent_workspace_id) BETWEEN 1 AND 100),
  ADD COLUMN agent_workspace_name TEXT CHECK (agent_workspace_name IS NULL OR length(agent_workspace_name) <= 255);
ALTER TABLE todos
  ADD COLUMN agent_repo_id   TEXT CHECK (agent_repo_id IS NULL OR length(agent_repo_id) BETWEEN 1 AND 100),
  ADD COLUMN agent_repo_name TEXT CHECK (agent_repo_name IS NULL OR length(agent_repo_name) <= 255),
  ADD COLUMN agent_auto      BOOLEAN NOT NULL DEFAULT false,
  ADD CONSTRAINT todos_agent_auto_needs_repo CHECK (NOT agent_auto OR (agent_repo_id IS NOT NULL AND project_id IS NOT NULL));
