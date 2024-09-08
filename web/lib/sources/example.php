<?php

// Reference implementation of the SourceSearch contract. Returns a single
// placeholder result so the site can boot in dev without a real source
// configured. Drop new drivers next to this one and reference by name from
// config('source.driver').

class ExampleSourceSearch implements SourceSearch {
	private $conf;

	public function __construct($conf) {
		$this->conf = $conf;
	}

	public function search($query, $category = null) {
		return array(
			array(
				'name'      => "[example] $query",
				'uri'       => '',
				'username'  => null,
				'badge'     => null,
				'date'      => time(),
				'size'      => 0,
				'comments'  => 0,
				'seeders'   => 0,
				'leechers'  => 0,
				'id_search' => false,
				'anonymous' => true,
			),
		);
	}
}
