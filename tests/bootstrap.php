<?php
declare(strict_types=1);

// comments: English; tabs used for indent

// Composer autoload
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($autoload)) {
	require $autoload;
}

// Enable logger gate
if (!defined('WP_DEBUG')) {
	define('WP_DEBUG', true);
}

// Provide a stable request marker for headers
if (!defined('SMARTDESK_REQ')) {
	define('SMARTDESK_REQ', "Timestamp: 1755609635.4039 / Request: abc123");
}

// Namespace compatibility for LogLevel:
// Prefer Utils\Support\LogLevel; if only Debug\Support\LogLevel exists, alias it.
if (!class_exists(\SmartDesk\Utils\Support\LogLevel::class) && class_exists(\SmartDesk\Debug\Support\LogLevel::class)) {
	class_alias(\SmartDesk\Debug\Support\LogLevel::class, \SmartDesk\Utils\Support\LogLevel::class);
}
