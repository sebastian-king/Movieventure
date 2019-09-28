<?php

require('../template/top.php');

if (auth()) {
	$api_host = 'https://api.example.com/api/search.php';

	header('Content-Type: application/json');

	if (!isset($_GET['q'])) {
		echo file_get_contents($api_host);
	} else {
		echo file_get_contents($api_host . '?q=' . urlencode($_GET['q']));
	}
} else {
	header("Location: /");
}
