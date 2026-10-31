CREATE TABLE foreign_dictionary_entries (
 id BIGSERIAL PRIMARY KEY, word TEXT NOT NULL UNIQUE, payload JSONB NOT NULL, metadata JSONB NOT NULL DEFAULT '{}',
 created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TABLE foreign_vocabularies (
 id BIGSERIAL PRIMARY KEY, user_id UUID NOT NULL REFERENCES identity_users(id),
 dictionary_entry_id BIGINT NOT NULL REFERENCES foreign_dictionary_entries(id),
 times_quizzed INTEGER NOT NULL DEFAULT 0 CHECK(times_quizzed >= 0),
 last_quizzed_at TIMESTAMP, last_correct_at TIMESTAMP,
 created_at TIMESTAMPTZ NOT NULL DEFAULT now(), updated_at TIMESTAMPTZ NOT NULL DEFAULT now(), UNIQUE(user_id,dictionary_entry_id)
);
CREATE INDEX ON foreign_vocabularies(user_id,id DESC);
CREATE TABLE foreign_import_runs (
 id UUID PRIMARY KEY, status TEXT NOT NULL CHECK(status IN ('queued','running','completed','failed')),
 dry_run BOOLEAN NOT NULL, processed BIGINT NOT NULL DEFAULT 0, imported BIGINT NOT NULL DEFAULT 0,
 skipped BIGINT NOT NULL DEFAULT 0, error TEXT, created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
 started_at TIMESTAMPTZ, finished_at TIMESTAMPTZ, heartbeat_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE UNIQUE INDEX foreign_one_import ON foreign_import_runs ((true)) WHERE status IN ('queued','running');
CREATE TABLE foreign_import_records (
 source_system TEXT NOT NULL, source_id BIGINT NOT NULL, source_snapshot JSONB NOT NULL, target_id BIGINT NOT NULL REFERENCES foreign_vocabularies(id),
 PRIMARY KEY(source_system,source_id)
);
