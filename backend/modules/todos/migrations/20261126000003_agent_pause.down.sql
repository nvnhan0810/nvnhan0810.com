DROP INDEX IF EXISTS todos_agent_cleanup;
UPDATE todos SET agent_state='cancelled' WHERE agent_state='paused';
ALTER TABLE todos
  DROP COLUMN agent_cleanup,
  DROP COLUMN agent_pause_requested,
  DROP CONSTRAINT todos_agent_state_check;
ALTER TABLE todos
  ADD CONSTRAINT todos_agent_state_check
    CHECK (agent_state IN ('idle','claimed','running','succeeded','failed','cancelled'));
