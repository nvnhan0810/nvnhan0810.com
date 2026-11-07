DROP TABLE identity_user_apps;
DROP INDEX identity_users_email_unique;
UPDATE identity_users SET google_subject='pending:' || id::text WHERE google_subject IS NULL;
ALTER TABLE identity_users ALTER COLUMN google_subject SET NOT NULL;
ALTER TABLE sso_clients DROP COLUMN is_public;
