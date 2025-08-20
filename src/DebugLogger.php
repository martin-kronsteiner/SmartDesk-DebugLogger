<?php

namespace SmartDesk\Utils;

use SmartDesk\Utils\Support\LogLevel;

/**
 * Class DebugLogger
 *
 * Human-friendly debug logger for WordPress dev with multi-line formatting,
 * SmartDesk caller info, and hook status block. Output goes to a single "sink":
 * - Default sink: error_log()
 * - Custom sink: setWriter(callable) or use makeRotatingWriter() factory.
 *
 * Tabs are intentionally used for indentation (project style).
 *
 * @package			SmartDesk\Utils
 * @since			1.0.0
 * @version			1.1.0
 */
final class DebugLogger
{
	/**
	 * Internal meta (SmartDesk loader usage only).
	 * @var array<string,mixed>
	 */
	protected array $class_data = [];

	/** @var null|callable(string):void Custom writer sink */
	private static $sink = null;

	/** @var array<string,float> active timers with start timestamp */
	private static array $timers = [];


	/** Default hook preset key. */
	private const DEFAULT_PRESET = 'register';

	/**
	 * Hook presets (deduplicated where reasonable).
	 * @var array<string, string[]>
	 */
	private const HOOK_PRESETS = [
		'load' => [
			'muplugins_loaded',
			'plugins_loaded',
			'setup_theme',
			'after_setup_theme',
			'init',
			'wp_loaded',
		],
		'register' => [
			'plugins_loaded',
			'init',
			'rest_api_init',
			'admin_menu',
			'admin_init',
			'wp_enqueue_scripts',
			'admin_enqueue_scripts',
			'wp_loaded',
		],
		'admin' => [
			'admin_init',
			'admin_menu',
			'current_screen',
			'admin_enqueue_scripts',
		],
		'frontend' => [
			'wp',
			'template_redirect',
			'wp_enqueue_scripts',
			'wp_head',
			'wp_footer',
		],
		'enqueue' => [
			'admin_enqueue_scripts',
			'wp_enqueue_scripts',
		],
		'woo' => [
			'before_woocommerce_init',
			'woocommerce_loaded',
			'init',
			'after_woocommerce_init',
			'woocommerce_init',
			'woocommerce_register_post_types',
			'woocommerce_register_taxonomy',
			'woocommerce_register_attribute',
		],
		'rest' => [
			'rest_api_init',
			'rest_post_type_init',
			'rest_taxonomy_init',
			'rest_register_routes',
			'rest_insert_attachment',
			'rest_insert_comment',
			'woocommerce_rest_order_object_collection_params',
			'woocommerce_rest_order_object_params',
			'woocommerce_rest_product_object_params',
			'woocommerce_rest_product_attribute_object_params',
			'rest_insert_product',
			'woocommerce_rest_product_attribute_taxonomies_params',
			'woocommerce_rest_product_attributes_params',
			'woocommerce_rest_product_categories_params',
			'woocommerce_rest_product_tags_params',
			'rest_insert_variation',
			'woocommerce_rest_product_variation_object_params',
			'woocommerce_rest_product_variation_attributes_params',
			'woocommerce_rest_product_variation_images_params',
			'woocommerce_rest_product_variation_data_params',
		],
		'request' => [
			'parse_request',
			'send_headers',
			'parse_query',
			'pre_get_posts',
			'wp',
			'template_redirect',
		],
	];

	/**
	 * Construct (SmartDesk loader metadata only).
	 * @param array<string,mixed> $common
	 */
	public function __construct(array $common = [])	{
		$this->class_data['class'] = 'DebugLogger';
		$this->class_data['namespace'] = __NAMESPACE__;
		$this->class_data['thisVersion'] = '1.1.0';
		$this->class_data['last_changed_at'] = '2025-08-18T10:30:00+02:00';
		$this->class_data['publicated'] = '2025-08-18T10:30:00+02:00';
		$this->class_data['enabled'] = true;
		$this->class_data['instantiate'] = false; // helper class, not a loader object
		$this->class_data['register_hook'] = 'woocommerce_loaded';
		$this->class_data['priority'] = 10;
	}

	/** @return array<string,mixed> */
	public function get_class_data(): array {
        // for SmartDesk loader usage
		return $this->class_data;
	}

	/** Gate: active only when WP_DEBUG === true. */
	protected static function isDebugEnabled(): bool {
		return defined('WP_DEBUG') && WP_DEBUG === true;
	}

	/**
	 * Pretty log with optional hooks block.
	 *
	 * @param mixed $data
	 * @param null|string|array $hooks Preset(s) and/or hook names
	 * @param null|string $title Optional title line
	 * @return bool
	 */
	public static function log(mixed $data, null|string|array $hooks = null, ?string $title = null, ?string $level = LogLevel::INFO): bool {
		if (!self::isDebugEnabled()) return false;

		[$ns, $func, $location] = self::callerInfo();
		$hooksList = self::normalizeHooks($hooks);
		$hooksBlock = self::formatHooksBlock($hooksList);
		$body = self::formatData($data);

		$lines = [];
		if ($title !== null && $title !== '') {
			$lines[] = "\t" . $title;
		}
		if ($hooksBlock !== '') {
			$lines[] = $hooksBlock;
		}
		if ($body !== '') {
			$lines[] = $body;
		}

		$message = implode(PHP_EOL, $lines);
		return self::emit($ns, $func, $location, $message, $level);
	}

	/**
	 * Log only the hook status block.
	 *
	 * @param null|string|array $hooks
	 * @param null|string $title
	 * @return bool
	 */
	public static function hook(null|string|array $hooks = null, ?string $title = 'Hooks', ?string $level = LogLevel::INFO): bool {
		if (!self::isDebugEnabled()) return false;

		[$ns, $func, $location] = self::callerInfo();
		$hooksList = self::normalizeHooks($hooks);
		$hooksBlock = self::formatHooksBlock($hooksList);

		$lines = [];
		if ($title !== null && $title !== '') {
			$lines[] = "\t" . $title;
		}
		if ($hooksBlock !== '') {
			$lines[] = $hooksBlock;
		}

		$message = implode(PHP_EOL, $lines);
		return self::emit($ns, $func, $location, $message, $level);
	}

	/* ============================== Writer & helpers =============================== */

	/**
	 * Set a custom sink (single output target).
	 * @param callable(string):void $writer
	 * @return void
	 */
	public static function setWriter(callable $writer): void {
		self::$sink = $writer;
	}

	/**
	 * Create a simple rotating file writer.
	 * @param string $dir
	 * @param string $file
	 * @param int $maxBytes
	 * @param int $maxFiles
	 * @return callable(string):void
	 */
	public static function makeRotatingWriter(string $dir, string $file = 'debug.log', int $maxBytes = 2_000_000, int $maxFiles = 4): callable {
		$dir = rtrim($dir, DIRECTORY_SEPARATOR);
		return static function (string $text) use ($dir, $file, $maxBytes, $maxFiles): void {
			if (!is_dir($dir)) {
				@mkdir($dir, 0775, true);
			}
			$path = $dir . DIRECTORY_SEPARATOR . $file;
			clearstatcache(true, $path);
			$size = is_file($path) ? (int) filesize($path) : 0;
			if ($size > $maxBytes) {
				for ($i = $maxFiles; $i >= 1; $i--) {
					$src = $dir . DIRECTORY_SEPARATOR . $file . ($i === 1 ? '' : '.' . ($i - 1));
					$dst = $dir . DIRECTORY_SEPARATOR . $file . '.' . $i;
					if (is_file($src)) {
						@rename($src, $dst);
					}
				}
			}
			$fp = @fopen($path, 'ab');
			if ($fp) {
				fwrite($fp, $text);
				fclose($fp);
			}
		};
	}

	/** Low-level writer. */
	private static function write(string $text): void {
		if (self::$sink) {
			(self::$sink)($text);
			return;
		}
		error_log($text);
	}

	/**
	 * Compose final multi-line entry and write to sink.
	 * @return bool
	 */
	private static function emit(string $namespace, string $function, string $location, string $body, ?string $level = LogLevel::INFO): bool {
		$date = new \DateTime('now', new \DateTimeZone('UTC'));
		$time = $date->format('H:i:s') . ',' . substr($date->format('u'), 0, 5);

		// prepend level (emoji + label)
		$prefix = ($level !== null && $level !== '') ? $level . ' ' : '';

		$header  = $prefix . $namespace . '\\' . $function . ' - ' . $time . PHP_EOL;
		$header .= defined('SMARTDESK_REQ') ? var_export(constant('SMARTDESK_REQ'), true) . PHP_EOL : '';
		$header .= $location . PHP_EOL . PHP_EOL;

		$eol = '---------|---------|---------|---------|---------|---------|---------|---------|---------|---------|-SmartDesk';

		self::write($header . rtrim($body, PHP_EOL) . PHP_EOL . $eol . PHP_EOL);
		return true;
	}

	/**
	 * Find first SmartDesk\* frame (not this class) and return [ns, func, location].
	 * @return array{0:string,1:string,2:string}
	 */
	private static function callerInfo(): array {
		$trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15);
		$caller = null;
		$namespace = '[global scope]';
		$function = '[unknown function]';
		$location = '[unknown location]';

		foreach ($trace as $frame) {
			if (isset($frame['class'])
				&& str_starts_with($frame['class'], 'SmartDesk\\')
				&& $frame['class'] !== __CLASS__) {
				$caller = $frame;
				break;
			}
		}
		if (!$caller) {
			return [$namespace, $function, $location];
		}

		if (isset($caller['class'])) {
			$fullClass = $caller['class'];
			$parts = explode('\\', $fullClass);
			$function = end($parts) . '::' . ($caller['function'] ?? '[unknown]') . '()';

			array_pop($parts);
			$namespace = implode('\\', $parts) ?: $namespace;

			try {
				$reflector = new \ReflectionMethod($caller['class'], $caller['function']);
				$location = $reflector->getFileName() . ':' . $reflector->getStartLine();
			} catch (\ReflectionException $e) {
				if (isset($caller['file'], $caller['line'])) {
					$location = $caller['file'] . ':' . $caller['line'];
				}
			}
		}
		return [$namespace, $function, $location];
	}

	/**
	 * @param null|string|array $hooks
	 * @return string[]
	 */
	private static function normalizeHooks(null|string|array $hooks): array {
		if ($hooks === null) {
			$hooks = [self::DEFAULT_PRESET];
		}

		$list = [];
		$push = static function (string $hook) use (&$list) {
			$hook = trim($hook);
			if ($hook !== '' && !in_array($hook, $list, true)) {
				$list[] = $hook;
			}
		};

		$items = is_array($hooks) ? $hooks : explode(',', (string) $hooks);
		foreach ($items as $item) {
			$item = trim((string) $item);
			if ($item === '') continue;

			if (isset(self::HOOK_PRESETS[$item])) {
				foreach (self::HOOK_PRESETS[$item] as $h) $push($h);
			} else {
				$push($item);
			}
		}
		return $list;
	}

	/**
	 * @param string[] $hooks
	 */
	private static function formatHooksBlock(array $hooks): string {
		if (empty($hooks)) return '';

		$out = "\thooks:" . PHP_EOL;

		foreach ($hooks as $h) {
			$count = function_exists('did_action') ? (int) did_action($h) : 0;
			if ($count > 0) {
				$out .= "\t\t♻️ {$h} ({$count} times fired)" . PHP_EOL;
			} else {
				$out .= "\t\t⏳ {$h}" . PHP_EOL;
			}
		}
		return rtrim($out, PHP_EOL);
	}

	/** Render any value into a pretty, tab-indented string. */
	private static function formatData(mixed $data): string {
		if (is_array($data)) {
			return self::formatArray($data, 1, true);
		}
		if (is_object($data)) {
			$indent = str_repeat("\t", 1);
			$out = $indent . '(object ' . get_class($data) . ')' . PHP_EOL;
			$obj = str_replace("\n", "\n$indent", print_r($data, true));
			$out .= $indent . $obj;
			return rtrim($out, PHP_EOL);
		}
		$indent = str_repeat("\t", 1);
		return $indent . self::scalarToString($data);
	}

	/**
	 * Pretty-print arrays with braces for nested values.
	 * @param array<mixed> $arr
	 */
	private static function formatArray(array $arr, int $depth = 1, bool $topLevel = false): string {
		$indent = str_repeat("\t", $depth);
		$parentInd = $depth > 0 ? str_repeat("\t", $depth - 1) : '';

		// List without keys
		if (self::arrayIsList($arr)) {
			$out = '';
			foreach ($arr as $item) {
                // strings inline, nested structured blocks with braces
				if (is_string($item)) {
					$out .= $indent . $item . PHP_EOL;
				} elseif (is_array($item)) {
					$out .= $indent . "{\n";
					$out .= self::formatArray($item, $depth + 2, true) . PHP_EOL;
					$out .= $indent . "\t\t}\n";
				} elseif (is_object($item)) {
					$out .= $indent . '(object ' . get_class($item) . ")\n";
					$obj = str_replace("\n", "\n$indent", print_r($item, true));
					$out .= $indent . $obj . "\n";
				} else {
					$out .= $indent . self::scalarToString($item) . PHP_EOL;
				}
			}
			return rtrim($out, PHP_EOL);
		}

		// Mixed array with keys
		$out = '';
		if (!$topLevel) {
			$out .= "{\n";
		}

		foreach ($arr as $k => $v) {
			$keyLabel = ($k === '' ? '[empty]' : (is_int($k) ? '[' . $k . ']' : (string) $k));

			if (is_int($k) && is_string($v)) {
				$out .= $indent . $v . PHP_EOL;
				continue;
			}

			if (is_array($v)) {
				$out .= $indent . $keyLabel . " => {\n";
				$out .= self::formatArray($v, $depth + 2, true) . PHP_EOL;
				$out .= $indent . "\t\t}\n";
			} elseif (is_object($v)) {
				$out .= $indent . $keyLabel . ' => (object ' . get_class($v) . ")\n";
				$obj = str_replace("\n", "\n$indent", print_r($v, true));
				$out .= $indent . $obj . "\n";
			} else {
				$out .= $indent . $keyLabel . ' => ' . self::scalarToString($v) . PHP_EOL;
			}
		}

		if (!$topLevel) {
			$out .= $parentInd . "}";
		}
		return rtrim($out, PHP_EOL);
	}

	/** Scalar to string with explicit markers for edge cases. */
	private static function scalarToString(mixed $v): string {
		if (is_bool($v)) return $v ? 'true' : 'false';
		if ($v === null) return 'null';
		if (is_string($v) && $v === '') return '[empty-string]';
		if ($v === '') return '[empty]';
		return (string) $v;
	}

	/** list detection (PHP < 8.1 fallback) */
	private static function arrayIsList(array $arr): bool {
		if (function_exists('array_is_list')) {
			return array_is_list($arr);
		}
		return array_keys($arr) === range(0, count($arr) - 1);
	}

	/* ============================== Timer ========================================= */

	/**
	 * Start a timer with given ID.
	 * @param string $id
	 * @return void
	 */
	public static function timerStart(string $id): void {
		if (!self::isDebugEnabled()) return;
		self::$timers[$id] = microtime(true);
	}

	/**
	 * Stop a timer, log duration with optional title/level.
	 * @param string $id
	 * @param null|string $title
	 * @param null|string $level
	 * @return void
	 */
	public static function timerStop(string $id, ?string $title = null, ?string $level = \SmartDesk\Utils\Support\LogLevel::INFO): void {
		if (!self::isDebugEnabled()) return;
		if (!isset(self::$timers[$id])) {
			self::log("Timer '$id' not started", null, $title ?? 'Timer error', \SmartDesk\Utils\Support\LogLevel::WARNING);
			return;
		}
		$start = self::$timers[$id];
		unset(self::$timers[$id]);

		$duration = microtime(true) - $start;
		$formatted = number_format($duration, 4) . 's';

		self::log(
			"Timer '$id' finished in " . $formatted,
			null,
			$title ?? 'Timer finished',
			$level
		);
	}

	/* ============================== Level shortcuts =============================== */

	// comments: English; tabs used for indent
	public static function debug(mixed $data, null|string|array $hooks = null, ?string $title = null): bool {
		return self::log($data, $hooks, $title, LogLevel::DEBUG);
	}
	public static function info(mixed $data, null|string|array $hooks = null, ?string $title = null): bool {
		return self::log($data, $hooks, $title, LogLevel::INFO);
	}
	public static function notice(mixed $data, null|string|array $hooks = null, ?string $title = null): bool {
		return self::log($data, $hooks, $title, LogLevel::NOTICE);
	}
	public static function warning(mixed $data, null|string|array $hooks = null, ?string $title = null): bool {
		return self::log($data, $hooks, $title, LogLevel::WARNING);
	}
	public static function alert(mixed $data, null|string|array $hooks = null, ?string $title = null): bool {
		return self::log($data, $hooks, $title, LogLevel::ALERT);
	}
	public static function emergency(mixed $data, null|string|array $hooks = null, ?string $title = null): bool {
		return self::log($data, $hooks, $title, LogLevel::EMERGENCY);
	}
	public static function error(mixed $data, null|string|array $hooks = null, ?string $title = null): bool {
		return self::log($data, $hooks, $title, LogLevel::ERROR);
	}
	public static function critical(mixed $data, null|string|array $hooks = null, ?string $title = null): bool {
		return self::log($data, $hooks, $title, LogLevel::CRITICAL);
	}

}