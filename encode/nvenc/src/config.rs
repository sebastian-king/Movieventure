use anyhow::{Context, Result};
use serde::Deserialize;
use std::path::PathBuf;

#[derive(Debug, Deserialize, Clone)]
pub struct Config {
    pub ffmpeg_bin: String,
    pub input_root: PathBuf,
    pub output_root: PathBuf,
    pub logs_root: PathBuf,
    #[serde(default = "default_segment_secs")]
    pub hls_segment_secs: u32,
    #[serde(default = "default_preset")]
    pub nvenc_preset: String,
    #[serde(default = "default_tune")]
    pub nvenc_tune: String,
    #[serde(default = "default_rc")]
    pub nvenc_rc: String,
    pub ladder: Vec<Rung>,
}

#[derive(Debug, Deserialize, Clone)]
pub struct Rung {
    pub name: String,
    pub height: u32,
    pub video_kbps: u32,
    pub audio_kbps: u32,
    pub audio_chans: u32,
}

fn default_segment_secs() -> u32 { 4 }
fn default_preset() -> String { "p4".to_string() }
fn default_tune() -> String { "hq".to_string() }
fn default_rc() -> String { "vbr".to_string() }

impl Config {
    pub fn load() -> Result<Self> {
        // Config search order: $MV_ENCODE_CONFIG, ./encoder.toml, ../encoder.toml
        // (so the binary works both during cargo run and from target/release).
        let env_path = std::env::var("MV_ENCODE_CONFIG").ok();
        let candidates: Vec<PathBuf> = env_path
            .into_iter()
            .map(PathBuf::from)
            .chain([
                PathBuf::from("encoder.toml"),
                PathBuf::from("../encoder.toml"),
                PathBuf::from("/etc/movieventure/encoder.toml"),
            ])
            .collect();

        for candidate in &candidates {
            if candidate.exists() {
                let raw = std::fs::read_to_string(candidate)
                    .with_context(|| format!("reading {}", candidate.display()))?;
                let cfg: Self = toml::from_str(&raw)
                    .with_context(|| format!("parsing {}", candidate.display()))?;
                return Ok(cfg);
            }
        }
        Err(anyhow::anyhow!(
            "no config found; tried: {:?}",
            candidates.iter().map(|p| p.display().to_string()).collect::<Vec<_>>()
        ))
    }
}
