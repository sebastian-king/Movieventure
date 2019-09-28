var WebSocketServer = require('websocket').server;
//var http = require('http');
var https = require('https');
var fs = require('fs');
var os  = require('os-utils');
var exec = require('child_process').exec;
var mysql = require('mysql');
var cookie = require('cookie');
var md5 = require('md5');
var serialize = require('./serialize.js');
var unserialize = require('./unserialize.js');

const SESSION_TIMEOUT = 1440; // 24 * 60 (24 minutes in seconds)
GLOBAL.connections = [];

var mysqlconn = mysql.createConnection({
  host     : 'localhost',
  user     : 'movieventure',
  password : '3DRN43qqzyf3exaHnFr7cRrL',
  database : 'movieventure'
});

var server = https.createServer(
{
	key: fs.readFileSync( '/etc/letsencrypt/live/api.movieventure.net/privkey.pem' ),
	cert: fs.readFileSync( '/etc/letsencrypt/live/api.movieventure.net/cert.pem' )
},
function(request, response) {
    console.log((new Date()) + ' Received request for ' + request.url);
    response.writeHead(404);
    response.end();
});

server.listen(8888, function() {
    console.log((new Date()) + ' Server is listening on port 8888');
	mysqlconn.connect();
});

server.on('close', function() {
	mysqlconn.end();
});

wsServer = new WebSocketServer({
    httpServer: server,
    autoAcceptConnections: false
});

function originIsAllowed(origin) {
	return origin === 'https://www.movieventure.net';
}

setInterval(function() {
	var cpu_usage, bandwidth_usage;
	os.cpuUsage(function(cpu_usage) {
		exec('export TERM="xterm"; /usr/bin/bmon -o format:fmt="\\$(element:name):\\$(attr:rxrate:bytes):\\$(attr:txrate:bytes)\n",format:quitafter=2 -p eth0 | tail -n 2 | awk -F\':\' \'{print "100 * ("$2"+"$3")/12500000"}\' | tail -n 1 | bc', function(error, stdout, stderr) {
			wsServer.broadcast(JSON.stringify({"cpu_usage":cpu_usage,"bandwidth_usage":Number(stdout.replace(/[^0-9a-z\.]/g, ""))}));
		});
	});
}, 1000);

setInterval(function() {
	GLOBAL.connections.forEach(function(connection) {
		if (connection._auth_session.session === 1 && connection._auth_session.expires >= time()) {
			mysqlconn.query('UPDATE auth_sessions SET expires = ? WHERE id = ? LIMIT 1', [time() + SESSION_TIMEOUT, connection._auth_session.id]);
			connection._auth_session.expires = time() + SESSION_TIMEOUT;
		}
	});
	console.log("Authentication sessions updated successfully");
	console.log("Concurrent active connections: " + GLOBAL.connections.length);
}, 60000);

wsServer.on('request', function(request) {
	if (!originIsAllowed(request.origin)) {
		request.reject();
		console.log((new Date()) + ' Connection from origin ' + request.origin + ' rejected.');
		return;
	}

	var cookies = cookie.parse(request.httpRequest.headers.cookie);

	if (typeof auth_level == 'undefined') {
			var auth_level = 1;
	}
	if (cookies.MOVIEVENTURE_SESSION_ID !== 'undefined' && cookies.MOVIEVENTURE_SESSION_NAME !== 'undefined') {
			mysqlconn.query('SELECT * FROM auth_sessions WHERE session_id = ? AND session_name = ? AND (expires > ? OR expires = 0) LIMIT 1', [cookies.MOVIEVENTURE_SESSION_ID, cookies.MOVIEVENTURE_SESSION_NAME, time()], function(err, results, fields) {
					if (err) { throw err; }
					if (results.length == 1) {
							var auth_session = results[0];
						
						console.log(
							md5(request.httpRequest.headers['user-agent'] + (request.httpRequest.headers['accept-encoding'] + request.httpRequest.headers['accept-language'])),
							auth_session.fingerprint,
							request.httpRequest.headers['user-agent'],
							request.httpRequest.headers['accept-encoding'],
							request.httpRequest.headers['accept-language'],
							
							md5(request.httpRequest.headers['user-agent'])
						);
						
							if (md5(request.httpRequest.headers['user-agent']) == auth_session.fingerprint) {
									mysqlconn.query('SELECT * FROM users WHERE id = ? LIMIT 1', [auth_session.uid], function(err, results, fields) {
											if (err) { throw err; }
											if (results.length === 1) {
													userinfo = results[0];
													if (auth_session.session === 1) {
														mysqlconn.query('UPDATE auth_sessions SET expires = ? WHERE id = ? LIMIT 1', [time() + SESSION_TIMEOUT, auth_session.id]);
														auth_session.expires = time() + SESSION_TIMEOUT;
													}
													if ( (auth_level === 2 && userinfo.is_admin === 1) || (auth_level === 1 && userinfo) ) {

														var acceptableProtocols = ["echo","messages","notifications"];
														for (var i = 0, len = request.requestedProtocols.length; i < len; i++) {
																if (acceptableProtocols.indexOf(request.requestedProtocols[i]) === -1) {
																		request.reject();
																} else {
																		protocol = request.requestedProtocols[i];
																		break;
																}
														}
														
														var connection = request.accept(protocol, request.origin);
														connection._userinfo = userinfo;
														connection._cookies = cookies;
														connection._auth_session = auth_session;
														connection._protocol = protocol;
														connection._id = userinfo.id + '_' + Date.now();
														GLOBAL.connections.push(connection);

														console.log((new Date()) + ' Connection accepted (user: ' + userinfo.username + ').');
														
														// main start

														connection.on('message', function(message) {
															if (message.type === 'utf8') {
																console.log('Received Message ['+request.protocol+']: ' + message.utf8Data);
																if (JSON.parse(message.utf8Data)) {
																	var opts = JSON.parse(message.utf8Data);
																	switch(opts.switch) {
																		case "test_pushbullet":
																			var options = {
																			  hostname: 'api.pushbullet.com',
																			  port: 443,
																			  path: '/v2/pushes',
																			  method: 'POST',
																			  headers: { 'Access-Token': userinfo.pushbullet, 'Content-Type': 'application/json' }
																			};
																			opts.val.forEach(function(element, index) {
																				var req = https.request(options, function(res) {
																				  res.setEncoding('utf8');
																				  res.on('data', function (data) {
																					  if (index === opts.val.length-1 && !JSON.parse(data).error) {
																						  connection.sendUTF(JSON.stringify({"test_pushbullet":"message sent"}));
																					  } else if (index === opts.val.length-1) {
																						  connection.sendUTF(JSON.stringify({"test_pushbullet":"error occurred on pushbullet result"}));
																					  }
																				  });
																				});
																				req.write('{"body":"This is a test notification from Movieventure","title":"Movieventure Test Push","type":"note","device_iden":"'+element+'"}');
																				req.end();
																				req.on('error', function() {
																					connection.sendUTF(JSON.stringify({"test_pushbullet":"error occurred on pushbullet request"}));
																				});
																			});
																		break;
																		case "pushbullet_devices":
																			mysqlconn.query('UPDATE users SET pushbullets = ? WHERE id = ? LIMIT 1', [serialize.serialize(opts.val), userinfo.id]);
																		break;
																	}
																}
															} else if (message.type === 'binary') {
																console.log('Received Binary Message of ' + message.binaryData.length + ' bytes');
																connection.sendBytes(message.binaryData);
															}
														});

														connection.on('close', function(reasonCode, description) {
															var index = GLOBAL.connections.indexOf(connection);
															if (index > -1) {
																GLOBAL.connections.splice(index, 1);
															} else {
																console.log("ERROR: Unable to locate connection in connectios array");
															}
															console.log((new Date()) + ' Peer ' + connection.remoteAddress + ' disconnected.');
														});
														
														// main end

													} else {
														reject_request();
													}
											} else {
												reject_request();
											}
									});
							} else {
								reject_request();
							}
					} else {
						reject_request();
					}
			});
	}
	function reject_request() {
		console.log(request.remoteAddresses);
		request.reject();
		console.log((new Date()) + ' Connection from origin ' + request.origin + ' rejected/bad auth.');
	}
});

time = function() {
	return Math.floor(new Date().getTime() / 1000)
}
