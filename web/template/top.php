<?php

// config
define("BASE", "/var/www/movieventure"); // also defined in .htaccess and accessible via getenv("BASE");
// ^ no trailing slash
define("EMAIL_DOMAIN", "example.com");
define("EMAIL_USER", "support");
define("EMAIL_NAME", "Movieventure");
define("DATABASE_HOST", "localhost");
define("DATABASE_USER", "movieventure");
define("DATABASE_PASSWORD", "3DRN43qqzyf3exaHnFr7cRrL");
define("DATABASE_NAME", "movieventure");
define("DATABASE_CHARSET", "utf8");

define("SESSION_TIMEOUT", 24 * 60); // 24 minutes in seconds (1440 is PHP timeout)

$base = BASE; // for legacy support

$currentCookieParams = session_get_cookie_params(); 

$rootDomain = '.example.com'; 

session_set_cookie_params( 
    0,
    "/",
    $rootDomain,
    true,
	true
); 
session_name("MOVIEVENTURE_PHP_SESSION_ID");
session_start();

$headers  = 'MIME-Version: 1.0' . "\r\n";
$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
$headers .= 'From: ' . EMAIL_NAME . ' <' . EMAIL_USER . '@' . EMAIL_DOMAIN . '>' . "\r\n" .
'Reply-To: ' . EMAIL_NAME . ' <' . EMAIL_USER . '@' . EMAIL_DOMAIN . '>' . "\r\n" .
'X-Mailer: PHP/' . phpversion();

$userinfo = array();

$db = new mysqli(DATABASE_HOST, DATABASE_USER, DATABASE_PASSWORD, DATABASE_NAME);
$db->set_charset(DATABASE_CHARSET);

date_default_timezone_set("UTC"); // by default use UTC for internal times

$ajax_nav = false;
if (isset($_GET['ajax_nav'])) {
	if ($_GET['ajax_nav'] == true) {
		$ajax_nav = true;
	}
}

function head($title, $heading, $auth = true, $breadcrumbs = array("Home" => "/"), $container_classes = "effect mainnav-lg", $return = false) {
	global $base, $userinfo, $session, $db, $ajax_nav;
	$default_values = array(
		2 => array("auth", true),
		3 => array("breadcrumbs", array("Home" => "/")),
		4 => array("container_classes", "effect mainnav-lg"),
		5 => array("return", false)
	);
	foreach (func_get_args() as $key => $val) {
		if ($val == NULL) {
			$$default_values[$key][0] = $default_values[$key][1];
		}
	}
	if ($auth == true) {
		$auth_result = auth((int)$auth);
		if (!is_array($auth_result)) {
			die(header('Location: /auth/login?returnto=' . urlencode($_SERVER['REQUEST_URI'])));
		}
		$userinfo = $auth_result[0];
		$session = $auth_result[1];
		date_default_timezone_set($userinfo['timezone']);
	}
	if ($heading == true && gettype($heading) == "boolean") {
		$heading = $title;
	}
	if ($return == true) {
		ob_start();
		require("$base/template/header.php");
		$return = ob_get_clean();
		return $return;
	} else {
		require("$base/template/header.php");
		return;
	}
}

function footer($die = true) {
	global $base;
	require("$base/template/footer.php");
	if ($die == true) {
		die();
	}
}

function email($to, $subject, $message, $replyto = false, $headers = NULL) {
	$replyto = ($replyto ? "$replyto" : EMAIL_NAME . ' <' . EMAIL_USER . '@' . EMAIL_DOMAIN . '>');
	if (!$headers || $headers == NULL) {
		$headers  = 'MIME-Version: 1.0' . "\r\n";
		$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
		$headers .= 'From: ' . EMAIL_NAME . ' <no-reply@' . EMAIL_DOMAIN . '.net>' . "\r\n" .
		'Reply-To: ' . $replyto . "\r\n" .
		'X-Mailer: PHP/' . phpversion();
	}
	return mail($to, $subject, $message, $headers);
}

function auth($auth_level = 1) {
	global $db;
	if (isset($_COOKIE['MOVIEVENTURE_SESSION_ID']) && isset($_COOKIE['MOVIEVENTURE_SESSION_NAME'])) {
		$q = $db->query("SELECT * FROM auth_sessions WHERE session_id = '".$db->real_escape_string($_COOKIE['MOVIEVENTURE_SESSION_ID'])."' AND session_name = '".$db->real_escape_string($_COOKIE['MOVIEVENTURE_SESSION_NAME'])."' AND (expires > ".time()." OR expires = 0) LIMIT 1") or die($db->error); //or die($db->error); // this is potentially a security risk if a user sees one of these errors
		if ($q->num_rows > 0) {
			$auth_session = $q->fetch_array(MYSQLI_ASSOC);
			if (md5($_SERVER['HTTP_USER_AGENT']) == $auth_session['fingerprint']) {
				$q = $db->query("SELECT * FROM users WHERE id = '".$db->real_escape_string($auth_session['uid'])."' LIMIT 1") or die($db->error);
				if ($q->num_rows > 0) {
					$userinfo = $q->fetch_array(MYSQLI_ASSOC);
					if ($auth_session['session'] == 1) {
						$db->query("UPDATE auth_sessions SET expires = '".$db->real_escape_string(time() + SESSION_TIMEOUT)."' WHERE id = '".$auth_session['id']."' LIMIT 1");
					}
					if ($auth_level == 2) {
						if ($userinfo['is_admin'] == 1) {
							return array($userinfo, $auth_session);
						}
					} else {
						return array($userinfo, $auth_session);
					}
				}
			}
		}
	}
	return false;
}

$timezones = timezone_identifiers_list();
