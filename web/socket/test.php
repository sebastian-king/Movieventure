<!DOCTYPE html>
<meta charset="utf-8" />
<title>WebSocket Test</title>
<script language="javascript" type="text/javascript">
  <?php
  // be careful when using an IP with SSL certificates
  require_once __DIR__ . '/../lib/config.php';
  $ws_domain = config('app.domain', 'example.com');
  $ws_port   = config('websocket.port', 18888);
  ?>
  var wsUri = "wss://www.<?php echo htmlspecialchars($ws_domain); ?>:<?php echo (int)$ws_port; ?>";
  var output;

  function init() {
    output = document.getElementById("output");
    testWebSocket();
  }

  function testWebSocket() {
    websocket = new WebSocket(wsUri, "echo");
    websocket.onopen = function(evt) {
      onOpen(evt)
    };
    websocket.onclose = function(evt) {
      onClose(evt)
    };
    websocket.onmessage = function(evt) {
      onMessage(evt)
    };
    websocket.onerror = function(evt) {
      onError(evt)
    };
  }

  function onOpen(evt) {
    writeToScreen("CONNECTED");
    doSend("WebSocket rocks");
  }

  function onClose(evt) {
    writeToScreen("DISCONNECTED");
  }

  function onMessage(evt) {
    writeToScreen('<span style="color: blue;">RESPONSE: ' + evt.data + '</span>');
    websocket.close();
  }

  function onError(evt) {
    writeToScreen('<span style="color: red;">ERROR:</span> ' + evt.data);
  }

  function doSend(message) {
    writeToScreen("SENT: " + message);
    websocket.send(message);
  }

  function writeToScreen(message) {
    var pre = document.createElement("p");
    pre.style.wordWrap = "break-word";
    pre.innerHTML = message;
    output.appendChild(pre);
  }
  window.addEventListener("load", init, false);
</script>
<h2>WebSocket Test</h2>
<div id="output"></div>