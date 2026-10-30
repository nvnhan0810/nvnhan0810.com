CREATE TABLE identity_users (
    id UUID PRIMARY KEY,
    google_subject TEXT NOT NULL UNIQUE,
    email TEXT NOT NULL,
    name TEXT NOT NULL,
    avatar TEXT,
    enabled BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT identity_system_root_enabled
        CHECK (lower(email) <> 'nguyenvannhan0810@gmail.com' OR enabled)
);

CREATE TABLE sso_clients (
    id UUID PRIMARY KEY,
    client_id VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    domain TEXT,
    client_type TEXT NOT NULL CHECK (client_type IN ('public', 'confidential')),
    secret_hash TEXT,
    redirect_uris TEXT[] NOT NULL CHECK (cardinality(redirect_uris) > 0),
    scopes TEXT[] NOT NULL,
    enabled BOOLEAN NOT NULL DEFAULT TRUE,
    version BIGINT NOT NULL DEFAULT 1 CHECK (version > 0),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    CHECK ((client_type = 'public' AND secret_hash IS NULL)
        OR (client_type = 'confidential' AND secret_hash IS NOT NULL))
);

-- Intent/state are short-lived and browser-bound; no credentials in URLs except opaque codes.
CREATE TABLE identity_login_attempts (
    state_hash TEXT PRIMARY KEY,
    browser_hash TEXT NOT NULL,
    nonce TEXT NOT NULL,
    google_verifier TEXT NOT NULL,
    intent JSONB NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL
);
CREATE TABLE identity_browser_logins (
    token_hash TEXT PRIMARY KEY,
    user_id UUID NOT NULL REFERENCES identity_users(id),
    expires_at TIMESTAMPTZ NOT NULL
);
CREATE INDEX ON identity_browser_logins(expires_at);

CREATE TABLE identity_authorization_codes (
    code_hash TEXT PRIMARY KEY,
    user_id UUID NOT NULL REFERENCES identity_users(id),
    sso_client_id UUID NOT NULL REFERENCES sso_clients(id),
    redirect_uri TEXT NOT NULL,
    challenge TEXT NOT NULL,
    scopes TEXT[] NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL
);

CREATE TABLE identity_grants (
    id UUID PRIMARY KEY,
    user_id UUID NOT NULL REFERENCES identity_users(id),
    sso_client_id UUID NOT NULL REFERENCES sso_clients(id),
    scopes TEXT[] NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL,
    revoked_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX ON identity_grants(user_id);
CREATE TABLE identity_refresh_tokens (
    token_hash TEXT PRIMARY KEY,
    grant_id UUID NOT NULL REFERENCES identity_grants(id),
    consumed_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX ON identity_refresh_tokens(grant_id);

CREATE INDEX ON identity_login_attempts(expires_at);
CREATE INDEX ON identity_authorization_codes(expires_at);
CREATE INDEX ON identity_grants(expires_at);
CREATE INDEX ON identity_grants(sso_client_id);
