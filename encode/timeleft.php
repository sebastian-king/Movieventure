<?php
//$hash = "C13A0E9CC843EAFFECC1CDF06B6D91C7B7ABCAC6";

$hash = strtoupper($argv[1]);
$file = "/home/encode/logs/${hash}.log";
$CONF_PREFIX = "MOVIEVENTURE";

if (is_file($file)) {
	$f = file_get_contents($file);
	if (preg_match("/^{$CONF_PREFIX}_ENCODE_START/", $f)) {
		// INIT STATS
		preg_match("/{$CONF_PREFIX}_ENCODE_FILES_COUNT (\d+)/", $f, $m);
		$files_to_encode = $m[1];
		echo "Total compatible files found: $files_to_encode" . PHP_EOL;
		preg_match_all("/{$CONF_PREFIX}_ENCODE_FILE_FOUND_(\d+)_ENCODE_SETTINGS (.+)/", $f, $m);

		$total_encodes = count($m[0]);
		echo "Total files queued for encoding: $total_encodes" . PHP_EOL;

		foreach ($m[1] as $key => $file_number) {
			preg_match("/{$CONF_PREFIX}_ENCODE_FILE_FOUND_{$file_number}_FILE (.+)/", $f, $mm);
			echo "$file_number => " . $m[2][$key] . " (" . basename($mm[1]) . ")" . PHP_EOL;
		}

		preg_match_all("@{$CONF_PREFIX}_ENCODE_STATUS ENCODING ([0-9]+)@", $f, $encoding_status);
		//var_dump($encoding_status[1]);

		if (!preg_match("/{$CONF_PREFIX}_ENCODE_DONE/", $f)) {
			// CURRENT STATS
			$r = strrev($f);
			preg_match("/^\n(.+)/m", $r, $m);
			$t = strrev($m[1]);

			preg_match_all("/Duration(.+)/m", $f, $m);
			preg_match("/^Duration: (\d{2}):(\d{2}):(\d{2}).(\d{2}),/", array_pop($m[0]), $m_duration);
			$duration = ($m_duration[1] * 3600) + ($m_duration[2] * 60) + ($m_duration[3]) + ($m_duration[4] / 100);
			preg_match("/time=(\d{2}):(\d{2}):(\d{2}).(\d{2})/", $t, $m_done);
			$done = ($m_done[1] * 3600) + ($m_done[2] * 60) + ($m_done[3]) + ($m_done[4] / 100);
			$remaining = ($duration - $done);
			preg_match("/speed=[ ]*(\d+\.\d+)x/", $t, $m);
			$speed = $m[1];
			$timeleft = round($remaining * (1/$speed),2);
			$progress = round($done / $duration * 100, 2);

			echo "Files remaining after this one: " . ($total_encodes - count($encoding_status[1])). PHP_EOL;
			echo "Files finished so far: " . (count($encoding_status[1]) - 1) . PHP_EOL;
			echo "Duration: $duration ($m_duration[1]:$m_duration[2]:$m_duration[3].$m_duration[4])" . PHP_EOL;
			echo "Done: " . number_format($done, 2, '.', '') . " ($m_done[1]:$m_done[2]:$m_done[3].$m_done[4])" . PHP_EOL;
			echo "Remaining: " . number_format($remaining, 2, '.', '') . " (" . gmdate("H:i:s", $remaining) . "." . str_pad(round(($remaining - floor($remaining)),2) * 100, 2, "0", STR_PAD_LEFT) . ")" . PHP_EOL;
			echo "Speed: ${speed}x" . PHP_EOL;
			echo "Time left: " . number_format($timeleft, 2, '.', '') . " (" . gmdate("H:i:s", $timeleft) . "." . str_pad(round(($timeleft - floor($timeleft)),2) * 100, 2, "0", STR_PAD_LEFT) . ")" . PHP_EOL;
			echo "Progress: " . number_format($progress, 2, '.', '') . "%" . PHP_EOL;

		} else {
                        echo "Encoding has finished." . PHP_EOL;
                }
        } else {
                echo "Encoding has not begun." . PHP_EOL;
        }
}
?>
