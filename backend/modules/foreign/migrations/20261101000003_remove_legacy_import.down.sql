CREATE TABLE foreign_import_runs (
 id UUID PRIMARY KEY, status TEXT NOT NULL CHECK(status IN ('queued','running','completed','failed')),
 dry_run BOOLEAN NOT NULL, processed BIGINT NOT NULL DEFAULT 0, imported BIGINT NOT NULL DEFAULT 0,
 skipped BIGINT NOT NULL DEFAULT 0, error TEXT, created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
 started_at TIMESTAMPTZ, finished_at TIMESTAMPTZ, heartbeat_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE UNIQUE INDEX foreign_one_import ON foreign_import_runs ((true)) WHERE status IN ('queued','running');
CREATE TABLE foreign_import_records (
 source_system TEXT NOT NULL, source_id TEXT NOT NULL, source_snapshot JSONB NOT NULL, target_id BIGINT NOT NULL REFERENCES foreign_vocabularies(id),
 PRIMARY KEY(source_system,source_id)
);
