//! One process owns all backend queue consumers. Register new job handlers here.
use std::time::Duration;
use tokio::{sync::watch, task::JoinSet};

pub async fn run() -> Result<(), Box<dyn std::error::Error>> {
    let source = app_config::ConfigSource::load()?;
    let config = app_config::DatabaseConfig::from_source(&source)?;
    let resources = app_resources::AppResources::connect(config.connect_options()).await?;
    // Fail at startup when migrations are missing, so Supervisor reports the error.
    sqlx::query("SELECT id FROM apalis.jobs LIMIT 0")
        .execute(&resources.database)
        .await?;
    sqlx::query("SELECT user_id FROM todo_pomodoro_states LIMIT 0")
        .execute(&resources.database)
        .await?;
    let queue = foreign::infrastructure::postgres_queue::Config::new()?;
    let telemetry = app_telemetry::Telemetry::init(&source, resources.database.clone())?;
    let (shutdown, signal) = watch::channel(false);
    let mut workers = JoinSet::new();
    let pool = resources.database.clone();
    let transcript_signal = signal.clone();
    workers.spawn(async move {
        foreign::infrastructure::transcript_queue::run(pool, queue, transcript_signal).await;
        Ok::<_, String>(())
    });
    let pool = resources.database.clone();
    let todo_config = todos::infrastructure::worker::PushConfig::from_source(&source);
    let todo_signal = signal.clone();
    workers.spawn(async move {
        todos::infrastructure::worker::run(pool, todo_config, todo_signal).await
    });
    let pool = resources.database.clone();
    workers.spawn(async move {
        app_telemetry::run_worker(&source, pool, signal)
            .await
            .map_err(|e| e.to_string())
    });
    tracing::info!("Backend queue worker started");
    let result: Result<(), Box<dyn std::error::Error>> = tokio::select! {
        result = shutdown_signal() => result.map_err(Into::into),
        result = workers.join_next() => Err(format!("Backend queue consumer stopped unexpectedly: {result:?}").into()),
    };
    if let Err(error) = &result {
        tracing::error!(%error, "Backend queue worker stopping");
    }
    let _ = shutdown.send(true);
    let drained = tokio::time::timeout(Duration::from_secs(90), async {
        while let Some(result) = workers.join_next().await {
            result.map_err(|e| e.to_string())??;
        }
        Ok::<_, String>(())
    })
    .await;
    workers.shutdown().await;
    telemetry.shutdown().await;
    resources.shutdown().await;
    result?;
    drained.map_err(|_| "Backend queue shutdown timed out")??;
    Ok(())
}

async fn shutdown_signal() -> Result<(), std::io::Error> {
    #[cfg(unix)]
    {
        let mut terminate =
            tokio::signal::unix::signal(tokio::signal::unix::SignalKind::terminate())?;
        tokio::select! { result = tokio::signal::ctrl_c() => result?, _ = terminate.recv() => {} }
    }
    #[cfg(not(unix))]
    tokio::signal::ctrl_c().await?;
    Ok(())
}
