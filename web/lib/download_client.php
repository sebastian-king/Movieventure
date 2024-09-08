<?php

require_once __DIR__ . '/config.php';

// Pluggable ingest-client interface. add($uri) hands a URI to whatever fetcher
// is wired up; stop / remove are management hooks the encode pipeline calls
// when it's done with the item.
//
// Pick a driver in config('download_client.driver'). Implementations live in
// web/lib/download_clients/.

interface DownloadClient {
	public function add($uri);
	public function stop($id);
	public function remove($id, $delete_data = false);
}

function load_download_client() {
	$driver = config('download_client.driver', 'noop');
	$path = __DIR__ . '/download_clients/' . $driver . '.php';
	if (!file_exists($path)) {
		throw new RuntimeException("Download client driver not found: $driver");
	}
	require_once $path;
	$class = ucfirst($driver) . 'DownloadClient';
	if (!class_exists($class)) {
		throw new RuntimeException("Download client class missing: $class");
	}
	return new $class(config('download_client'));
}
