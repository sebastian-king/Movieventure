#!/usr/bin/php
<?php

(PHP_SAPI !== 'cli' || isset($_SERVER['HTTP_USER_AGENT'])) && die("Sorry, CLI only" . PHP_EOL);

require(__DIR__ . '../template/top.php');
require_once __DIR__ . '/../lib/config.php';

$APP_NAME   = config('app.name',   'Movieventure');
$APP_DOMAIN = config('app.domain', 'example.com');

function random_string($length)
{
	$key = '';
	$keys = array_merge(range(0, 9), range('a', 'z'), range('A', 'Z'));
	for ($i = 0; $i < $length; $i++) {
		$key .= $keys[array_rand($keys)];
	}
	return $key;
}

parse_str(implode('&', array_slice($argv, 1)), $_GET);

if (!filter_var($_GET['email'], FILTER_VALIDATE_EMAIL)) {
	die("Please supply a valid e-mail address" . PHP_EOL);
}

$q = $db->query("SELECT * FROM users WHERE email = '" . $db->real_escape_string($_GET['email']) . "' LIMIT 1") or die($db->error);
if ($q->num_rows > 0) {
	die("The email address '{$_GET['email']}' already is assigned to an account" . PHP_EOL);
}

$q = $db->query("SELECT * FROM invitation_tokens WHERE email = '" . $db->real_escape_string($_GET['email']) . "' AND expires > NOW() AND used = 0 LIMIT 1") or die($db->error);
if ($q->num_rows > 0) {
	die("The email address '{$_GET['email']}' has already been invited and the token has not expired yet or been used" . PHP_EOL);
}

$token =  random_string(rand(40, 60));
echo "Generated token: $token" . PHP_EOL;

$expires = date('Y-m-d H:i:s', strtotime('+2 days'));
echo "Token expires (2 days from now): $expires (" . date("l jS \of F Y h:i:s A T", $expires) . ")" . PHP_EOL;

$q = $db->query("SELECT * FROM invitation_tokens WHERE token = '" . $db->real_escape_string($token) . "';") or die($db->error);
if ($q->num_rows > 0) {
	die("Fatal error, token is a duplicate" . PHP_EOL);
}

echo "Checks passed, will invite user" . PHP_EOL;

$db->query("INSERT INTO `invitation_tokens`
	(`token`,
	`email`,
	`expires`,
	`created`,
	`used`)
	VALUES
	(
	'" . $db->real_escape_string($token) . "',
	'" . $db->real_escape_string($_GET['email']) . "',
	'" . $db->real_escape_string($expires) . "',
	NOW(),
	'0'
	);
") or die($db->error());

$subject = "You have been invited to $APP_NAME";
$greeting = "Hello" . (isset($_GET['name']) ? " {$_GET['name']}" : "");
$body = "$greeting, <br>You have been invited to join <a href='https://www.$APP_DOMAIN'>$APP_DOMAIN</a>, an invite-only streaming platform. To register please follow this link:<br><a href='https://www.$APP_DOMAIN/auth/register/token/$token'>https://www.$APP_DOMAIN/auth/register/token/$token</a><br>This invitation will expire in 3 days.<br><br>Kindest regards,<br>The $APP_NAME Team";

if (email($_GET['email'], $subject, $body)) {
	echo "Success! User invited" . PHP_EOL;
} else {
	echo "Error! Unable to send e-mail" . PHP_EOL;
}
