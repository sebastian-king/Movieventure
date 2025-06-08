<?php

// Copy this to config/config.local.php and fill in the values for your install.
// config.local.php is gitignored.

return array(

	'app' => array(
		'name'         => 'Movieventure',
		'domain'       => 'example.com',
		'admin_email'  => 'admin@example.com',
		'cookie_name'  => 'movieventure_session',
		'metadata_api' => '', // optional IMDB-ish metadata API
	),

	'database' => array(
		'driver'    => 'mysqli',
		'host'      => '127.0.0.1',
		'port'      => 3306,
		'username'  => '',
		'password'  => '',
		'database'  => 'movieventure',
	),

	// Pluggable content source. The driver name maps to a class in
	// web/lib/sources/. See web/lib/sources/example.php for the contract.
	// Categories are an arbitrary identifier the source driver knows how to use.
	'source' => array(
		'driver'       => 'example',
		'base_url'     => 'https://example.com',
		'search_path'  => '/search/{query}/{page}/{per_page}/{category}',
		'use_socks'    => false,
		// command prefix to wrap shell calls when use_socks is true; any tool
		// that forwards a child process's networking through a SOCKS proxy
		// works (proxychains, redsocks, etc.)
		'socks_wrapper'=> 'proxychains',
		'categories'   => array(
			'hd' => 207,
			'sd' => 201,
		),
		'min_response_bytes' => 500,
		'max_retries'        => 10,
	),

	// Pluggable ingest client. The driver name maps to a class in
	// web/lib/download_clients/. The default 'noop' driver accepts every URI
	// and does nothing; provide your own driver to wire it up to whatever
	// fetcher you use.
	'download_client' => array(
		'driver'    => 'noop',
		'host'      => '127.0.0.1',
		'port'      => 0,
		'username'  => '',
		'password'  => '',
	),

	// Encode worker. encode/hook.sh is the post-ingest entry point; it
	// dispatches to either the CPU pipeline (encode.pl, libx264, MP4 output,
	// works on any headless server) or the GPU pipeline (encode/nvenc.py,
	// h264_nvenc + HLS, requires a CUDA-capable card).
	//
	// driver: 'auto' detects nvidia-smi at runtime; 'cpu' and 'gpu' force.
	'encode' => array(
		'driver'      => 'auto',
		'input_root'  => '/var/lib/movieventure/downloads',
		'output_root' => '/var/lib/movieventure/encodes',
		'logs_root'   => '/var/log/movieventure/encode',
		'ffmpeg_bin'  => '/usr/bin/ffmpeg',
		'ladder' => array(
			array('name' => '1080p', 'height' => 1080, 'video_kbps' => 4500, 'audio_kbps' => 128, 'audio_channels' => 2),
			array('name' => '720p',  'height' =>  720, 'video_kbps' => 2500, 'audio_kbps' => 128, 'audio_channels' => 2),
			array('name' => '480p',  'height' =>  480, 'video_kbps' => 1100, 'audio_kbps' =>  96, 'audio_channels' => 2),
			array('name' => '360p',  'height' =>  360, 'video_kbps' =>  650, 'audio_kbps' =>  64, 'audio_channels' => 1),
		),
		'hls_segment_seconds' => 4,
	),

	'tls' => array(
		'cert_path' => '/etc/ssl/certs/movieventure.pem',
		'key_path'  => '/etc/ssl/private/movieventure.key',
	),

	'websocket' => array(
		'port'       => 8888,
		'subprotocols' => array('echo', 'messages', 'notifications', 'playback'),
	),

	'integrations' => array(
		'pushbullet' => array(
			'enabled'      => false,
			'client_id'    => '',
			'access_token' => '',
			'device_iden'  => '',
		),
	),
);
