-- Refuse rollback if UUID source IDs exist, preserving imported records.
ALTER TABLE foreign_import_records ALTER COLUMN source_id TYPE BIGINT USING source_id::bigint;
