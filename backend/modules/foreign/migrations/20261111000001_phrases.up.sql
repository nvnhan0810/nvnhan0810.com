CREATE TABLE foreign_phrases (
    id BIGSERIAL PRIMARY KEY,
    user_id UUID NOT NULL REFERENCES identity_users(id) ON DELETE CASCADE,
    phrase TEXT NOT NULL,
    payload JSONB NOT NULL,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE(user_id, phrase)
);
CREATE TABLE foreign_word_phrases (
    vocabulary_id BIGINT NOT NULL REFERENCES foreign_vocabularies(id) ON DELETE CASCADE,
    phrase_id BIGINT NOT NULL REFERENCES foreign_phrases(id) ON DELETE CASCADE,
    PRIMARY KEY(vocabulary_id, phrase_id)
);
