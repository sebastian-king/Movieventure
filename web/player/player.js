// HLS player with WebSocket-driven state sync. Pairs with the playback
// subprotocol in web/socket/websocket-server-ssl.js (see handlePlayback
// there for the on-wire contract).

(function () {
	'use strict';

	var cfg = window.MV_PLAYER;
	var video = document.getElementById('video');
	var statusEl = document.getElementById('status');
	var resumeEl = document.getElementById('resume-pos');
	var bandwidthEl = document.getElementById('bandwidth');
	var rungEl = document.getElementById('rung');
	var bufferEl = document.getElementById('buffer');

	var hls = null;
	var ws = null;
	var lastSentAt = 0;
	var resumeHandled = false;

	function setStatus(cls, text) {
		statusEl.className = 'status status-' + cls;
		statusEl.textContent = text;
	}

	// HLS setup. We use hls.js everywhere except Safari (which has native
	// HLS via media source extensions). The currentLevel listener fires on
	// every ABR switch, and we forward those over the WS so that resume
	// picks the same rung the user was last on.
	function attachHls() {
		if (video.canPlayType('application/vnd.apple.mpegurl')) {
			video.src = cfg.master_url;
			video.addEventListener('loadedmetadata', maybeApplyResume);
			return;
		}
		if (!window.Hls || !window.Hls.isSupported()) {
			alert('Your browser cannot play HLS.');
			return;
		}
		hls = new window.Hls({
			capLevelToPlayerSize: true,
			startLevel: -1, // auto from ABR
		});
		hls.loadSource(cfg.master_url);
		hls.attachMedia(video);
		hls.on(window.Hls.Events.MANIFEST_PARSED, maybeApplyResume);
		hls.on(window.Hls.Events.LEVEL_SWITCHED, function (_e, data) {
			var level = hls.levels[data.level];
			if (!level) return;
			rungEl.textContent = level.height + 'p (' + Math.round(level.bitrate / 1000) + ' kbps)';
			bandwidthEl.textContent = Math.round((hls.bandwidthEstimate || 0) / 1000) + ' kbps';
		});
	}

	function maybeApplyResume() {
		// Resume is driven by the backend once it sends us the 'resume' message
		// after we say hello. Both the WS hello and the manifest-loaded events
		// can land in either order, so we wait for both.
		if (resumeHandled) return;
		resumeHandled = true;
		if (window.MV_RESUME && typeof window.MV_RESUME.playhead === 'number') {
			video.currentTime = window.MV_RESUME.playhead;
			resumeEl.textContent = formatSeconds(window.MV_RESUME.playhead);
			if (hls && typeof window.MV_RESUME.quality_level === 'number') {
				hls.currentLevel = window.MV_RESUME.quality_level;
			}
		} else {
			resumeEl.textContent = 'fresh start';
		}
	}

	function attachWs() {
		ws = new WebSocket(cfg.ws_uri, 'playback');

		ws.onopen = function () {
			setStatus('up', 'connected');
			ws.send(JSON.stringify({ kind: 'hello', user: cfg.user, media: cfg.media }));
		};

		ws.onmessage = function (ev) {
			var msg;
			try { msg = JSON.parse(ev.data); } catch (e) { return; }
			if (msg.kind === 'resume') {
				window.MV_RESUME = msg.state || {};
				if (video.readyState >= 1) maybeApplyResume();
			}
		};

		ws.onclose = function () { setStatus('down', 'disconnected'); };
		ws.onerror = function () { setStatus('down', 'error');        };
	}

	// Throttled state push. We only need to send at most once every 5 seconds
	// during steady-state playback, plus a final flush on close.
	function pushState() {
		if (!ws || ws.readyState !== WebSocket.OPEN) return;
		var now = Date.now();
		if (now - lastSentAt < 5000) return;
		lastSentAt = now;

		var level = hls && hls.levels && hls.levels[hls.currentLevel];
		var buffered = video.buffered.length
			? video.buffered.end(video.buffered.length - 1) - video.currentTime
			: 0;
		bufferEl.textContent = buffered.toFixed(1) + 's';

		ws.send(JSON.stringify({
			kind: 'state',
			state: {
				playhead:      video.currentTime,
				audio_track:   video.audioTracks  ? selectedTrackId(video.audioTracks)  : null,
				sub_track:     video.textTracks   ? selectedTrackId(video.textTracks)   : null,
				quality_level: hls ? hls.currentLevel : null,
				buffer_health: buffered,
				viewing_stats: {
					duration: video.duration || 0,
					paused:   video.paused,
				},
			},
		}));
	}

	function selectedTrackId(tracks) {
		for (var i = 0; i < tracks.length; i++) {
			if (tracks[i].enabled || tracks[i].mode === 'showing') return i;
		}
		return null;
	}

	function flushOnLeave() {
		// Best-effort final state push before the page goes away.
		try {
			lastSentAt = 0;
			pushState();
		} catch (e) {}
	}

	function formatSeconds(s) {
		s = Math.max(0, Math.floor(s));
		var h = Math.floor(s / 3600);
		var m = Math.floor((s % 3600) / 60);
		var ss = s % 60;
		return (h ? h + ':' : '') + (h ? String(m).padStart(2, '0') : m) + ':' + String(ss).padStart(2, '0');
	}

	attachWs();
	attachHls();
	setInterval(pushState, 1000);
	window.addEventListener('beforeunload', flushOnLeave);
})();
