#!/bin/bash
# Generic post-ingest hook. Reads the three required env vars
#
#     INPUT_NAME   human-readable item name
#     INPUT_HASH   40-character hex identifier (sha1 is fine)
#     INPUT_DIR    directory the item was written into
#
# then dispatches to either the Rust NVENC pipeline or the Perl CPU fallback.
# If your ingest worker uses different variable names, alias them in
# /etc/movieventure/env (sourced below) before this script runs.

set -e

if [ -f /etc/movieventure/env ]; then
	# shellcheck source=/dev/null
	. /etc/movieventure/env
fi

if [ -z "${INPUT_NAME:-}" ] || [ -z "${INPUT_HASH:-}" ] || [ -z "${INPUT_DIR:-}" ]; then
	echo "hook: INPUT_NAME / INPUT_HASH / INPUT_DIR must all be set" >&2
	exit 2
fi

export INPUT_NAME INPUT_HASH INPUT_DIR

HERE="$(cd "$(dirname "$0")" && pwd)"

# Driver selection: ENCODE_DRIVER overrides everything, otherwise check for an
# NVIDIA card and fall back to CPU.
DRIVER="${ENCODE_DRIVER:-auto}"
if [ "$DRIVER" = "auto" ]; then
	if command -v nvidia-smi >/dev/null 2>&1 && nvidia-smi -L >/dev/null 2>&1; then
		DRIVER="gpu"
	else
		DRIVER="cpu"
	fi
fi

case "$DRIVER" in
	gpu)
		BIN="$HERE/nvenc/target/release/mv-encode"
		if [ ! -x "$BIN" ]; then
			echo "hook: $BIN not built; falling back to CPU pipeline" >&2
			DRIVER="cpu"
		else
			exec "$BIN"
		fi
		;;
esac

# CPU pipeline. encode.pl reads the INPUT_* env vars; it doesn't care whether
# they were set by an upstream worker or by us.
exec /usr/bin/env perl "$HERE/encode.pl"
