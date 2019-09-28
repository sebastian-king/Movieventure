#!/usr/bin/nodejs
//process.env.NODE_TLS_REJECT_UNAUTHORIZED = '0';
 
var xmlrpc = require('xmlrpc')

setTimeout(function () {
  // Creates an XML-RPC client. Passes the host information on where to
  // make the XML-RPC calls.
  var client = xmlrpc.createClient({ host: 'localhost', port: 9100, path: '/ingest/rpc'})
 
  // Sends a method call to the XML-RPC server
  client.methodCall('item-get', ['51'], function (error, value) {
    // Results of the method response
    console.log('Method response for \'item-get\': ' + value)
	console.log("Error: " + error);
  })
 
}, 1000)