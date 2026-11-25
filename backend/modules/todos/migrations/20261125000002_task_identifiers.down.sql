DROP INDEX todos_identifier_seq;
ALTER TABLE todos DROP CONSTRAINT todos_identifier_unique;
ALTER TABLE todos DROP COLUMN identifier;
ALTER TABLE todos DROP COLUMN identifier_seq;
ALTER TABLE todos DROP COLUMN identifier_prefix;
ALTER TABLE todo_projects DROP COLUMN identifier_prefix;
