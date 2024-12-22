use crate::config::Config;
use anyhow::{Context, Result};
use std::path::PathBuf;

// Job identifies a single piece of media to encode. INPUT_NAME / INPUT_HASH /
// INPUT_DIR are set by encode/hook.sh from whatever upstream worker delivered
// the item.
pub struct Job {
    pub name: String,
    pub hash: String,
    pub input_dir: String,
}

impl Job {
    pub fn from_env(_cfg: &Config) -> Result<Self> {
        let name = std::env::var("INPUT_NAME").context("INPUT_NAME not set")?;
        let hash = std::env::var("INPUT_HASH").context("INPUT_HASH not set")?;
        let dir = std::env::var("INPUT_DIR").context("INPUT_DIR not set")?;

        if hash.len() != 40 || !hash.chars().all(|c| c.is_ascii_hexdigit()) {
            anyhow::bail!("INPUT_HASH must be a 40-char hex string");
        }
        let hash = hash.to_uppercase();

        Ok(Self {
            name,
            hash,
            input_dir: format!("{}/{}", dir.trim_end_matches('/'), name),
        })
    }

    pub fn output_dir(&self, cfg: &Config) -> PathBuf {
        cfg.output_root.join(&self.hash)
    }

    pub async fn prepare_output_dir(&self, cfg: &Config) -> Result<()> {
        // Lean on tokio's fs so we don't block the runtime if the output root
        // is on a slow disk.
        let dir = self.output_dir(cfg);
        tokio::fs::create_dir_all(&dir)
            .await
            .with_context(|| format!("creating output dir {}", dir.display()))?;

        // Drop an info.txt for downstream tooling. Matches the legacy Perl
        // pipeline's behaviour so existing players don't break.
        let info = dir.join("info.txt");
        tokio::fs::write(&info, &self.name).await.ok();
        Ok(())
    }
}
