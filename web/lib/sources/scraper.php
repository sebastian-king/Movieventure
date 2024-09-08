<?php

// Generic HTML-table scraper. Fetches a search results page from the configured
// base_url, walks the response with the configured regex map, returns rows in
// the SourceSearch contract shape.
//
// Pattern map lives in config('source.patterns') so the same driver can be
// pointed at any site with a vaguely table-shaped search response.

class ScraperSourceSearch implements SourceSearch {
	private $conf;

	public function __construct($conf) {
		$this->conf = $conf;
	}

	public function search($query, $category = null) {
		$html = $this->fetch($query, $category);
		if (strlen($html) < (isset($this->conf['min_response_bytes']) ? $this->conf['min_response_bytes'] : 200)) {
			return array();
		}
		return $this->parse($html);
	}

	private function fetch($query, $category) {
		$url = $this->build_url($query, $category);
		$cmd = !empty($this->conf['use_socks'])
			? escapeshellcmd($this->conf['socks_wrapper']) . ' wget -qO- ' . escapeshellarg($url)
			: 'wget -qO- ' . escapeshellarg($url);

		$tries = 0;
		$max = isset($this->conf['max_retries']) ? (int)$this->conf['max_retries'] : 1;
		do {
			$out = shell_exec($cmd);
			if ($out !== null && strlen($out) >= ($this->conf['min_response_bytes'] ?? 200)) {
				return $out;
			}
			$tries++;
		} while ($tries < $max);

		return $out === null ? '' : $out;
	}

	private function build_url($query, $category) {
		$cat = $category;
		if (is_string($category) && isset($this->conf['categories'][$category])) {
			$cat = $this->conf['categories'][$category];
		}
		$repl = array(
			'{query}'    => rawurlencode($query),
			'{page}'     => 0,
			'{per_page}' => 99,
			'{category}' => $cat ?? '',
		);
		$path = strtr($this->conf['search_path'], $repl);
		return rtrim($this->conf['base_url'], '/') . $path;
	}

	// Sub-classable seam: derived drivers override this for site-specific
	// HTML shapes. Default returns nothing because no parser is universal.
	protected function parse($html) {
		return array();
	}
}
