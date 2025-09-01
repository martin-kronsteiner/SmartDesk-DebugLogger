# SmartDesk DebugLogger

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](LICENSE) [![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/supported-versions.php) [![Packagist Version](https://img.shields.io/packagist/v/smartdesk/debug-logger.svg)](https://packagist.org/packages/smartdesk/debug-logger) [![Packagist Downloads](https://img.shields.io/packagist/dt/smartdesk/debug-logger.svg)](https://packagist.org/packages/smartdesk/debug-logger) [![CI](https://github.com/martin-kronsteiner/SmartDesk-DebugLogger/actions/workflows/ci.yml/badge.svg)](https://github.com/martin-kronsteiner/SmartDesk-DebugLogger/actions/workflows/ci.yml) [![Release](https://github.com/martin-kronsteiner/SmartDesk-DebugLogger/actions/workflows/release.yml/badge.svg)](https://github.com/martin-kronsteiner/SmartDesk-DebugLogger/actions/workflows/release.yml) ![WordPress](https://img.shields.io/badge/WordPress-Plugin-21759B?logo=wordpress)

\[EN\] Human-readable debug logger for WordPress development (PHP 8.1+).  
Generates structured multi-line entries with SmartDesk caller info, an optional hook status block (♻️/⏳), and a level prefix (emoji + label).  
Output goes to a sink: error\_log() by default, optionally a rotating file via setWriter().  
The output of a debug entry in debug.log or in your personal storage location looks something like this:

\[DE\] Menschenlesbarer Debug‑Logger für WordPress‑Entwicklung (PHP 8.1+).  
Erzeugt strukturierte, mehrzeilige Einträge mit SmartDesk‑Caller‑Info, optionalem Hook‑Status‑Block (♻️/⏳) und Level‑Präfix (Emoji + Label).  
Ausgabe geht an eine Senke (sink): standardmäßig error\_log(), optional rotierende Datei via setWriter().  
Die Ausgabe eines Debug-Eintrages in debug.log oder an deinem persönlichen Speicherort, sieht dann etwa so aus:

```
[20-Aug-2025 04:58:25 UTC] SmartDesk\Admin\WooMembershipPlanPricing\Main::{closure:SmartDesk\Admin\WooMembershipPlanPricing\Main::__construct():74}() - 04:58:25,56937
'Timestamp: 1755665904.8372 / Request: 394713'
C:\Users\SmartDesk\...\public\wp-includes\class-wp-hook.php:324

    hooks:
        ♻️ plugins_loaded (1 times fired)
        ♻️ init (1 times fired)
        ⏳ rest_api_init
        ⏳ admin_menu
        ⏳ admin_init
        ⏳ wp_enqueue_scripts
        ⏳ admin_enqueue_scripts
        ♻️ wp_loaded (1 times fired)
    AT states count (via getter, after wp_loaded): 9
    person => {
            id => 1
            wp_capabilities => {
                    administrator => true
                    }
            role => administrator
            nickname => admin
            locale => [empty-string]
            show_welcome_panel => false
            ...
            }
---------|---------|---------|---------|---------|---------|---------|---------|---------|---------|-SmartDesk
```

## Table of Contents

*   [Features](#features)
*   [Requirements](#requirements)
*   [Installation](#installation)
*   [Quick Start](#quick-start)
*   [Configuration](#configuration)
*   [API Reference](#api-reference)
*   [Usage Examples](#usage-examples)
*   [Plugin Integration](#plugin-integration)
*   [Testing](#testing)
*   [Notes](#notes)
*   [License](#license)

## Features

*   🎯 **WordPress-optimized**: Designed specifically for WordPress development
*   📊 **Hook status tracking**: Visual indicators for fired (♻️) and pending (⏳) hooks
*   🎨 **Human-readable output**: Structured, multi-line debug entries
*   📁 **Flexible output**: Error log or rotating file support
*   ⏱️ **Built-in timers**: Performance monitoring capabilities
*   🎭 **Log levels**: 8 different levels with emoji indicators
*   🔧 **Easy integration**: Simple setup for plugins and themes

## Requirements

*   PHP 8.1 or higher
*   WordPress (any recent version)
*   WP\_DEBUG enabled for active operation

## Installation

```
composer require smartdesk/debug-logger
```

## Quick Start

```php
use SmartDesk\Utils\DebugLogger;

// Simple logging
DebugLogger::debug(['user_id' => 123], 'init', 'User Login');

// With hook status
DebugLogger::log($data, ['plugins_loaded', 'init'], 'Startup Data');
```

## Configuration

### Basic Setup

```php
use SmartDesk\Utils\DebugLogger;

// Enable only in development
if (defined('WP_DEBUG') && WP_DEBUG === true) {
    DebugLogger::setMinLevel(LogLevel::DEBUG);
}
```

### Advanced Configuration

```php
// Custom log directory
$logDir = WP_CONTENT_DIR . '/uploads/smartdesk-logs';
DebugLogger::setWriter(
    DebugLogger::makeRotatingWriter($logDir, 'debug.log', 5_000_000, 5)
);

// Set minimum log level
DebugLogger::setMinLevel(LogLevel::WARNING);
```

### Output Configuration

#### Error Log Output (Default)

```php
// Uses PHP's error_log() function - no additional setup needed
DebugLogger::debug(['data' => 'value'], 'init', 'Debug Info');
```

#### Rotating File Output

```php
// Set up rotating file writer
$logDir = WP_CONTENT_DIR . '/uploads/smartdesk-logs';
DebugLogger::setWriter(
    DebugLogger::makeRotatingWriter(
        $logDir,        // Directory path
        'debug.log',    // Base filename
        5_000_000,      // Max file size in bytes (5MB)
        5               // Number of backup files to keep
    )
);
```

#### Custom Writer

```php
use SmartDesk\Utils\Handlers\CallbackHandler;

// Custom callback handler
DebugLogger::setWriter(new CallbackHandler(function($message) {
    // Send to external service, database, etc.
    file_put_contents('/custom/path/app.log', $message, FILE_APPEND);
}));
```

#### Log Level Configuration

```php
use SmartDesk\Utils\Support\LogLevel;

// Set minimum log level (only logs at this level or higher will be output)
DebugLogger::setMinLevel(LogLevel::WARNING);  // Only WARNING, ERROR, CRITICAL, ALERT, EMERGENCY

// Available levels (from lowest to highest):
// LogLevel::DEBUG     🐞
// LogLevel::INFO      ℹ️  
// LogLevel::NOTICE    📋
// LogLevel::WARNING   ⚠️
// LogLevel::ERROR     ❌
// LogLevel::CRITICAL  ☠️
// LogLevel::ALERT     🚨
// LogLevel::EMERGENCY ⛔
```

#### Request Tracking

```php
// Define request marker for log correlation
if (!defined('SMARTDESK_REQ')) {
    define(
        'SMARTDESK_REQ',
        'Timestamp: ' . ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))
        . ' / Request: ' . substr(md5($_SERVER['REQUEST_URI'] ?? ''), 0, 6)
        . ' / User: ' . (get_current_user_id() ?: 'guest')
    );
}
```

## API Reference

### Static Methods

#### `setWriter(WriterInterface $writer): void`

Sets the output destination for log messages.

```php
// Rotating file writer
DebugLogger::setWriter(
    DebugLogger::makeRotatingWriter('/path/to/logs', 'app.log', 5_000_000, 5)
);

// Custom callback writer
DebugLogger::setWriter(new CallbackHandler(function($message) {
    error_log($message);
}));
```

#### `setMinLevel(string $level): void`

Sets the minimum log level. Only messages at this level or higher will be output.

```php
use SmartDesk\Utils\Support\LogLevel;

// Available levels (from lowest to highest):
// LogLevel::DEBUG     🐞
// LogLevel::INFO      ℹ️  
// LogLevel::NOTICE    📋
// LogLevel::WARNING   ⚠️
// LogLevel::ERROR     ❌
// LogLevel::CRITICAL  ☠️
// LogLevel::ALERT     🚨
// LogLevel::EMERGENCY ⛔
DebugLogger::setMinLevel(LogLevel::WARNING);
```

#### `makeRotatingWriter(string $dir, string $filename, int $maxSize, int $maxFiles): FileHandler`

Creates a rotating file handler.

##### Parameters:

*   `$dir` - Directory path for log files
*   `$filename` - Base filename (e.g., 'app.log')
*   `$maxSize` - Maximum file size in bytes before rotation
*   `$maxFiles` - Number of backup files to keep

```php
$writer = DebugLogger::makeRotatingWriter(
    WP_CONTENT_DIR . '/uploads/logs',
    'debug.log',
    2_000_000,  // 2MB
    4           // Keep 4 backup files
);
```

### Logging Methods

#### `log(mixed $data, string|array $hooks = '', string $title = '', string $level = LogLevel::DEBUG): void`

Main logging method.

#### Shortcut Methods

```php
DebugLogger::debug($data, $hooks, $title);     // 🐞 DEBUG
DebugLogger::info($data, $hooks, $title);      // ℹ️ INFO  
DebugLogger::notice($data, $hooks, $title);    // 📋 NOTICE
DebugLogger::warning($data, $hooks, $title);   // ⚠️ WARNING
DebugLogger::error($data, $hooks, $title);     // ❌ ERROR
DebugLogger::critical($data, $hooks, $title);  // ☠️ CRITICAL
DebugLogger::alert($data, $hooks, $title);     // 🚨 ALERT
DebugLogger::emergency($data, $hooks, $title); // ⛔ EMERGENCY
```

### Timer Methods

#### `timerStart(string $name): void`

Starts a named timer.

#### `timerLap(string $name, string $title = ''): void`

Records a lap time without stopping the timer.

#### `timerStop(string $name, string $title = '', string $level = LogLevel::DEBUG): void`

Stops the timer and logs the total time.

### Hook Methods

#### `hook(string|array $hooks, string $title = ''): void`

Logs only hook status information without additional data.

## Usage Examples

### Setup Rotating file sink with Request marker

```php
use SmartDesk\Utils\DebugLogger;

add_action('plugins_loaded', function () {
    if (!defined('WP_DEBUG') || WP_DEBUG !== true) return;

    $dir = WP_CONTENT_DIR . '/uploads/smartdesk-logs';
    DebugLogger::setWriter(DebugLogger::makeRotatingWriter($dir, 'dev.log', 2_000_000, 4));

    if (!defined('SMARTDESK_REQ')) {
        define(
            'SMARTDESK_REQ',
            'Timestamp: ' . ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))
            . ' / Request: ' . substr(md5($_SERVER['REQUEST_URI'] ?? ''), 0, 6)
        );
    }
});
```

### Log

```php
use SmartDesk\Utils\DebugLogger;

// 🐞 Debug Flag
$debug = 1 === 1;

$debug && DebugLogger::log(
    [
        '🧪 foo' => 'bar',
        '🧪 list' => ['a', 'b', 'c'],
    ],
    'register',   // preset(s) or single hooks
    'My Block'    // optional title
);
```

#### Log just Hook-State

```php
use SmartDesk\Utils\DebugLogger;

DebugLogger::hook(['admin', 'enqueue'], 'Hooks');
```

##### Available Hook Presets

The logger includes predefined hook sets for common WordPress scenarios:

```php
// Usage examples with presets
DebugLogger::log($data, 'load', 'Plugin Loading');      // WordPress loading hooks
DebugLogger::log($data, 'register', 'Registration');    // Plugin/theme registration
DebugLogger::log($data, 'admin', 'Admin Area');         // Admin-specific hooks
DebugLogger::log($data, 'frontend', 'Frontend');        // Frontend-specific hooks
DebugLogger::log($data, 'enqueue', 'Script Loading');   // Script/style enqueuing
DebugLogger::log($data, 'woo', 'WooCommerce');          // WooCommerce hooks
DebugLogger::log($data, 'rest', 'REST API');            // REST API hooks
DebugLogger::log($data, 'request', 'Request Handling'); // Request processing
```

##### Preset Details:

*   `'load'` - WordPress core loading sequence (muplugins\_loaded, plugins\_loaded, init, etc.)
*   `'register'` - Plugin/theme registration hooks (plugins\_loaded, init, admin\_init, etc.)
*   `'admin'` - Admin area hooks (admin\_init, admin\_menu, current\_screen, etc.)
*   `'frontend'` - Frontend hooks (wp, template\_redirect, wp\_enqueue\_scripts, etc.)
*   `'enqueue'` - Script/style enqueuing hooks
*   `'woo'` - WooCommerce-specific hooks
*   `'rest'` - REST API related hooks
*   `'request'` - Request processing hooks (parse\_request, wp, template\_redirect, etc.)

### Timer

```php
use SmartDesk\Utils\DebugLogger;
use SmartDesk\Utils\Support\LogLevel;

DebugLogger::timerStart('import');
// ... prepare whatever
DebugLogger::timerLap('import', 'after preparing whatever');
// ... work ...
DebugLogger::timerStop('import', 'Import job', LogLevel::NOTICE);
```

##### Output:

```
📋 NOTICE SmartDesk\Importer\Runner::run - 12:34:56,12345
'Timestamp: 1755609635.4039 / Request: abc123'
C:\path\to\Importer\Runner.php:88

    Timer finished
    Timer 'import' finished in 2.3456s
---------|---------|...
```

## Plugin Integration

> **WordPress**
> 
> See the dedicated guide: [WP-IMPLEMENTATION.md](./WP-IMPLEMENTATION.md) — step-by-step
> 
> *   Composer & manual install
> *   Robust plugin bootstrap (autoloader fallback)
> *   Theme & multisite setup
> *   Hook/action tracking
> *   DB query logging
> *   Performance timers
> *   WP-CLI integration
> *   Security, and troubleshooting

```php
/**
 * Plugin Name: SmartDesk Core
 */

use SmartDesk\Utils\DebugLogger;
use SmartDesk\Utils\Support\LogLevel;

// Composer Autoload
require_once __DIR__ . '/vendor/autoload.php';

// Logger setup for plugin
add_action('plugins_loaded', function () {
    if (!defined('WP_DEBUG') || WP_DEBUG !== true) return;

    $logsDir = WP_CONTENT_DIR . '/uploads/smartdesk-logs';

    DebugLogger::setWriter(
        DebugLogger::makeRotatingWriter($logsDir, 'core.log', 2_000_000, 4)
    );
    
    DebugLogger::info([
        'wp' => get_bloginfo('version'),
        'php' => PHP_VERSION,
    ], 'plugins_loaded', 'Core Plugin Loaded');
});
```

## Testing

```
composer install
composer test
composer coverage  # Generate coverage report
composer qa        # Run all quality checks
```

## Notes

Active only if WP\_DEBUG === true.  
Timestamps in UTC.  
Single sink: default error\_log(), or rotating file via setWriter().

## License

GPL-3.0-or-later. See [LICENSE](./LICENSE).

### Plugin-Header (if loaded as plugin)

```php
/*
Plugin Name: SmartDesk DebugLogger
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
*/
```