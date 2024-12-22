use anyhow::{Context, Result};
use std::path::Path;
use tokio::process::Command;

pub struct Media {
    pub path: String,
    pub height: u32,
    pub size_bytes: u64,
}

// Recursively walk the input directory and probe each candidate video file
// with mediainfo. Anything under 50 MB or with "sample" in the filename is
// treated as a teaser / preview rip and dropped.
pub async fn scan(dir: &str) -> Result<Vec<Media>> {
    let mut out = Vec::new();
    let mut stack = vec![std::path::PathBuf::from(dir)];

    while let Some(path) = stack.pop() {
        let mut entries = tokio::fs::read_dir(&path)
            .await
            .with_context(|| format!("reading dir {}", path.display()))?;

        while let Some(entry) = entries.next_entry().await? {
            let p = entry.path();
            let ft = entry.file_type().await?;
            if ft.is_dir() {
                stack.push(p);
                continue;
            }
            if !is_video(&p) { continue; }
            let meta = entry.metadata().await?;
            if meta.len() < 50_000_000 { continue; }
            if p.to_string_lossy().to_lowercase().contains("sample") { continue; }

            let height = probe_height(&p).await.unwrap_or(0);
            out.push(Media {
                path: p.to_string_lossy().into_owned(),
                height,
                size_bytes: meta.len(),
            });
        }
    }

    Ok(out)
}

fn is_video(p: &Path) -> bool {
    matches!(
        p.extension().and_then(|s| s.to_str()).map(|s| s.to_lowercase()).as_deref(),
        Some("mp4" | "m4v" | "mkv" | "avi" | "ts")
    )
}

async fn probe_height(path: &Path) -> Result<u32> {
    let out = Command::new("mediainfo")
        .arg("--Output=Video;%Height%")
        .arg(path)
        .output()
        .await
        .context("running mediainfo")?;
    let s = String::from_utf8_lossy(&out.stdout);
    Ok(s.trim().parse::<u32>().unwrap_or(0))
}
