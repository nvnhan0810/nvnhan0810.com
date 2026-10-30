use crate::application::{
    error::{IdentityError, Result},
    models::{
        AuthorizeRequest, Client, ClientInput, CodeRecord, GoogleIdentity, GrantRecord,
        LoginAttempt, UserAccount, UserProfile,
    },
    ports::{IdentityRepository, TokenTransaction},
};
use async_trait::async_trait;
use chrono::{Duration, Utc};
use sqlx::{PgPool, Postgres, Row, Transaction};
use uuid::Uuid;

#[derive(Clone)]
pub struct IdentityStore(pub PgPool);

#[async_trait]
impl IdentityRepository for IdentityStore {
    async fn client(&self, client_id: &str) -> Result<Client> {
        sqlx::query_as("SELECT * FROM sso_clients WHERE client_id=$1")
            .bind(client_id)
            .fetch_optional(&self.0)
            .await?
            .ok_or_else(IdentityError::unauthorized)
    }
    async fn client_by_id(&self, id: Uuid) -> Result<Client> {
        sqlx::query_as("SELECT * FROM sso_clients WHERE id=$1")
            .bind(id)
            .fetch_optional(&self.0)
            .await?
            .ok_or(IdentityError {
                code: "not_found",
                status: 404,
            })
    }
    async fn profile(&self, id: Uuid) -> Result<UserProfile> {
        sqlx::query_as("SELECT id,email,name,avatar FROM identity_users WHERE id=$1 AND enabled")
            .bind(id)
            .fetch_optional(&self.0)
            .await?
            .ok_or_else(IdentityError::forbidden)
    }
    async fn register_client(
        &self,
        id: Uuid,
        input: &ClientInput,
        secret_hash: Option<&str>,
    ) -> Result<Client> {
        Ok(sqlx::query_as("INSERT INTO sso_clients (id,client_id,name,domain,client_type,secret_hash,redirect_uris,scopes,enabled) VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9) RETURNING *")
            .bind(id).bind(&input.client_id).bind(&input.name).bind(&input.domain)
            .bind(&input.client_type).bind(secret_hash).bind(&input.redirect_uris).bind(&input.scopes)
            .bind(input.enabled).fetch_one(&self.0).await?)
    }
    async fn update_client(&self, id: Uuid, version: i64, input: &ClientInput) -> Result<Client> {
        sqlx::query_as("UPDATE sso_clients SET name=$1,domain=$2,redirect_uris=$3,scopes=$4,enabled=$5,version=version+1,updated_at=now() WHERE id=$6 AND version=$7 RETURNING *")
            .bind(&input.name).bind(&input.domain).bind(&input.redirect_uris).bind(&input.scopes)
            .bind(input.enabled).bind(id).bind(version)
            .fetch_optional(&self.0).await?.ok_or(IdentityError {code:"version_conflict",status:409})
    }
    async fn rotate_secret(&self, id: Uuid, version: i64, hash: &str) -> Result<Client> {
        sqlx::query_as("UPDATE sso_clients SET secret_hash=$1,version=version+1,updated_at=now() WHERE id=$2 AND version=$3 AND client_type='confidential' RETURNING *")
            .bind(hash).bind(id).bind(version).fetch_optional(&self.0).await?
            .ok_or(IdentityError {code:"version_conflict",status:409})
    }
    async fn list_clients(&self, after: Option<Uuid>, limit: i64) -> Result<Vec<Client>> {
        Ok(sqlx::query_as(
            "SELECT * FROM sso_clients WHERE ($1::uuid IS NULL OR id>$1) ORDER BY id LIMIT $2",
        )
        .bind(after)
        .bind(limit)
        .fetch_all(&self.0)
        .await?)
    }
    async fn browser_user(&self, hash: &str) -> Result<Option<Uuid>> {
        Ok(sqlx::query_scalar("SELECT b.user_id FROM identity_browser_logins b JOIN identity_users u ON u.id=b.user_id WHERE b.token_hash=$1 AND b.expires_at>now() AND u.enabled")
            .bind(hash).fetch_optional(&self.0).await?)
    }
    async fn save_attempt(&self, state_hash: &str, attempt: LoginAttempt) -> Result<()> {
        sqlx::query("INSERT INTO identity_login_attempts(state_hash,browser_hash,nonce,google_verifier,intent,expires_at) VALUES($1,$2,$3,$4,$5,$6)")
            .bind(state_hash).bind(attempt.browser_hash).bind(attempt.nonce).bind(attempt.verifier)
            .bind(sqlx::types::Json(attempt.intent)).bind(Utc::now()+Duration::minutes(10)).execute(&self.0).await?;
        Ok(())
    }
    async fn consume_attempt(&self, state_hash: &str, browser_hash: &str) -> Result<LoginAttempt> {
        let row=sqlx::query("DELETE FROM identity_login_attempts WHERE state_hash=$1 AND browser_hash=$2 AND expires_at>now() RETURNING nonce,google_verifier,intent")
            .bind(state_hash).bind(browser_hash).fetch_optional(&self.0).await?.ok_or_else(||IdentityError::bad("invalid_grant"))?;
        let intent: sqlx::types::Json<AuthorizeRequest> = row.try_get("intent")?;
        Ok(LoginAttempt {
            browser_hash: browser_hash.into(),
            nonce: row.try_get("nonce")?,
            verifier: row.try_get("google_verifier")?,
            intent: intent.0,
        })
    }
    async fn upsert_google_user(&self, identity: GoogleIdentity) -> Result<Uuid> {
        sqlx::query_scalar("INSERT INTO identity_users(id,google_subject,email,name,avatar) VALUES($1,$2,$3,$4,$5) ON CONFLICT(google_subject) DO UPDATE SET email=excluded.email,name=excluded.name,avatar=excluded.avatar,enabled=CASE WHEN $6 THEN TRUE ELSE identity_users.enabled END,updated_at=now() WHERE identity_users.enabled OR $6 RETURNING id")
            .bind(Uuid::new_v4()).bind(identity.subject).bind(&identity.email).bind(identity.name).bind(identity.avatar).bind(crate::domain::policies::system_root::is_system_root(&identity.email))
            .fetch_optional(&self.0).await?.ok_or_else(IdentityError::forbidden)
    }
    async fn save_browser_login(&self, hash: &str, user: Uuid) -> Result<()> {
        sqlx::query(
            "INSERT INTO identity_browser_logins(token_hash,user_id,expires_at) VALUES($1,$2,$3)",
        )
        .bind(hash)
        .bind(user)
        .bind(Utc::now() + Duration::hours(8))
        .execute(&self.0)
        .await?;
        Ok(())
    }
    async fn delete_browser_login(&self, hash: &str) -> Result<()> {
        sqlx::query("DELETE FROM identity_browser_logins WHERE token_hash=$1")
            .bind(hash)
            .execute(&self.0)
            .await?;
        Ok(())
    }
    async fn save_code(
        &self,
        hash: &str,
        client: Uuid,
        user: Uuid,
        intent: &AuthorizeRequest,
    ) -> Result<()> {
        let scopes: Vec<_> = intent.scope.split_whitespace().map(str::to_owned).collect();
        sqlx::query("INSERT INTO identity_authorization_codes(code_hash,user_id,sso_client_id,redirect_uri,challenge,scopes,expires_at) VALUES($1,$2,$3,$4,$5,$6,$7)")
            .bind(hash).bind(user).bind(client).bind(&intent.redirect_uri).bind(&intent.code_challenge)
            .bind(scopes).bind(Utc::now()+Duration::seconds(120)).execute(&self.0).await?;
        Ok(())
    }
    async fn active_grant(&self, grant: Uuid, user: Uuid, client_id: &str) -> Result<bool> {
        Ok(sqlx::query_scalar("SELECT EXISTS(SELECT 1 FROM identity_grants g JOIN sso_clients c ON c.id=g.sso_client_id WHERE g.id=$1 AND g.user_id=$2 AND g.revoked_at IS NULL AND g.expires_at>now() AND c.enabled AND c.client_id=$3)")
            .bind(grant).bind(user).bind(client_id).fetch_one(&self.0).await?)
    }
    async fn revoke(&self, client: Uuid, hash: &str) -> Result<()> {
        sqlx::query("UPDATE identity_grants SET revoked_at=COALESCE(revoked_at,now()) WHERE sso_client_id=$1 AND id IN (SELECT grant_id FROM identity_refresh_tokens WHERE token_hash=$2)")
            .bind(client).bind(hash).execute(&self.0).await?;
        Ok(())
    }
    async fn begin(&self) -> Result<Box<dyn TokenTransaction>> {
        Ok(Box::new(PostgresTokenTransaction(self.0.begin().await?)))
    }
}

struct PostgresTokenTransaction(Transaction<'static, Postgres>);
#[async_trait]
impl TokenTransaction for PostgresTokenTransaction {
    async fn lock_client(&mut self, id: Uuid) -> Result<Client> {
        Ok(
            sqlx::query_as("SELECT * FROM sso_clients WHERE id=$1 FOR SHARE")
                .bind(id)
                .fetch_one(&mut *self.0)
                .await?,
        )
    }
    async fn consume_code(
        &mut self,
        hash: &str,
        client: Uuid,
        redirect: &str,
        challenge: &str,
    ) -> Result<CodeRecord> {
        sqlx::query_as("DELETE FROM identity_authorization_codes WHERE code_hash=$1 AND sso_client_id=$2 AND redirect_uri=$3 AND challenge=$4 AND expires_at>now() RETURNING user_id,scopes")
            .bind(hash).bind(client).bind(redirect).bind(challenge).fetch_optional(&mut *self.0).await?.ok_or_else(||IdentityError::bad("invalid_grant"))
    }
    async fn user_email(&mut self, id: Uuid) -> Result<String> {
        sqlx::query_scalar("SELECT email FROM identity_users WHERE id=$1 AND enabled FOR SHARE")
            .bind(id)
            .fetch_optional(&mut *self.0)
            .await?
            .ok_or_else(|| IdentityError::bad("invalid_grant"))
    }
    async fn create_grant(
        &mut self,
        id: Uuid,
        client: Uuid,
        code: &CodeRecord,
        expires: chrono::DateTime<Utc>,
    ) -> Result<()> {
        sqlx::query("INSERT INTO identity_grants(id,user_id,sso_client_id,scopes,expires_at) VALUES($1,$2,$3,$4,$5)")
            .bind(id).bind(code.user_id).bind(client).bind(&code.scopes).bind(expires).execute(&mut *self.0).await?;
        Ok(())
    }
    async fn insert_refresh(&mut self, hash: &str, grant: Uuid) -> Result<()> {
        sqlx::query("INSERT INTO identity_refresh_tokens(token_hash,grant_id) VALUES($1,$2)")
            .bind(hash)
            .bind(grant)
            .execute(&mut *self.0)
            .await?;
        Ok(())
    }
    async fn lock_grant(&mut self, hash: &str, client: Uuid) -> Result<GrantRecord> {
        sqlx::query_as("SELECT g.id,g.user_id,g.scopes,g.revoked_at,g.expires_at FROM identity_grants g JOIN identity_refresh_tokens t ON t.grant_id=g.id WHERE t.token_hash=$1 AND g.sso_client_id=$2 FOR UPDATE OF g")
            .bind(hash).bind(client).fetch_optional(&mut *self.0).await?.ok_or_else(||IdentityError::bad("invalid_grant"))
    }
    async fn refresh_consumed(&mut self, hash: &str) -> Result<bool> {
        let consumed: Option<chrono::DateTime<Utc>> = sqlx::query_scalar(
            "SELECT consumed_at FROM identity_refresh_tokens WHERE token_hash=$1",
        )
        .bind(hash)
        .fetch_one(&mut *self.0)
        .await?;
        Ok(consumed.is_some())
    }
    async fn revoke_grant(&mut self, id: Uuid) -> Result<()> {
        sqlx::query("UPDATE identity_grants SET revoked_at=COALESCE(revoked_at,now()) WHERE id=$1")
            .bind(id)
            .execute(&mut *self.0)
            .await?;
        Ok(())
    }
    async fn consume_refresh(&mut self, hash: &str) -> Result<()> {
        sqlx::query("UPDATE identity_refresh_tokens SET consumed_at=now() WHERE token_hash=$1")
            .bind(hash)
            .execute(&mut *self.0)
            .await?;
        Ok(())
    }
    async fn commit(self: Box<Self>) -> Result<()> {
        self.0.commit().await?;
        Ok(())
    }
}

impl From<sqlx::Error> for IdentityError {
    fn from(error: sqlx::Error) -> Self {
        if error
            .as_database_error()
            .is_some_and(|e| e.is_unique_violation())
        {
            Self {
                code: "conflict",
                status: 409,
            }
        } else {
            eprintln!(
                "Identity persistence operation failed ({})",
                error
                    .as_database_error()
                    .and_then(|e| e.code())
                    .as_deref()
                    .unwrap_or("connection/driver")
            );
            Self::internal()
        }
    }
}

macro_rules! row_mapping {
    ($model:ty, $($field:ident),+ $(,)?) => {
        impl<'r> sqlx::FromRow<'r,sqlx::postgres::PgRow> for $model {
            fn from_row(row: &'r sqlx::postgres::PgRow) -> std::result::Result<Self,sqlx::Error> {
                Ok(Self { $($field: row.try_get(stringify!($field))?),+ })
            }
        }
    };
}
row_mapping!(
    Client,
    id,
    client_id,
    name,
    domain,
    client_type,
    secret_hash,
    redirect_uris,
    scopes,
    enabled,
    version
);
row_mapping!(UserProfile, id, email, name, avatar);
row_mapping!(CodeRecord, user_id, scopes);
row_mapping!(GrantRecord, id, user_id, scopes, revoked_at, expires_at);

#[async_trait]
impl crate::application::ports::UserRepository for IdentityStore {
    async fn users(&self, after: Option<Uuid>, limit: i64) -> Result<Vec<UserAccount>> {
        Ok(sqlx::query_as("SELECT id,email,name,avatar,enabled,(lower(email)=$1) AS is_system_root FROM identity_users WHERE ($2::uuid IS NULL OR id>$2) ORDER BY id LIMIT $3")
            .bind(crate::domain::policies::system_root::SYSTEM_ROOT_EMAIL)
            .bind(after).bind(limit).fetch_all(&self.0).await?)
    }
    async fn user(&self, id: Uuid) -> Result<UserAccount> {
        sqlx::query_as("SELECT id,email,name,avatar,enabled,(lower(email)=$1) AS is_system_root FROM identity_users WHERE id=$2")
            .bind(crate::domain::policies::system_root::SYSTEM_ROOT_EMAIL)
            .bind(id).fetch_optional(&self.0).await?.ok_or(IdentityError {code:"not_found",status:404})
    }
    async fn set_user_enabled(&self, id: Uuid, enabled: bool) -> Result<UserAccount> {
        sqlx::query("UPDATE identity_users SET enabled=$1,updated_at=now() WHERE id=$2 AND lower(email)<>$3")
            .bind(enabled).bind(id).bind(crate::domain::policies::system_root::SYSTEM_ROOT_EMAIL)
            .execute(&self.0).await?;
        self.user(id).await
    }
    async fn revoke_user(&self, id: Uuid) -> Result<()> {
        let mut tx = self.0.begin().await?;
        sqlx::query(
            "UPDATE identity_grants SET revoked_at=COALESCE(revoked_at,now()) WHERE user_id=$1",
        )
        .bind(id)
        .execute(&mut *tx)
        .await?;
        sqlx::query("DELETE FROM identity_browser_logins WHERE user_id=$1")
            .bind(id)
            .execute(&mut *tx)
            .await?;
        tx.commit().await?;
        Ok(())
    }
}
row_mapping!(
    UserAccount,
    id,
    email,
    name,
    avatar,
    enabled,
    is_system_root
);
