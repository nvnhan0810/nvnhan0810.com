-- The base branch moves from the project to the task: the repo default lives on Agent, and a task
-- may name its own base for a special case. NULL means "use the repo's default".
ALTER TABLE todo_projects DROP COLUMN IF EXISTS base_branch;
ALTER TABLE todos
  ADD COLUMN agent_base_branch TEXT
    CHECK (agent_base_branch IS NULL OR (length(agent_base_branch) BETWEEN 1 AND 120 AND agent_base_branch ~ '^[A-Za-z0-9][A-Za-z0-9._/-]*$'));
