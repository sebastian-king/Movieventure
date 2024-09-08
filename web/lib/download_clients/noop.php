<?php

// Default driver. Pretends to accept every URI and does nothing. Useful for
// dev when the site needs to boot without a real ingest worker behind it.

class NoopDownloadClient implements DownloadClient {
	public function __construct($conf) {}
	public function add($uri)                       { return array('ok' => true, 'id' => null); }
	public function stop($id)                       { return array('ok' => true); }
	public function remove($id, $delete_data = false) { return array('ok' => true); }
}
