<?php
require("../template/top.php");
if ($auth = auth()) {
	if ($_FILES['upload']['error'] !== 0) {
		die(header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500));
	}
	
	$id = uniqid();
	$url = '/avatar/' . $id . '.png';
	$uri = BASE . $url;
	
	$file = $_FILES['upload']['tmp_name'];
	$info = getimagesize($file);
	switch ( $info[2] ) {
	  case IMAGETYPE_JPEG:  $image = imagecreatefromjpeg($file);  break;
	  case IMAGETYPE_GIF:   $image = imagecreatefromgif($file);   break;
	  case IMAGETYPE_PNG:   $image = imagecreatefrompng($file);   break;
		default: die(header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500));
	}
	$image = imagescale($image, 150, 150);
	imagepng($image, $uri);
	
	$userinfo = $auth[0];
	$db->query("UPDATE users SET avatar = '$url' WHERE id = '{$userinfo['id']}' LIMIT 1");
}