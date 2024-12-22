use anyhow::{anyhow, Context, Result};
use tracing::{error, info, warn};

mod config;
mod ffmpeg;
mod manifest;
mod probe;
mod work;

use config::Config;
use work::Job;

#[tokio::main]
async fn main() -> Result<()> {
    tracing_subscriber::fmt()
        .with_env_filter(
            tracing_subscriber::EnvFilter::try_from_default_env()
                .unwrap_or_else(|_| tracing_subscriber::EnvFilter::new("info")),
        )
        .init();

    let cfg = Config::load()?;
    let job = Job::from_env(&cfg).context("reading INPUT_* env vars")?;

    info!(name = %job.name, hash = %job.hash, "starting encode");
    job.prepare_output_dir(&cfg).await?;

    let media = probe::scan(&job.input_dir)
        .await
        .context("scanning input directory")?;
    if media.is_empty() {
        return Err(anyhow!("no encodable media found in {}", job.input_dir));
    }
    let primary = media
        .into_iter()
        .max_by_key(|m| m.size_bytes)
        .expect("non-empty");
    info!(file = %primary.path, height = primary.height, "selected primary input");

    let outcome = ffmpeg::encode_ladder(&cfg, &job, &primary).await?;

    manifest::write_master(&job.output_dir(&cfg), &cfg.ladder, &outcome).await?;

    info!(
        completed = outcome.completed.len(),
        skipped = outcome.skipped.len(),
        "encode finished"
    );
    for rung in &outcome.completed {
        info!(rung = %rung, "rung ready");
    }
    for (rung, reason) in &outcome.skipped {
        warn!(rung = %rung, reason = %reason, "rung skipped");
    }
    Ok(())
}
