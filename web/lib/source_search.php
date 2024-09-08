<?php

require_once __DIR__ . '/config.php';

// Pluggable content-source lookup. The site doesn't care where results come
// from as long as a driver returns the expected shape from search():
//
//     array(
//         array(
//             'name'      => string,    // human-readable title
//             'uri'       => string,    // URI handed to the ingest client
//             'username'  => string|null,
//             'badge'     => string|null,
//             'date'      => int,       // unix timestamp
//             'size'      => int,       // bytes
//             'comments'  => int,
//             'seeders'   => int,       // optional, used for scoring
//             'leechers'  => int,       // optional, used for scoring
//             'id_search' => bool,
//             'anonymous' => bool,
//         ),
//         ...
//     )
//
// Drivers live in web/lib/sources/. Add one and reference it by name from
// config('source.driver').

interface SourceSearch {
	public function search($query, $category = null);
}

function load_source_driver() {
	$driver = config('source.driver', 'example');
	$path = __DIR__ . '/sources/' . $driver . '.php';
	if (!file_exists($path)) {
		throw new RuntimeException("Source driver not found: $driver ($path)");
	}
	require_once $path;
	$class = ucfirst($driver) . 'SourceSearch';
	if (!class_exists($class)) {
		throw new RuntimeException("Source driver class missing: $class");
	}
	return new $class(config('source'));
}
