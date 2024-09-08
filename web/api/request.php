<?php

require('../template/top.php');
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/download_client.php';
require_once __DIR__ . '/lookup.php';

header('Content-Type: application/json');

if (!auth()) {
	http_response_code(401);
	die(json_encode(array('error' => 'unauthenticated')));
}

$imdbid = $_GET['imdbid'] ?? '';
$title  = $_GET['title']  ?? '';
$year   = $_GET['year']   ?? '';

if (!$imdbid || !$title || !$year) {
	http_response_code(400);
	die(json_encode(array('error' => 'missing imdbid, title or year')));
}

$ranked = lookup_media($imdbid, $title, $year);

// flatten the bucketed results and pick the highest-quality match
$best = null;
foreach ($ranked as $bucket) {
	if (!empty($bucket)) { $best = $bucket[0]; break; }
}

if ($best === null || empty($best['uri'])) {
	die(json_encode(array('error' => 'no candidates')));
}

$client = load_download_client();
$result = $client->add($best['uri']);

if (empty($result['ok'])) {
	die(json_encode(array('error' => 'download client rejected request', 'detail' => $result)));
}

echo json_encode(array(
	'ok'      => true,
	'queued'  => $best['name'],
	'size'    => $best['size'],
	'seeders' => $best['seeders'],
	'client'  => $result,
));
