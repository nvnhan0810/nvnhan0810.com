pub async fn run() -> Result<(), Box<dyn std::error::Error>> {
    let source = app_config::ConfigSource::load()?;
    let config = app_config::DatabaseConfig::from_source(&source)?;
    let resources = app_resources::AppResources::connect(
        config.connect_options(),
        &source.required("REDIS_URL")?,
        &source.required("REDIS_QUEUE_PREFIX")?,
    )
    .await?;
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
