-- Task identifiers: each project carries a prefix (default DEF); each task gets PREFIX-N where N counts
-- up per user and prefix. The identifier is assigned at creation and never changes afterwards, so
-- Agent branches, PR titles and links keep pointing at the same task after a project move or rename.
-- Writes already run under the per-user lock (repo::lock), so MAX()+1 cannot race.
ALTER TABLE todo_projects
  ADD COLUMN identifier_prefix TEXT NOT NULL DEFAULT 'DEF'
    CHECK (identifier_prefix ~ '^[A-Z][A-Z0-9]{1,9}$');

ALTER TABLE todos
  ADD COLUMN identifier_prefix TEXT NOT NULL DEFAULT 'DEF'
    CHECK (identifier_prefix ~ '^[A-Z][A-Z0-9]{1,9}$'),
  ADD COLUMN identifier_seq INTEGER CHECK (identifier_seq > 0);

-- Existing tasks all belong to the DEF sequence: projects had no prefix before this migration.
UPDATE todos t
   SET identifier_seq = x.rn
  FROM (SELECT id, row_number() OVER (PARTITION BY user_id ORDER BY created_at, id) AS rn FROM todos) x
 WHERE t.id = x.id;

ALTER TABLE todos ALTER COLUMN identifier_seq SET NOT NULL;
ALTER TABLE todos
  ADD COLUMN identifier TEXT GENERATED ALWAYS AS (identifier_prefix || '-' || identifier_seq::text) STORED,
  ADD CONSTRAINT todos_identifier_unique UNIQUE (user_id, identifier);
-- Next-number lookup: MAX(identifier_seq) per (user, prefix). Deleted rows count, so numbers are never reused.
CREATE INDEX todos_identifier_seq ON todos(user_id, identifier_prefix, identifier_seq);
