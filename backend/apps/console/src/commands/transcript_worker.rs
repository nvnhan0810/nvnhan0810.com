pub async fn run() -> Result<(), Box<dyn std::error::Error>> {
    let source = app_config::ConfigSource::load()?;
    let redis = app_resources::SharedRedis::new(
        &source.required("REDIS_URL")?,
        &source.required("REDIS_QUEUE_PREFIX")?,
    )?;
    let telemetry = app_telemetry::Telemetry::init(&source, redis.clone())?;
    let result = run_worker(&source, redis).await;
    if result.is_err() {
        tracing::error!("Dedicated transcript worker failed");
    }
    telemetry.shutdown().await;
    result
}

async fn run_worker(
    source: &app_config::ConfigSource,
    redis: app_resources::SharedRedis,
) -> Result<(), Box<dyn std::error::Error>> {
    let config = app_config::DatabaseConfig::from_source(source)?;
    let resources =
        app_resources::AppResources::with_redis(config.connect_options(), redis).await?;
    let worker = foreign::start_worker(resources.database.clone(), resources.redis.clone()).await?;
    #[cfg(unix)]
    {
        let mut terminate =
            tokio::signal::unix::signal(tokio::signal::unix::SignalKind::terminate())?;
        tokio::select! {result=tokio::signal::ctrl_c()=>result?,_=terminate.recv()=>{}}
    }
    #[cfg(not(unix))]
    tokio::signal::ctrl_c().await?;
    let result = worker.shutdown().await;
    resources.shutdown().await;
    result?;
    Ok(())
}
