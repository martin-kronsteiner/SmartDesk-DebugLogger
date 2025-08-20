# SmartDesk DebugLogger

Leichter, unabhängiger Logger für PHP 8.1+ mit file rotation, error_log, Timern und optionalen WP-Helpers.

## Installation
composer require smartdesk/debuglogger

## Quickstart
```php
use SmartDesk\Debug\DebugLogger;
use SmartDesk\Debug\Support\LogLevel;

$logger = (new DebugLogger('smartdesk', LogLevel::DEBUG))
	->withFile(WP_CONTENT_DIR . '/uploads/sd-logs', 'smartdesk.log')
	->withErrorLog();

$logger->info('plugin booted', ['version' => '1.0.0']);

```
## Timer
```php
$logger->timerStart('import');
doImport();
$logger->timerStop('import', 'import finished');

```
## WP Hook Wrapping
```php
add_action('init', $logger->wrapHook('init', function () {
	// ...
}));

```
## Plugin integration
```php
<?php
/**
 * Plugin Name: SmartDesk Core
 */

use SmartDesk\Debug\DebugLogger;
use SmartDesk\Debug\Support\LogLevel;

// Composer Autoload sicherstellen (root composer oder plugin vendor)
require_once __DIR__ . '/vendor/autoload.php';

// Logger im Plugin-Container registrieren
add_action('plugins_loaded', function () {
	$logsDir = WP_CONTENT_DIR . '/uploads/smartdesk-logs';

	$GLOBALS['smartdesk_logger'] = (new DebugLogger('smartdesk', LogLevel::DEBUG))
		->withFile($logsDir, 'core.log', 2_000_000, 4, 10)
		->withErrorLog();

	$GLOBALS['smartdesk_logger']->registerGlobalHandlers();

	$GLOBALS['smartdesk_logger']->info('core_loaded', [
		'wp' => get_bloginfo('version'),
		'php' => PHP_VERSION,
	]);
});