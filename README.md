# Movieventure

A self-hosted streaming stack. Contains a multi-bitrate HLS encoder (CPU and GPU pipelines), a WebSocket state bus that synchronises playback state across devices, and an HLS front-end that talks to both.

## What's in here

```
encode/
├── encode.pl                Perl CPU pipeline. Multi-bitrate libx264 + AAC, MP4
│                            output. Headless-server friendly, no GPU required.
├── hook.sh                  Generic post-ingest entry point. Picks gpu vs cpu
│                            based on whether nvidia-smi is present.
└── nvenc/                   Rust GPU pipeline. h264_nvenc + HLS, per-rung
                             ffmpeg children spawned in parallel.

web/
├── api/
│   ├── lookup.php           Generic content-source lookup. Pluggable driver
│   │                        via web/lib/sources/.
│   ├── request.php          Send a URI to the configured ingest client.
│   └── search.php           Metadata-API proxy.
├── auth/                    Login / register / reset / logout.
├── lib/
│   ├── config.php           Array-config loader.
│   ├── source_search.php    SourceSearch interface + driver loader.
│   ├── sources/             SourceSearch implementations.
│   ├── download_client.php  DownloadClient interface + driver loader.
│   └── download_clients/    DownloadClient implementations.
├── player/                  HLS player + WebSocket state-sync client.
├── socket/                  TLS WebSocket state bus (Node.js).
├── me/                      Per-user pages: profile, settings, integrations.
└── template/                Page chrome shared across the site.

config/                      Array-config schema + .example file.
sql/schema.sql               The bits of the schema that are public-relevant.
```

## How the pieces fit together

Item ingestion isn't part of the open-source release; you wire up whatever fetcher you like and have it call `encode/hook.sh` with `INPUT_NAME`, `INPUT_HASH` and `INPUT_DIR` set. The hook decides between the CPU and GPU pipelines and execs the right one.

The GPU pipeline (`encode/nvenc/`) is a Rust binary that spawns one ffmpeg subprocess per rung in the ABR ladder. Each rung writes its own HLS playlist and segments; a master playlist is emitted at the end so players see every variant that finished. Encoding is fully concurrent, so playback can begin against the lowest-bitrate rung the moment its first segment lands while the higher rungs are still working.

The CPU pipeline (`encode.pl`) is the original Perl orchestrator, kept for headless servers without an NVIDIA card. Same ladder, libx264 instead of h264_nvenc, MP4 output instead of segmented HLS.

Once an item is encoded, the player at `web/player/` opens an HLS source against the master playlist and, in parallel, a WebSocket connection to the state bus at `web/socket/`. The bus tracks playhead, buffer health, selected audio and subtitle tracks and quality level per `(user, media)` pair. Closing the page flushes a final snapshot; opening the player again sends that snapshot back so the player can resume from exactly where the previous session left off, including the rung the user was on.

The admin analytics tap is a fifth subprotocol on the same WebSocket endpoint that mirrors every state event live, used for the "who's watching what right now" view.

## Running

You need:

* PHP 8+ and MySQL 8+
* Node 18+ for `web/socket/`
* Rust toolchain (`cargo`) if you want the NVENC pipeline; otherwise the CPU pipeline is fine
* ffmpeg and mediainfo on `$PATH`

```
# database
mysql < sql/schema.sql

# WebSocket bus
cd web/socket && npm install && node websocket-server-ssl.js

# NVENC encoder (only if you have a CUDA-capable card)
cd encode/nvenc && cargo build --release
```

`config/config.example.php` documents every config variable. Copy it to `config/config.local.php` and fill in your values; everything reads from there.

## License

MIT.
