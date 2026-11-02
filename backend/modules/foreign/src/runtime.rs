//! Module startup and shutdown. Hosts supply connections and process signals;
//! Foreign owns queue settings and workers; the host owns shared connections.
use crate::{
    config::QueueConfig,
    infrastructure::{postgres_queue, transcript_queue},
};
use axum::Router;
use sqlx::PgPool;
use tokio::{sync::watch, task::JoinHandle};

type StartupError = Box<dyn std::error::Error>;

#[must_use = "Keep the module alive and call shutdown when the host stops"]
pub struct Foreign {
    router: Router,
    runtime: ForeignWorker,
}

impl Foreign {
    pub fn router(&self) -> Router {
        self.router.clone()
    }

    /// A handle the host can move into its process shutdown future.
    pub fn shutdown_handle(&self) -> ForeignShutdown {
        ForeignShutdown(self.runtime.shutdown.clone())
    }

    pub async fn shutdown(self) -> Result<(), tokio::task::JoinError> {
        // Release this module's handles without closing the shared pool.
        drop(self.router);
        self.runtime.shutdown().await
    }
}

#[derive(Clone)]
pub struct ForeignShutdown(watch::Sender<bool>);

impl ForeignShutdown {
    pub fn request_shutdown(&self) {
        let _ = self.0.send(true);
    }
}

/// Start the HTTP producer only; consumers run in `console queue:work`.
pub async fn start(
    identity: identity::IdentityState,
    pool: PgPool,
) -> Result<Foreign, StartupError> {
    let settings = QueueConfig::default();
    let queue = postgres_queue::Config::from_config(settings)?;
    let router =
        crate::presentation::http::routes::build_routes(identity, pool.clone(), queue.clone());
    let runtime = ForeignWorker::spawn(pool, queue, false);
    Ok(Foreign { router, runtime })
}

#[must_use = "Keep the worker alive and call shutdown when the host stops"]
pub struct ForeignWorker {
    shutdown: watch::Sender<bool>,
    worker: Option<JoinHandle<()>>,
}

impl ForeignWorker {
    fn spawn(pool: PgPool, queue: postgres_queue::Config, enabled: bool) -> Self {
        let (shutdown, signal) = watch::channel(false);
        let worker =
            enabled.then(|| tokio::spawn(transcript_queue::run(pool.clone(), queue, signal)));
        Self { shutdown, worker }
    }

    pub fn request_shutdown(&self) {
        let _ = self.shutdown.send(true);
    }

    /// Drain active work; shared connections remain available to other modules.
    pub async fn shutdown(self) -> Result<(), tokio::task::JoinError> {
        self.request_shutdown();
        match self.worker {
            Some(worker) => worker.await,
            None => Ok(()),
        }
    }
}

/// Dedicated worker startup uses the same Foreign configuration as the API.
pub async fn start_worker(pool: PgPool) -> Result<ForeignWorker, StartupError> {
    let queue = postgres_queue::Config::new()?;
    Ok(ForeignWorker::spawn(pool, queue, true))
}

#[cfg(test)]
mod tests {
    use super::*;
    use sqlx::postgres::PgPoolOptions;

    #[tokio::test]
    async fn shutdown_waits_for_worker_and_keeps_shared_pool_open() {
        let pool = PgPoolOptions::new()
            .connect_lazy("postgres://localhost/unused")
            .unwrap();
        let observed_pool = pool.clone();
        let (shutdown, mut signal) = watch::channel(false);
        let worker_pool = pool.clone();
        let worker = tokio::spawn(async move {
            signal.wait_for(|stopping| *stopping).await.unwrap();
            assert!(!worker_pool.is_closed());
        });
        let runtime = ForeignWorker {
            shutdown,
            worker: Some(worker),
        };
        runtime.shutdown().await.unwrap();
        assert!(!observed_pool.is_closed());
        observed_pool.close().await;
    }

    #[tokio::test]
    async fn shutdown_keeps_shared_pool_open_when_worker_panics() {
        let pool = PgPoolOptions::new()
            .connect_lazy("postgres://localhost/unused")
            .unwrap();
        let observed_pool = pool.clone();
        let (shutdown, _) = watch::channel(false);
        let runtime = ForeignWorker {
            shutdown,
            worker: Some(tokio::spawn(async { panic!("worker failed") })),
        };
        assert!(runtime.shutdown().await.unwrap_err().is_panic());
        assert!(!observed_pool.is_closed());
        observed_pool.close().await;
    }
}
