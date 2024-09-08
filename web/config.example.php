<?php

// Bridge between the legacy constant-style config and the array config in
// config/config.example.php. Copy this file to web/config.php (gitignored)
// alongside config/config.local.php.

require_once __DIR__ . '/lib/config.php';

$c = load_config();

define('BASE',                __DIR__);
define('SESSION_NAME',        $c['app']['cookie_name']);
define('COOKIE_ROOT_DOMAIN',  $c['app']['domain']);

define('DATABASE_HOST',     $c['database']['host']);
define('DATABASE_USER',     $c['database']['username']);
define('DATABASE_PASSWORD', $c['database']['password']);
define('DATABASE_NAME',     $c['database']['database']);
define('DATABASE_CHARSET',  'utf8mb4');

define('EMAIL_NAME',   $c['app']['name']);
define('EMAIL_USER',   'no-reply');
define('EMAIL_DOMAIN', $c['app']['domain']);
