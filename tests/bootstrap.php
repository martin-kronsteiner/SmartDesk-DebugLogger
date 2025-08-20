<?php
// SPDX-License-Identifier: GPL-3.0-or-later
declare(strict_types=1);

// Composer autoload
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($autoload)) {
	require $autoload;
}

// Enable logger gate in tests
if (!defined('WP_DEBUG')) define('WP_DEBUG', true);

// Stable request marker (used in string assertions)
if (!defined('SMARTDESK_REQ')) {
	define('SMARTDESK_REQ', "Timestamp: 1755609635.4039 / Request: abc123");
}