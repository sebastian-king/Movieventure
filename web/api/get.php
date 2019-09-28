<?php

require('../template/top.php');

if (auth()) {
	$imdbid = $_GET['imdbid'];
	$title = $_GET['title'];
	$release_year = $_GET['year'];

	$a = array();

	require("find.php");

	$find = find_a_movie($imdbid, $title, $release_year);

	//file_get_contents("https://{$_SERVER['HTTP_HOST']}/api.find.php?

	$transmission_username = "seb";
	$transmission_password = "";

	$find = $find[0][0];

	$command = "transmission-remote 127.0.0.1:9100 --auth='{$transmission_username}:{$transmission_password}' --add '{$find['magnet_link']}'";

	exec($command, $output, $return_var);
	if ($return_var !== 0) {
		$a['error'] = true;
		$a['error_message'] = "Unable to start the download";
	}

	// transmission has begun
} else {
	header("Location: /");
	die();
}
