ALTER TABLE foreign_vocabularies ADD COLUMN entry_override JSONB;
CREATE TABLE foreign_game_sessions (
 user_id UUID NOT NULL REFERENCES identity_users(id), mode TEXT NOT NULL,
 state JSONB NOT NULL, updated_at TIMESTAMPTZ NOT NULL DEFAULT now(), PRIMARY KEY(user_id,mode)
);
CREATE TABLE foreign_game_records (
 user_id UUID NOT NULL REFERENCES identity_users(id), game TEXT NOT NULL,
 best_correct INTEGER NOT NULL DEFAULT 0 CHECK(best_correct>=0), PRIMARY KEY(user_id,game)
);
CREATE TABLE foreign_attempts (
 id BIGSERIAL PRIMARY KEY, user_id UUID NOT NULL REFERENCES identity_users(id),
 vocabulary_id BIGINT REFERENCES foreign_vocabularies(id) ON DELETE SET NULL,
 kind TEXT NOT NULL, score INTEGER NOT NULL, total INTEGER NOT NULL,
 completed_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX ON foreign_attempts(user_id,completed_at DESC);
CREATE TABLE foreign_media (
 id BIGSERIAL PRIMARY KEY, user_id UUID NOT NULL REFERENCES identity_users(id),
 payload JSONB NOT NULL, created_at TIMESTAMPTZ NOT NULL DEFAULT now(), updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX ON foreign_media(user_id,id DESC);
CREATE TABLE foreign_listen_logs (
 id BIGSERIAL PRIMARY KEY, user_id UUID NOT NULL REFERENCES identity_users(id),
 media_id BIGINT NOT NULL REFERENCES foreign_media(id) ON DELETE CASCADE,
 listened_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
