<?php

require('../template/top.php');

if (auth()) {
	$imdbid = $_GET['imdbid'];
	$title = $_GET['title'];
	$release_year = $_GET['year'];

	$categories = array();
	$categories[] = 207; // HD Movies
	//$categories[] = 202; // Movies DVDR
	$categories[] = 201; // Movies

	$year = date('Y');

	if (!preg_match("@^tt\d{7,8}$@i", $imdbid)) {
		if (preg_match("@^\d{7,8}@i$", $imdbid)) {
			$imdbid = "tt" . $imdbid;
		} else {
			// error!
		}
	}

	function convert_to_bytes(string $from) {
		$units = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB'];
		$number = substr($from, 0, -3);
		$suffix = substr($from, -3);

		//B or no suffix
		if(is_numeric(substr($suffix, 0, 1))) {
			return preg_replace('/[^\d]/', '', $from);
		}

		$exponent = array_flip($units)[$suffix] ?? null;
		if($exponent === null) {
			return null;
		}

		return $number * (1024 ** $exponent); // 1000 for iba, 1024 ibi
	}

	function search_source($query, $category = NULL) {
		if (is_null($category)) {
			global $categories;
			$category = $categories[0];
		}
		//$search_results = shell_exec('wget -qO- https://thesource.rocks/search/' . rawurlencode($query) . '/0/99/' . $category);
		$search_results = shell_exec('socks_wrapper wget -qO- http://REDACTED.example.com/search/' . $query . '/0/99/' . $category);
		$i = 0;
		while (strlen($search_results) < 500) {
			if ($i > 10) {
				// error!
				break;
			}
			$search_results = shell_exec('socks_wrapper wget -qO- http://REDACTED.example.com/search/' . $query . '/0/99/' . $category);
			$i++;
		}
		if ($i > 10) {
			die(json_encode(array('error'=>2))); // strlen failed
		}
		return $search_results;
	}

	function parse_results($results, $query) {
		global $year;

		$anonymous_uploader = false;
		$id_search = false;
		if (preg_match("@ tt\d{7,8}$@i", $query)) {
			$id_search = true;
		}

		preg_match_all('@<div id="SearchResults"><table id="searchResult">.*?(<tr>.+)</table>\n</div>@ims', $results, $m);
		preg_match_all('@(?:<tr class="alt">|<tr>)(.+?)</tr>@ims', $m[1][0], $m);

		if (count($m[0]) == 0) {
			if (preg_match("@&nbsp;No hits. Try adding an asterisk in you search phrase.</h2>@i", $results)) {
				return array();
				//die(json_encode(array('error'=>3))); // no hits
			}
		}
		
		$return = array();
		$i = 0;
		foreach ($m[0] as $key => $title) {
			// item name
			preg_match('@<div class="detName">(.+?)</div>@ims', $title, $name);
			$name = trim(strip_tags($name[1]));

			// item uri link
			preg_match('@<a href="uri:(.+?)" title="@i', $title, $uri);
			$uri = "uri:${uri[1]}";

			// item username & user badges
			preg_match("@<a href=\"/user/(.+?)\"><img src=\".+?\" alt=\"(.+?)\" title=\"(.+?)\".*/></a>@i", $title, $user_badges);
			if (count($user_badges)) {
				$username = $user_badges[1];
				$badge = $user_badges[3];
			} else {
				preg_match("@<a.*?href=\"/user/(.+?)/\".*?>@i", $title, $username);
				if (empty($username[1])) {
					if (preg_match("@ULed by <i>Anonymous</i>@i", $title)) {
						$anonymous_uploader = true;
						$username = NULL;
					}
				} else {
					$username = $username[1];
					$badge = "NONE";
				}
			}

			// uploaded date & total size of item data
			preg_match("@<font class=\"detDesc\">Uploaded (.+)-(.+)&nbsp;(.+), Size (.+)&nbsp;(.+), ULed by@i", $title, $uploaded_size);
			$uploaded = strtotime("{$uploaded_size[2]}-{$uploaded_size[1]}-{$uploaded_size[3]}");
			if (!$uploaded) {
				if (preg_match("@\d{2}:\d{2}@", $uploaded_size[3])) {
					$str = "{$uploaded_size[2]}-{$uploaded_size[1]}-{$year} {$uploaded_size[3]}";
					$uploaded = strtotime("{$uploaded_size[2]}-{$uploaded_size[1]}-{$year} {$uploaded_size[3]}");
				}
			}
			$size = convert_to_bytes("{$uploaded_size[4]}{$uploaded_size[5]}");

			preg_match("@icon_comment.gif\" alt=\"This item has (\d+) comments.\"@i", $title, $comments);
			$comments = intval($comments);

			preg_match("@<td align=\"right\">(\d+)</td>\n\s*<td align=\"right\">(\d+)</td>@i", $title, $peers);
			$seeders = $peers[1];
			$leechers = $peers[2];

			//var_dump($title);

			$return[$i] = array();
			$return[$i]['name'] = $name;
			$return[$i]['uri'] = $uri;
			$return[$i]['username'] = $username;
			$return[$i]['badge'] = $badge;
			$return[$i]['date'] = $uploaded;
			$return[$i]['size'] = $size;
			$return[$i]['comments'] = $comments;
			$return[$i]['seeders'] = $seeders;
			$return[$i]['leechers'] = $leechers;
			$return[$i]['id_search'] = $id_search;
			$return[$i]['anonymous'] = $anonymous_uploader;
			$i++;
		}
		if (count($return) == 0) {
			var_dump($results);
		}
		return $return;
	}

	function sort_meta($a, $b) {
		$a_quality = $a['quality'];
		$b_quality = $b['quality'];

		if ($a_quality == $b_quality) {
			return 0;
		}
		return ($a_quality < $b_quality) ? 1 : -1;
	}

	function meta_quality($result) {
		$quality = 0;
		if (preg_match("@\sbrrip\s@i", $result['title'])) {
			$quality += 1;
		} else if (preg_match("@\s(web|dvd)r?i?p?\s@i", $result['title'])) {
			$quality + 0.75;
		} else if (preg_match("@\s(cam|ts)r?i?p?\s@i", $result['title'])) {
			$quality -= 100; // discard these later
		}

		if ($result['seeders'] > 10000) {
			$quality += 2;
		} else if ($result['seeders'] > 1000) {
			$quality += 1.5;
		} else if ($result['seeders'] > 500) {
			$quality += 1;
		} else if ($result['seeders'] > 250) {
			$quality += 0.5;
		} else if ($result['seeders'] > 100) {
			$quality += 0.25;
		} else if ($result['seeders'] > 50) {
			$quality += 0.15;
		} else if ($result['seeders'] > 10) {
			$quality += 0.05;
		} else if ($result['seeders'] > 0) {
			$quality += 0.01;
		} else if ($result['seeders'] == 0) {
			$quality -= 1;
		}

		$size_in_gb = $result['size'] / pow(1, 9);

		if ($size_in_gb > 5) {
			$quality -= 0.5;
		} else if ($size_in_gb > 3) {
			$quality += 0.5;
		} else if ($size_in_gb > 2) {
			$quality += 1;
		} else if ($size_in_gb > 1) {
			$quality += 1.5;
		} else if ($size_in_gb > 0.5) {
			$quality += 1;
		} else {
			$quality -= 0.25;
		}
		
		if ($result['anonymous']) {
			$quality -= 0.5;
		}

		return $quality;
	}

	function sort_results_by_quality($results, $search_query) {
		// how to use: date, comments?
		// are more comments indicative of a more active, better items? or a bad one with complaints
		// date: not too recent, not yet vetted
			// not too old, might be dead

		$sorted = array();
		$sorted[0] = array();
		$sorted[1] = array();
		$sorted[2] = array();
		$sorted[3] = array();
		$sorted[4] = array();
		$sorted[5] = array();

		foreach ($results as $key => $val) {
			// usually year gos after the title
			// sort out whitespace and delimiters
			$sanitized_title = str_replace(':', '.?', $search_query['title']);
			if (preg_match("@{$sanitized_title}.*{$search_query['year']}@i",
				$val['name'])) { // title and year
				
				echo "MATCHED:" . $val['name'] . PHP_EOL;
				
				$val['quality'] = meta_quality($val);
				if ($val['quality'] < -10) {
					continue;
				}
				
				if ($val['id_search'] == true) {
					if ($val['badge'] == 'VIP') {
						$sorted[0][] = $val;
					} else if ($val['badge'] == 'Trusted') {
						$sorted[1][] = $val;
					} else {
						$sorted[2][] = $val;
					}
				} else {
					if ($val['badge'] == 'VIP') {
							$sorted[3][] = $val;
					} else if ($val['badge'] == 'Trusted') {
							$sorted[4][] = $val;
					} else {
							$sorted[5][] = $val;
					}
				}
			} else {
				// discard, doesn't match title & year
			}

		}
		
		usort($sorted[0], 'sort_meta');
		usort($sorted[1], 'sort_meta');
		usort($sorted[2], 'sort_meta');
		usort($sorted[3], 'sort_meta');
		usort($sorted[4], 'sort_meta');
		usort($sorted[5], 'sort_meta');

		return $sorted;

		// 1: correct title, year, imdbid & VIP badge
			// quality:
				// brrip
				// dvdrip
				// webrip
				// exclude CAM
				// exclude TS
			// best seed:leech ratio
			// best filesize to time ratio
		// 2: correct title, year, imdbid & Trusted badge
			// quality:
				// ...
			// best seed:leech ratio
			// best filesize to time ratioo
		// 3: correect title, year, imdbid, no badge
			// same again
		// 4: correct title, year only (id_search false)
			// same again
		// ignore any other results
	}

	$search_query = "$title $release_year $imdbid";
	$id_results = parse_results(search_source($search_query), $search_query);

	$search_query = "$title $release_year";
	$nid_results = parse_results(search_source($search_query), $search_query);

	$results = array_merge($id_results, $nid_results);

	echo '<pre>Merged array has: ' . count($results) . ' entries' . PHP_EOL;
	
	//foreach ($results as $key => $val) {
		//echo $val['name'] . PHP_EOL;
	//}
	
	$keyed_query = array('title' => $title, 'year' => $release_year, 'imdbid' => $imdbid);
	var_dump($keyed_query);
	$sorted_results = sort_results_by_quality($results, $keyed_query);
	var_dump($sorted_results);
	
	if (count($sorted_results) > 30) {
		    echo '<meta http-equiv="refresh" content="10;" />';
	}
	
} else {
	header("Location: /");
}
