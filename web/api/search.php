<?php

require('../template/top.php');
require_once __DIR__ . '/../lib/config.php';

if (!auth()) {
	header("Location: /");
	exit;
}

$api_host = config('app.metadata_api', '');
if (!$api_host) {
	header('Content-Type: application/json');
	echo json_encode(array('error' => 'metadata_api not configured'));
	exit;
}

header('Content-Type: application/json');
if (!isset($_GET['q'])) {
	echo file_get_contents($api_host);
} else {
	echo file_get_contents($api_host . '?q=' . urlencode($_GET['q']));
}
