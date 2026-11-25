-- Base branch of a Todo project: Agent cuts each task branch from it and opens PRs against it.
-- Existing projects keep `main`, which is what Agent used before this column existed.
ALTER TABLE todo_projects
  ADD COLUMN base_branch TEXT NOT NULL DEFAULT 'main'
    CHECK (length(base_branch) BETWEEN 1 AND 120 AND base_branch ~ '^[A-Za-z0-9][A-Za-z0-9._/-]*$');
