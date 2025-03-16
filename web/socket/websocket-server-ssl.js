#!/usr/bin/nodejs
//
// Movieventure WebSocket state bus.
//
// Five subprotocols, all multiplexed onto one TLS-terminated WS endpoint:
//
//   playback       client <-> backend per-(user, media) playback state sync
//   notifications  server-push notifications to a specific user
//   messages       generic relay channel between connected users
//   echo           dev-only loopback for the test page
//   admin          read-only analytics tap (every state event is mirrored here)
//
// The big one is `playback`. Players open a connection per playback session
// and send {kind:"hello",user,media} as the first message; we look up the last
// known state for that pair, push it back so the player can resume from where
// the previous session left off, then keep the row in sync with whatever the
// player sends thereafter (playhead, buffer_health, audio_track, sub_track,
// quality_level, viewing_stats).

'use strict';

var WebSocketServer = require('websocket').server;
var https = require('https');
var fs = require('fs');
var mysql = require('mysql');

var TLS_KEY  = process.env.TLS_KEY_PATH  || '/etc/ssl/private/movieventure.key';
var TLS_CERT = process.env.TLS_CERT_PATH || '/etc/ssl/certs/movieventure.pem';
var WS_PORT  = parseInt(process.env.WS_PORT || '8888', 10);

var db = mysql.createPool({
	host:               process.env.DB_HOST     || '127.0.0.1',
	user:               process.env.DB_USER     || '',
	password:           process.env.DB_PASSWORD || '',
	database:           process.env.DB_NAME     || '',
	connectionLimit:    16,
});

var server = https.createServer(
	{
		key:  fs.readFileSync(TLS_KEY),
		cert: fs.readFileSync(TLS_CERT),
	},
	function (req, res) {
		res.writeHead(404);
		res.end();
	}
);
server.listen(WS_PORT, '::', function () {
	console.log((new Date()) + ' WS state bus listening on :' + WS_PORT);
});

var wsServer = new WebSocketServer({
	httpServer: server,
	autoAcceptConnections: false,
});

var ACCEPTABLE_PROTOCOLS = ['playback', 'notifications', 'messages', 'echo', 'admin'];

var registry = {
	byUser:     {},   // user_id -> [connection]
	adminTaps:  [],
	playbackByConn: {}, // connection.id -> { user, media, last_state }
};

var nextConnectionId = 0;

wsServer.on('request', function (request) {
	var protocol = null;
	for (var i = 0; i < request.requestedProtocols.length; i++) {
		if (ACCEPTABLE_PROTOCOLS.indexOf(request.requestedProtocols[i]) !== -1) {
			protocol = request.requestedProtocols[i];
			break;
		}
	}
	if (protocol === null) {
		request.reject(400, 'no acceptable protocol');
		return;
	}

	var connection = request.accept(protocol, request.origin);
	connection.id = nextConnectionId++;
	connection.protocol_used = protocol;
	console.log((new Date()) + ' accept #' + connection.id + ' proto=' + protocol);

	if (protocol === 'admin') {
		registry.adminTaps.push(connection);
	}

	connection.on('message', function (message) {
		if (message.type !== 'utf8') return; // we don't accept binary
		handleMessage(connection, message.utf8Data);
	});

	connection.on('close', function () {
		console.log((new Date()) + ' close  #' + connection.id);
		// Persist a final state snapshot before tearing the row out of memory.
		if (registry.playbackByConn[connection.id]) {
			persistPlayback(registry.playbackByConn[connection.id], true);
			delete registry.playbackByConn[connection.id];
		}
		// Drop from user registry
		for (var uid in registry.byUser) {
			var idx = registry.byUser[uid].indexOf(connection);
			if (idx !== -1) registry.byUser[uid].splice(idx, 1);
		}
		// Drop from admin taps
		var ai = registry.adminTaps.indexOf(connection);
		if (ai !== -1) registry.adminTaps.splice(ai, 1);
	});
});

function handleMessage(connection, raw) {
	var payload;
	try { payload = JSON.parse(raw); }
	catch (e) {
		connection.sendUTF(JSON.stringify({ kind: 'error', reason: 'bad_json' }));
		return;
	}

	switch (connection.protocol_used) {
		case 'echo':
			connection.sendUTF(JSON.stringify({ kind: 'echo', payload: payload }));
			break;
		case 'playback':
			handlePlayback(connection, payload);
			break;
		case 'notifications':
			handleNotification(connection, payload);
			break;
		case 'messages':
			// Generic relay; clients pre-register their user id so we can
			// route by recipient. Authentication is the responsibility of
			// whatever upstream gateway terminates TLS.
			if (payload.kind === 'hello' && typeof payload.user === 'number') {
				if (!registry.byUser[payload.user]) registry.byUser[payload.user] = [];
				registry.byUser[payload.user].push(connection);
				connection.user_id = payload.user;
			} else if (payload.to && registry.byUser[payload.to]) {
				registry.byUser[payload.to].forEach(function (peer) {
					peer.sendUTF(JSON.stringify({ kind: 'message', from: connection.user_id, body: payload.body }));
				});
			}
			break;
	}
}

function handlePlayback(connection, payload) {
	if (payload.kind === 'hello') {
		if (!payload.user || !payload.media) {
			connection.sendUTF(JSON.stringify({ kind: 'error', reason: 'hello_missing_fields' }));
			return;
		}
		// Look up the last known state and ship it back so the player can
		// resume. This is the "per-account resumable playback" path.
		db.query(
			'SELECT playhead, audio_track, sub_track, quality_level, updated_at ' +
			'FROM playback_state WHERE user_id = ? AND media_id = ? LIMIT 1',
			[payload.user, payload.media],
			function (err, rows) {
				if (err) { console.error('db lookup', err); return; }
				var state = (rows && rows.length) ? rows[0] : null;
				registry.playbackByConn[connection.id] = {
					user: payload.user, media: payload.media, last: state || {},
				};
				connection.sendUTF(JSON.stringify({ kind: 'resume', state: state }));
			}
		);
		return;
	}

	if (payload.kind === 'state') {
		var ctx = registry.playbackByConn[connection.id];
		if (!ctx) {
			connection.sendUTF(JSON.stringify({ kind: 'error', reason: 'no_session' }));
			return;
		}
		// Merge what the client gave us into the in-memory state, then write
		// through to MySQL. Buffer health and viewing stats are persisted
		// alongside playhead so the analytics tap sees the full picture.
		Object.assign(ctx.last, payload.state);
		persistPlayback(ctx, false);
		broadcastToAdmins({ kind: 'playback', user: ctx.user, media: ctx.media, state: ctx.last });
	}
}

function persistPlayback(ctx, final) {
	var s = ctx.last;
	db.query(
		'INSERT INTO playback_state (user_id, media_id, playhead, audio_track, sub_track, quality_level, buffer_health, viewing_stats, updated_at) ' +
		'VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW()) ' +
		'ON DUPLICATE KEY UPDATE ' +
		' playhead       = VALUES(playhead), ' +
		' audio_track    = VALUES(audio_track), ' +
		' sub_track      = VALUES(sub_track), ' +
		' quality_level  = VALUES(quality_level), ' +
		' buffer_health  = VALUES(buffer_health), ' +
		' viewing_stats  = VALUES(viewing_stats), ' +
		' updated_at     = NOW()',
		[
			ctx.user, ctx.media,
			s.playhead       || 0,
			s.audio_track    || null,
			s.sub_track      || null,
			s.quality_level  || null,
			s.buffer_health  || null,
			s.viewing_stats ? JSON.stringify(s.viewing_stats) : null,
		],
		function (err) {
			if (err) console.error('db upsert', err);
		}
	);

	if (final) {
		broadcastToAdmins({ kind: 'session_end', user: ctx.user, media: ctx.media, state: ctx.last });
	}
}

function handleNotification(connection, payload) {
	if (payload.kind === 'subscribe' && typeof payload.user === 'number') {
		if (!registry.byUser[payload.user]) registry.byUser[payload.user] = [];
		registry.byUser[payload.user].push(connection);
		connection.user_id = payload.user;
		connection.sendUTF(JSON.stringify({ kind: 'subscribed' }));
	}
}

function broadcastToAdmins(payload) {
	var msg = JSON.stringify(payload);
	registry.adminTaps.forEach(function (admin) {
		try { admin.sendUTF(msg); } catch (e) {}
	});
}
