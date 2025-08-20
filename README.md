# SmartDesk DebugLogger

![CI](https://github.com/martin-kronsteiner/SmartDesk-DebugLogger/actions/workflows/ci.yml/badge.svg)
![Release](https://github.com/martin-kronsteiner/SmartDesk-DebugLogger/actions/workflows/release.yml/badge.svg)
![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)

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

## Installation

```
composer require smartdesk/debug-logger
```

## How to use

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

### Log Levels / Shortcuts

Available:  
🐞 DEBUG, ℹ️ INFO, 📋 NOTICE, ⚠️ WARNING, 🚨 ALERT, ⛔ EMERGENCY, ❌ ERROR, ☠️ CRITICAL

```php
use SmartDesk\Utils\DebugLogger;
use SmartDesk\Utils\Support\LogLevel;

DebugLogger::log(['x' => 1], 'register', 'My Block', LogLevel::ERROR);

DebugLogger::warning('something odd happened', 'frontend');
DebugLogger::debug(['vars' => $_GET], ['request','enqueue'], 'Request vars');
```

### Log just Hook-State

```php
use SmartDesk\Utils\DebugLogger;

DebugLogger::hook(['admin', 'enqueue'], 'Hooks');
```

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

Output

```
📋 NOTICE SmartDesk\Importer\Runner::run - 12:34:56,12345
'Timestamp: 1755609635.4039 / Request: abc123'
C:\path\to\Importer\Runner.php:88

    Timer finished
    Timer 'import' finished in 2.3456s
---------|---------|...
```

## Plugin integration

```php
<?php
/**
 * Plugin Name: SmartDesk Core
 */

use SmartDesk\Utils\DebugLogger;
use SmartDesk\Utils\Support\LogLevel;

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
```

## Test

```
composer install
composer test
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