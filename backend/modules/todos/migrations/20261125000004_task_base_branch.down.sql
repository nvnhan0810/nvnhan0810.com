ALTER TABLE todos DROP COLUMN agent_base_branch;
ALTER TABLE todo_projects
  ADD COLUMN base_branch TEXT NOT NULL DEFAULT 'main'
    CHECK (length(base_branch) BETWEEN 1 AND 120 AND base_branch ~ '^[A-Za-z0-9][A-Za-z0-9._/-]*$');
