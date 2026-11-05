CREATE TABLE foreign_vocabulary_memory (
    vocabulary_id BIGINT PRIMARY KEY REFERENCES foreign_vocabularies(id) ON DELETE CASCADE,
    level SMALLINT CHECK (level BETWEEN 1 AND 4),
    rated_at TIMESTAMPTZ,
    meaning_counts JSONB NOT NULL DEFAULT '{}'::jsonb
);
