#!/usr/bin/nodejs
//process.env.NODE_TLS_REJECT_UNAUTHORIZED = '0';
 
var WebSocketServer = require('websocket').server;
var xmlrpc = require('xmlrpc')

//var WebSocketClient = require('websocket').client;
//var WebSocketFrame  = require('websocket').frame;
//var WebSocketRouter = require('websocket').router;
//var W3CWebSocket = require('websocket').w3cwebsocket;

//var http = require('http');
var https = require('https');
var fs = require('fs');
 
var server = https.createServer(
{
      key: fs.readFileSync( '/etc/letsencrypt/live/api.example.com/privkey.pem' ),
      cert: fs.readFileSync( '/etc/letsencrypt/live/api.example.com/cert.pem' )
}, 
function(request, response) {
    console.log((new Date()) + ' Received request for ' + request.url);
    response.writeHead(404);
    response.end();
});

server.listen(8888, '::', function() {
    console.log((new Date()) + ' Server is listening on port 8888');
});
 
wsServer = new WebSocketServer({
    httpServer: server,
    autoAcceptConnections: false
});
 
function originIsAllowed(origin) {
  return true;
}
 
wsServer.on('request', function(request) {
    if (!originIsAllowed(request.origin)) {
      request.reject();
      console.log((new Date()) + ' Connection from origin ' + request.origin + ' rejected.');
      return;
    }

	console.log(request);

	var acceptableProtocols = ["echo","messages","notifications"];
	
	console.log(request.requestedProtocols);
	
	if (request.requestedProtocols.length > 1) {
		for (var i = 0, len = request.requestedProtocols.length; i < len; i++) {
			if (acceptableProtocols.indexOf(request.requestedProtocols[i]) == -1) {
				request.reject();
			} else {
				protocol = request.requestProtocols[i];
				break;
			}
		}
	} else {
		var protocol = request.requestedProtocols[0];
	}

	var connection = request.accept(protocol, request.origin);
	console.log((new Date()) + ' Connection accepted.');

	connection.on('message', function(message) {
        if (message.type === 'utf8') {
            console.log('Received Message ['+request.protocol+']: ' + message.utf8Data);
            connection.sendUTF(JSON.stringify("MESSAGE: " + message.utf8Data));
                console.log('Reponse sent: ' + "MESSAGE: " + message.utf8Data);
        }
        else if (message.type === 'binary') {
            console.log('Received Binary Message of ' + message.binaryData.length + ' bytes');
            connection.sendBytes(message.binaryData);
        }
    });
    
    connection.on('close', function(reasonCode, description) {
        console.log((new Date()) + ' Peer ' + connection.remoteAddress + ' disconnected.');
    });
});
