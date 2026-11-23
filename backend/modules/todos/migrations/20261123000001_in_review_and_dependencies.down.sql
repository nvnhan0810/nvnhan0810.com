DROP TABLE todo_dependencies;
UPDATE todos SET status='in_progress' WHERE status='in_review';
ALTER TABLE todos DROP CONSTRAINT todos_status_check;
ALTER TABLE todos ADD CONSTRAINT todos_status_check CHECK (status IN ('backlog','todo','in_progress','done','rejected'));
