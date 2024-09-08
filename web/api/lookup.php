<?php

require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/source_search.php';

// Look up media candidates for a given (imdbid, title, year) tuple.
//
// The actual fetching is delegated to the SourceSearch driver chosen by
// config('source.driver'). This file only does scoring and ranking; nothing
// here knows or cares which site is on the other end.

function lookup_media($imdbid, $title, $release_year) {
	$source = load_source_driver();

	if (!preg_match("@^tt\d{7,8}$@i", $imdbid) && preg_match("@^\d{7,8}$@", $imdbid)) {
		$imdbid = "tt" . $imdbid;
	}

	$primary_category = current(array_keys(config('source.categories', array(null => null))));

	$id_query  = "$title $release_year $imdbid";
	$gen_query = "$title $release_year";

	$results = array_merge(
		$source->search($id_query, $primary_category),
		$source->search($gen_query, $primary_category)
	);

	return rank_by_quality(
		$results,
		array('title' => $title, 'year' => $release_year, 'imdbid' => $imdbid)
	);
}

function rank_by_quality($results, $query) {
	$buckets = array(
		'id_vip'    => array(),
		'id_trust'  => array(),
		'id_other'  => array(),
		'gen_vip'   => array(),
		'gen_trust' => array(),
		'gen_other' => array(),
	);

	$title_re = str_replace(':', '.?', $query['title']);
	$year     = $query['year'];

	foreach ($results as $r) {
		if (!preg_match("@{$title_re}.*{$year}@i", $r['name'])) continue;

		$r['quality'] = score_release($r);
		if ($r['quality'] < -10) continue;

		$prefix = !empty($r['id_search']) ? 'id_' : 'gen_';
		$badge  = strtolower($r['badge'] ?? '');
		$bucket = $prefix . ($badge === 'vip' ? 'vip' : ($badge === 'trusted' ? 'trust' : 'other'));

		$buckets[$bucket][] = $r;
	}

	foreach ($buckets as &$b) {
		usort($b, function ($a, $b) { return $b['quality'] <=> $a['quality']; });
	}

	return array_values($buckets);
}

function score_release($r) {
	$q = 0;
	$name = ' ' . strtolower($r['name']) . ' ';

	if (strpos($name, ' brrip ') !== false)                              $q += 1.0;
	else if (preg_match('@\s(web|dvd)r?i?p?\s@', $name))                 $q += 0.75;
	else if (preg_match('@\s(cam|ts)r?i?p?\s@', $name))                  $q -= 100;

	$s = (int)$r['seeders'];
	if      ($s > 10000) $q += 2.0;
	else if ($s >  1000) $q += 1.5;
	else if ($s >   500) $q += 1.0;
	else if ($s >   250) $q += 0.5;
	else if ($s >   100) $q += 0.25;
	else if ($s >    50) $q += 0.15;
	else if ($s >    10) $q += 0.05;
	else if ($s >     0) $q += 0.01;
	else                 $q -= 1.0;

	$gb = $r['size'] / (1024 ** 3);
	if      ($gb > 5)   $q -= 0.5;
	else if ($gb > 3)   $q += 0.5;
	else if ($gb > 2)   $q += 1.0;
	else if ($gb > 1)   $q += 1.5;
	else if ($gb > 0.5) $q += 1.0;
	else                $q -= 0.25;

	if (!empty($r['anonymous'])) $q -= 0.5;

	return $q;
}
