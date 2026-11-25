ALTER TABLE todos
  DROP CONSTRAINT todos_agent_auto_needs_repo,
  DROP COLUMN agent_auto,
  DROP COLUMN agent_repo_name,
  DROP COLUMN agent_repo_id;
ALTER TABLE todo_projects
  DROP COLUMN agent_workspace_name,
  DROP COLUMN agent_workspace_id;
