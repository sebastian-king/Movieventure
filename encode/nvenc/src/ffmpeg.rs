use crate::config::{Config, Rung};
use crate::probe::Media;
use crate::work::Job;
use anyhow::{Context, Result};
use std::path::PathBuf;
use std::process::Stdio;
use tokio::io::{AsyncBufReadExt, BufReader};
use tokio::process::Command;
use tracing::{debug, info, warn};

pub struct Outcome {
    pub completed: Vec<String>,
    pub skipped: Vec<(String, String)>,
}

// Spawn one ffmpeg child per rung in parallel. Each rung writes its own HLS
// playlist + segments under <output_dir>/<rung_name>/. The master playlist
// in manifest.rs sets the per-rung references after this returns.
pub async fn encode_ladder(cfg: &Config, job: &Job, media: &Media) -> Result<Outcome> {
    let output_dir = job.output_dir(cfg);

    let mut tasks = Vec::new();
    for rung in &cfg.ladder {
        // Skip rungs that would be an upscale. Lots of legacy material is just
        // under the nominal height (475 px for "480p") so allow a 5% leeway.
        if media.height > 0 && (media.height as f32) < (rung.height as f32) * 0.95 {
            tasks.push(Skip {
                rung: rung.name.clone(),
                reason: format!("source {} < target {}", media.height, rung.height),
            }.into_task());
            continue;
        }

        let cfg = cfg.clone();
        let rung = rung.clone();
        let input = media.path.clone();
        let out = output_dir.clone();

        tasks.push(tokio::spawn(async move {
            encode_one(&cfg, &rung, &input, &out).await
        }));
    }

    let mut completed = Vec::new();
    let mut skipped  = Vec::new();
    for t in tasks {
        match t.await? {
            Ok(RungResult::Done(name))         => completed.push(name),
            Ok(RungResult::Skipped(name, why)) => skipped.push((name, why)),
            Err(e) => warn!(error = %e, "rung task failed"),
        }
    }
    Ok(Outcome { completed, skipped })
}

enum RungResult { Done(String), Skipped(String, String) }

struct Skip { rung: String, reason: String }
impl Skip {
    fn into_task(self) -> tokio::task::JoinHandle<Result<RungResult>> {
        tokio::spawn(async move { Ok(RungResult::Skipped(self.rung, self.reason)) })
    }
}

async fn encode_one(cfg: &Config, rung: &Rung, input: &str, out_root: &std::path::Path) -> Result<RungResult> {
    let rung_dir = out_root.join(&rung.name);
    tokio::fs::create_dir_all(&rung_dir).await
        .with_context(|| format!("creating {}", rung_dir.display()))?;

    let playlist = rung_dir.join("index.m3u8");
    let segment_pattern = rung_dir.join("seg_%05d.ts");

    info!(rung = %rung.name, "spawning ffmpeg");
    let mut cmd = Command::new(&cfg.ffmpeg_bin);
    cmd.kill_on_drop(true)
        .stdout(Stdio::null())
        .stderr(Stdio::piped());

    // hwaccel'd decode followed by NVENC encode keeps frames in VRAM end-to-end
    // and avoids round-tripping through host memory.
    cmd.args(&["-hide_banner", "-loglevel", "warning"]);
    cmd.args(&["-hwaccel", "cuda", "-hwaccel_output_format", "cuda"]);
    cmd.args(&["-i", input]);

    // scale_npp keeps the frame on the GPU. -2 in place of width preserves
    // the source aspect ratio and rounds to an even pixel count.
    cmd.args(&[
        "-vf",
        &format!("scale_npp=-2:{}:format=yuv420p", rung.height),
    ]);

    cmd.args(&["-c:v", "h264_nvenc"]);
    cmd.args(&["-preset", &cfg.nvenc_preset]);
    cmd.args(&["-tune",   &cfg.nvenc_tune]);
    cmd.args(&["-rc",     &cfg.nvenc_rc]);
    cmd.args(&["-b:v",    &format!("{}k", rung.video_kbps)]);
    cmd.args(&["-maxrate", &format!("{}k", (rung.video_kbps as f32 * 1.5) as u32)]);
    cmd.args(&["-bufsize", &format!("{}k", rung.video_kbps * 2)]);

    cmd.args(&["-c:a", "aac"]);
    cmd.args(&["-b:a", &format!("{}k", rung.audio_kbps)]);
    cmd.args(&["-ac", &rung.audio_chans.to_string()]);

    // HLS muxer config. Segment length matches the source GOP target so every
    // segment starts on a keyframe, which is what the player needs to switch
    // rungs without artefacts.
    let segs = cfg.hls_segment_secs;
    cmd.args(&["-g",            &(segs * 2).to_string()]);
    cmd.args(&["-keyint_min",   &(segs * 2).to_string()]);
    cmd.args(&["-sc_threshold", "0"]);

    cmd.args(&[
        "-f", "hls",
        "-hls_time",          &segs.to_string(),
        "-hls_list_size",     "0",
        "-hls_segment_type",  "mpegts",
        "-hls_flags",         "independent_segments+temp_file",
        "-hls_segment_filename", segment_pattern.to_str().unwrap(),
        playlist.to_str().unwrap(),
    ]);

    let mut child = cmd.spawn().context("spawning ffmpeg")?;

    // Drain stderr line by line so we can surface real errors. ffmpeg writes
    // status to stderr by convention; stdout is muted above.
    let stderr = child.stderr.take().expect("piped above");
    let rung_name = rung.name.clone();
    tokio::spawn(async move {
        let mut lines = BufReader::new(stderr).lines();
        while let Ok(Some(line)) = lines.next_line().await {
            debug!(rung = %rung_name, "ffmpeg: {line}");
        }
    });

    let status = child.wait().await.context("waiting for ffmpeg")?;
    if !status.success() {
        anyhow::bail!("ffmpeg exited with status {:?}", status);
    }
    Ok(RungResult::Done(rung.name.clone()))
}

// Helper to find the per-rung playlist on disk after encoding completes.
pub fn playlist_path(output_dir: &std::path::Path, rung: &str) -> PathBuf {
    output_dir.join(rung).join("index.m3u8")
}
