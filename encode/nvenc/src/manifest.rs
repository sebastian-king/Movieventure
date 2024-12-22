use crate::config::Rung;
use crate::ffmpeg::{playlist_path, Outcome};
use anyhow::{Context, Result};
use std::fmt::Write;
use std::path::Path;
use tokio::fs;

// Build the top-level master playlist that lists every rung that finished.
// Sorted by descending bandwidth so well-behaved players pick the highest rung
// they can sustain.
pub async fn write_master(output_dir: &Path, ladder: &[Rung], outcome: &Outcome) -> Result<()> {
    let mut buf = String::new();
    writeln!(buf, "#EXTM3U")?;
    writeln!(buf, "#EXT-X-VERSION:6")?;
    writeln!(buf, "#EXT-X-INDEPENDENT-SEGMENTS")?;

    let mut entries: Vec<&Rung> = ladder
        .iter()
        .filter(|r| outcome.completed.iter().any(|d| d == &r.name))
        .collect();
    entries.sort_by(|a, b| b.video_kbps.cmp(&a.video_kbps));

    for rung in entries {
        let bandwidth = (rung.video_kbps + rung.audio_kbps) * 1000;
        let resolution = if rung.height > 0 {
            // assume 16:9, rounded down to nearest 2 pixels for codec sanity
            let w = ((rung.height * 16 / 9) / 2) * 2;
            format!(",RESOLUTION={}x{}", w, rung.height)
        } else {
            String::new()
        };
        writeln!(
            buf,
            "#EXT-X-STREAM-INF:BANDWIDTH={bw},CODECS=\"avc1.640028,mp4a.40.2\"{res},NAME=\"{name}\"",
            bw = bandwidth,
            res = resolution,
            name = rung.name
        )?;
        // Relative path so the master playlist is portable across origins.
        let pl = playlist_path(output_dir, &rung.name);
        let rel = pl.strip_prefix(output_dir).unwrap_or(&pl);
        writeln!(buf, "{}", rel.display().to_string().replace('\\', "/"))?;
    }

    let master_path = output_dir.join("master.m3u8");
    fs::write(&master_path, buf)
        .await
        .with_context(|| format!("writing {}", master_path.display()))?;
    Ok(())
}
