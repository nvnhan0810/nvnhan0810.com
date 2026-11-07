ALTER TABLE sso_clients ADD COLUMN is_public BOOLEAN NOT NULL DEFAULT TRUE;
ALTER TABLE identity_users ALTER COLUMN google_subject DROP NOT NULL;
CREATE UNIQUE INDEX identity_users_email_unique ON identity_users (lower(email));
CREATE TABLE identity_user_apps (
 user_id UUID NOT NULL REFERENCES identity_users(id) ON DELETE CASCADE,
 sso_client_id UUID NOT NULL REFERENCES sso_clients(id) ON DELETE CASCADE,
 PRIMARY KEY(user_id,sso_client_id)
);
