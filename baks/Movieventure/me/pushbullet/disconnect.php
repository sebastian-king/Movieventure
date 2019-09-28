<?php
require("../../template/top.php");
if ($auth = auth()) {
	$userinfo = $auth[0];
	$db->query("UPDATE users SET pushbullet = NULL WHERE id = '{$userinfo['id']}' LIMIT 1") or die($db->error);
	$_SESSION['pushbullet_disconnected'] = true;
	header("Location: /me/pushbullet");
}