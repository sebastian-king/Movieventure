<?php
require_once __DIR__ . '/../template/top.php';

if (!auth()) {
	header('Location: /auth/');
	exit;
}

$media_id = isset($_GET['m']) ? (int)$_GET['m'] : 0;
if (!$media_id) {
	http_response_code(400);
	die('missing media id');
}

$user_id = (int)$userinfo['id'];

// Look up the media row so we know which master playlist to load. The master
// playlist is emitted by the encoder under <output_root>/<hash>/master.m3u8;
// we keep the hash on the media row when ingest completes.
$q = $db->prepare("SELECT id, title, hash FROM media WHERE id = ? LIMIT 1");
$q->bind_param('i', $media_id);
$q->execute();
$res = $q->get_result();
$row = $res->fetch_assoc();
if (!$row || empty($row['hash'])) {
	http_response_code(404);
	die('media not found or not yet encoded');
}

$master_url = '/hls/' . $row['hash'] . '/master.m3u8';
$ws_domain  = COOKIE_ROOT_DOMAIN;
$ws_port    = 8888;

$page_title = htmlspecialchars($row['title']) . ' | ' . EMAIL_NAME;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?php echo $page_title; ?></title>
<link rel="stylesheet" href="player.css">
<script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.13/dist/hls.min.js"></script>
</head>
<body>
	<header>
		<a href="/" class="back">&larr;</a>
		<h1><?php echo htmlspecialchars($row['title']); ?></h1>
		<span id="status" class="status status-down">connecting</span>
	</header>

	<main>
		<video id="video" controls autoplay playsinline></video>
		<div class="meta">
			<dl>
				<dt>Resume from</dt>
				<dd id="resume-pos">-</dd>
				<dt>Bandwidth</dt>
				<dd id="bandwidth">-</dd>
				<dt>Current rung</dt>
				<dd id="rung">-</dd>
				<dt>Buffered ahead</dt>
				<dd id="buffer">-</dd>
			</dl>
		</div>
	</main>

	<script>
		window.MV_PLAYER = <?php echo json_encode(array(
			'media'      => (int)$row['id'],
			'user'       => $user_id,
			'master_url' => $master_url,
			'ws_uri'     => 'wss://' . $ws_domain . ':' . $ws_port,
		)); ?>;
	</script>
	<script src="player.js"></script>
</body>
</html>
