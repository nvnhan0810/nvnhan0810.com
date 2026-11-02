-- One serialized personal-finance aggregate per Identity user. Row locking makes
-- transfers, bulk entries, payments and their reversals atomic across replicas.
CREATE TABLE wallets_accounts (
 user_id UUID PRIMARY KEY REFERENCES identity_users(id) ON DELETE CASCADE,
 payload JSONB NOT NULL,
 updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
