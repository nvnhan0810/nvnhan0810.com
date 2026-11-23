ALTER TABLE todos DROP CONSTRAINT todos_status_check;
ALTER TABLE todos ADD CONSTRAINT todos_status_check CHECK (status IN ('backlog','todo','in_progress','in_review','done','rejected'));
-- todo_id waits for depends_on_id to be done. Both rows belong to the same user (composite FKs);
-- the same-project rule and cycle check are enforced in the application layer inside the user lock.
CREATE TABLE todo_dependencies (
 user_id UUID NOT NULL REFERENCES identity_users(id) ON DELETE CASCADE,
 todo_id BIGINT NOT NULL,
 depends_on_id BIGINT NOT NULL,
 created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
 PRIMARY KEY(todo_id,depends_on_id),
 CHECK (todo_id<>depends_on_id),
 FOREIGN KEY(user_id,todo_id) REFERENCES todos(user_id,id) ON DELETE CASCADE,
 FOREIGN KEY(user_id,depends_on_id) REFERENCES todos(user_id,id) ON DELETE CASCADE
);
CREATE INDEX todo_dependencies_reverse ON todo_dependencies(depends_on_id);
