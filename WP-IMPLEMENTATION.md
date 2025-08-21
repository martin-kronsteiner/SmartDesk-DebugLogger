# WordPress Implementation Guide

## Installation in WordPress

### Via Composer (Recommended)

Add to your `composer.json`:

```
{
  "require": {
    "smartdesk/debug-logger": "^1.0"
  }
}
```

Then install:

```
composer install
# or, if already using Composer
composer require smartdesk/debug-logger:^1.0
```

Make sure WordPress (or your plugin) loads Composer’s autoloader early:

```php
// wp-config.php (Bedrock) or your plugin bootstrap
require_once __DIR__ . '/vendor/autoload.php';
```

> Bedrock-style projects: autoloader is usually loaded in `web/wp-config.php`. Classic WP: load `vendor/autoload.php` in your plugin before using the logger.

### Manual Installation

If you don’t use Composer in your WordPress project:

1.  Create a small **integration plugin**, e.g. `wp-content/plugins/smartdesk-logger-integration/`.
2.  Inside it, include your project’s Composer autoload (if your project manages dependencies) or ship the package’s source within that plugin and register an autoloader.
3.  Require the file **before** you call the logger API.

> Manual installs are possible, but Composer is strongly recommended for updates and autoloading.

### Plugin Integration

Minimal bootstrap in your plugin (runs early and once):

```php
<?php
/** Plugin Name: SmartDesk Logger Integration */

use SmartDesk\Utils\DebugLoggerUtil;
use SmartDesk\Utils\Support\LogLevel;

// 1) Load Composer autoload first (adjust path to your setup)
require_once WP_CONTENT_DIR . '/../vendor/autoload.php';

// 2) Initialize logger early
add_action('plugins_loaded', static function () {
    DebugLoggerUtil::init([
        'dir'       => WP_CONTENT_DIR . '/uploads/smartdesk-logs',
        'file'      => 'app.log',
        'rotate'    => true,
        'max_bytes' => 5_000_000,
        'max_files' => 5,
        'min_level' => LogLevel::WARNING, // set DEBUG in development
    ]);
}, 1);
```

Optional self-test (development only):

```php
if (defined('WP_DEBUG') && WP_DEBUG) {
    add_action('init', static function () { DebugLoggerUtil::selfTest(); }, 99);
}
```

#### Robust bootstrap (recommended)

```php
<?php
/** Plugin Name: SmartDesk Logger Integration */

use SmartDesk\Utils\DebugLoggerUtil;
use SmartDesk\Utils\Support\LogLevel;

// 1) Load Composer autoload first (robust path detection)
$autoloads = [
    WP_CONTENT_DIR . '/../vendor/autoload.php', // Bedrock-ish
    ABSPATH . 'vendor/autoload.php',            // Classic WP in web root
    plugin_dir_path(__FILE__) . 'vendor/autoload.php', // Bundled with this plugin
];
foreach ($autoloads as $file) {
    if (is_file($file)) { require_once $file; break; }
}

// 2) Initialize logger early
add_action('plugins_loaded', static function () {
    DebugLoggerUtil::init([
        'dir'       => defined('WP_CONTENT_DIR') 
                        ? WP_CONTENT_DIR . '/uploads/smartdesk-logs' 
                        : __DIR__ . '/../var/log',
        'file'      => 'app.log',
        'rotate'    => true,
        'max_bytes' => 5_000_000,
        'max_files' => 5,
        'min_level' => LogLevel::WARNING, // set DEBUG in development
    ]);
}, 1);
```

## Configuration Examples

### Theme Integration

Although logging belongs in plugins, you can initialize in a theme’s `functions.php`:

```php
use SmartDesk\Utils\DebugLoggerUtil;
use SmartDesk\Utils\Support\LogLevel;

add_action('after_setup_theme', static function () {
    if (!class_exists(DebugLoggerUtil::class)) return;
    DebugLoggerUtil::init([
        'dir'       => WP_CONTENT_DIR . '/uploads/smartdesk-logs',
        'file'      => 'theme.log',
        'min_level' => LogLevel::NOTICE,
    ]);
});
```

### Plugin Development

Recommended: initialize in your plugin bootstrap and log with context:

```php
use SmartDesk\Utils\DebugLogger;

add_action('init', static function () {
    DebugLogger::info([
        'plugin' => 'my-plugin',
        'event'  => 'init',
    ], [], 'Plugin init');
});

// Log a warning with extra context
DebugLogger::warning([
    'plugin' => 'my-plugin',
    'slow'   => true,
    'ms'     => 850,
], [], 'Slow operation');
```

### Multisite Setup

*   Use a **network-wide log directory** or one directory per site.
*   Example (per site): `WP_CONTENT_DIR . '/uploads/smartdesk-logs/site-' . get_current_blog_id()`
*   Run initialization on every site (e.g., in a network-activated plugin).
*   Be mindful of disk space and rotation settings.

## WordPress-Specific Features

### Hook Tracking

Record key lifecycle hooks once (very low overhead):

```php
foreach (['plugins_loaded','init','wp_loaded','template_redirect','shutdown'] as $hook) {
    add_action($hook, static function () use ($hook) {
        SmartDesk\Utils\DebugLogger::debug([
            'hook' => $hook,
        ], [$hook], 'Hook reached');
    }, PHP_INT_MAX);
}
```

> The second argument is the **hooks** list. Keep it to short strings; pass **data** (arrays/objects) as the **first** argument.

### Action/Filter Logging

Wrap actions/filters to measure execution time:

```php
function sd_log_action_timing(string $hook, int $priority = 10): void {
    add_action($hook, static function () use ($hook) {
        $t0 = microtime(true);
        // let other callbacks run on next priority tick
        add_action($hook, static function () use ($hook, $t0) {
            SmartDesk\Utils\DebugLogger::debug([
                'hook'       => $hook,
                'elapsed_ms' => (int) round((microtime(true) - $t0) * 1000),
            ], [$hook], 'Action timing');
        }, PHP_INT_MAX);
    }, $priority);
}
```

### Database Query Logging

Enable query collection and log slow queries on `shutdown`:

```php
// wp-config.php
define('SAVEQUERIES', true);
```

```php
add_action('shutdown', static function () {
    global $wpdb;
    if (!isset($wpdb->queries) || !is_array($wpdb->queries)) return;

    $thresholdMs = 50; // log queries slower than 50ms
    foreach ($wpdb->queries as $q) {
        [$sql, $time, $callers] = $q; // $time in seconds
        $ms = (int) round($time * 1000);
        if ($ms < $thresholdMs) continue;
        SmartDesk\Utils\DebugLogger::warning([
            'sql'        => $sql,
            'elapsed_ms' => $ms,
            'callers'    => $callers,
        ], ['db', 'slow-query'], 'Slow DB query');
    }
});
```

### Performance Monitoring

Use simple timers around critical paths:

```php
$t0 = microtime(true);
// ... do work ...
SmartDesk\Utils\DebugLogger::debug([
    'segment'    => 'my-critical-path',
    'elapsed_ms' => (int) round((microtime(true) - $t0) * 1000),
], ['perf'], 'Perf checkpoint');
```

Or reuse `DebugLoggerUtil::selfTest()` which already logs **start**, **mid-checkpoints**, and **total time**.

## Common Use Cases

### Plugin Development Debugging

Log plugin lifecycle, settings saves, and error paths:

```php
add_action('admin_post_my_plugin_save', static function () {
    SmartDesk\Utils\DebugLogger::info([
        'action' => 'save',
        'user'   => get_current_user_id(),
    ], ['admin_post'], 'Settings saved');
});
```

### Theme Development

Trace template resolution or enqueue operations:

```php
add_action('wp_enqueue_scripts', static function () {
    SmartDesk\Utils\DebugLogger::debug([
        'styles'  => wp_styles()->queue,
        'scripts' => wp_scripts()->queue,
    ], ['assets'], 'Assets enqueued');
});
```

### API Endpoint Debugging

Inside a REST controller:

```php
SmartDesk\Utils\DebugLogger::info([
    'route'   => '/my-namespace/v1/things',
    'method'  => $request->get_method(),
    'params'  => $request->get_params(),
    'user'    => get_current_user_id(),
], ['rest'], 'REST request');
```

### AJAX Request Logging

```php
add_action('wp_ajax_my_action', static function () {
    SmartDesk\Utils\DebugLogger::debug([
        'action' => 'my_action',
        'nonce'  => isset($_REQUEST['_wpnonce']),
        'user'   => get_current_user_id(),
    ], ['ajax'], 'AJAX handler');
});
```

## Integration with WordPress Tools

### WP-CLI Integration

```php
if (defined('WP_CLI') && WP_CLI) {
    \WP_CLI::add_command('smartdesk:selftest', static function () {
        SmartDesk\Utils\DebugLoggerUtil::selfTest(['source' => 'wp-cli']);
        \WP_CLI::success('SmartDesk DebugLogger self-test finished.');
    });
}
```

### Debug Bar Plugin

*   Use the logger to persist details to files while Debug Bar shows runtime metrics.
*   Cross-reference timestamps between Debug Bar panels and log lines.

### Query Monitor Plugin

*   With `SAVEQUERIES` enabled, Query Monitor shows queries; log **slow** ones for historical analysis.
*   You can also log **hooks** that Query Monitor highlights, to correlate events.

## Security Considerations

### Production Environment

*   Set `min_level` to `WARNING` or higher.
*   Enable rotation and limit retention.
*   Keep logs concise; avoid large payloads.

### Log File Protection

Store logs in `wp-content/uploads/smartdesk-logs` (non-PHP) and deny direct HTTP access.

**Apache (**`**.htaccess**`**)**

**Nginx**

Alternatively, place logs **outside the web root** if your hosting allows it.

### Sensitive Data Handling

*   Never log secrets, passwords, tokens, or full personal data.
*   Redact well-known keys before logging:

```php
function sd_redact(array $data, array $keys = ['password','token','secret']): array {
    foreach ($keys as $k) {
        if (isset($data[$k])) $data[$k] = '***';
    }
    return $data;
}
```

## Troubleshooting

### Common WordPress Issues

*   **No logs written**: ensure directory exists & is writable; make sure `DebugLoggerUtil::init()` runs; check `min_level`.
*   **Autoloader not found**: `require vendor/autoload.php` before using the logger.
*   **Duplicate logs**: ensure legacy/old logger is not active alongside the new one.
*   **Parameter order**: use `DebugLogger::<level>($data, $hooks, $title)`. Do **not** put arrays/objects into `$hooks`.

### Performance Impact

*   Avoid logging every callback via `all` hook; target specific hooks.
*   Log at `DEBUG` only in development; use `WARNING`\+ in production.
*   Keep payloads small; prefer IDs over full objects.

### Memory Usage

*   Don’t attach huge arrays/objects; summarize or paginate payloads across multiple lines.
*   Use rotation and prune old files.

---

**Ready to go.** Use `DebugLoggerUtil::init()` early, log with `DebugLogger::<level>($data, $hooks, $title)`, and keep production lean with higher `min_level`. Happy debugging!

```
location ~* \.log$ { deny all; }
```

```
<FilesMatch "\.log$">
  Require all denied
</FilesMatch>
```