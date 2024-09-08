<?php
require_once __DIR__ . '/../../lib/config.php';

$client_id = config('integrations.pushbullet.client_id', '');
$domain    = config('app.domain', 'example.com');

if (!$client_id) {
	http_response_code(503);
	die('Pushbullet integration not configured');
}

$redirect_uri = "https://www.$domain/me/pushbullet/connected";
header("Location: https://www.pushbullet.com/authorize?" . http_build_query(array(
	'client_id'     => $client_id,
	'redirect_uri'  => $redirect_uri,
	'response_type' => 'code',
)));
