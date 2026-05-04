<?php

// SPDX-License-Identifier: GPL-3.0-or-later
/**
 * SmartDesk DebugLogger
 * Copyright (C) 2025 SmartDesk Development Team - Ing. M. Kronsteiner
 *
 * This file is part of SmartDesk DebugLogger.
 *
 * SmartDesk DebugLogger is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * SmartDesk DebugLogger is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with SmartDesk DebugLogger.  If not, see <https://www.gnu.org/licenses/>.
 *
 * @license		GPL-3.0-or-later
 * @copyright	2025 SmartDesk Development Team - Ing. M. Kronsteiner
 * @link		https://www.gnu.org/licenses/gpl-3.0.html
 */
declare(strict_types=1);

namespace SmartDesk\Utils;

use SmartDesk\Utils\Support\LogLevel;

/**
 * Class DebugLogger
 *
 * Human-friendly debug logger for WordPress dev with multi-line formatting,
 * SmartDesk caller info, and hook status block. Output goes to a single "sink":
 * 		- Default sink:	error_log()
 * 		- Custom sink:	setWriter(callable) or use makeRotatingWriter() factory.
 *
 * Tabs are intentionally used for indentation (project style).
 */
final class DebugLogger
{
	/** @var null|callable(string):void Custom writer sink */
	private static $sink = null;

	/** @var array<string,float> active timers with start timestamp */
	private static array $timers = [];

	/** @var null|string minimal required level; null = no filter */
	private static ?string $minLevel = null;

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

	/** Gate: active only when WP_DEBUG === true. */
	protected static function isDebugEnabled(): bool
    {
		return defined('WP_DEBUG') && WP_DEBUG === true;
    }

	/**
	 * Pretty log with optional hooks block.
	 *
	 * Logs debug data with human-friendly formatting, including optional hook status information
	 * and caller details. The output includes formatted data, hook execution status, and metadata
	 * about the calling function. Only active when WP_DEBUG is enabled.
	 *
	 * The data to log - can be any type (array, object, scalar, etc.)
	 * @param	mixed							$data
	 *
	 * Optional hook preset name(s) or specific hook names to check status for.
	 * Can be a preset key ('load', 'register', 'admin', etc.), comma-separated string,
	 * or array of hook names. Defaults to 'register' preset if null.
	 * @param	array<int,string>|string|null	$hooks
	 *
	 * Optional title line to display at the top of the log entry
	 * @param	null|string						$title
	 *
	 * Log level using LogLevel constants (DEBUG, INFO, WARNING, etc.). Defaults to INFO.
	 * @param	null|string						$level
	 *
	 * @return	bool				True if the log entry was written successfully, false if WP_DEBUG is disabled
	 */
	public static function log(
		mixed $data,
		null|string|array $hooks = [],
		?string $title = null,
		?string $level = LogLevel::INFO
    ): bool {
		if (!self::isDebugEnabled()) {
            return false;
        }

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
	 * Outputs a debug log entry containing only WordPress hook execution status information,
	 * without any additional data. Shows which hooks have been fired and how many times,
	 * versus hooks that are still pending. Only active when WP_DEBUG is enabled.
	 *
	 * Optional hook preset name(s) or specific hook names to check status for.
	 * Can be a preset key ('load', 'register', 'admin', etc.), comma-separated string,
	 * or array of hook names. Defaults to 'register' preset if null.
	 * @param	array<int,string>|string|null	$hooks
	 *
	 * Optional title line to display at the top of the log entry. Defaults to 'Hooks'.
	 * @param	null|string						$title
	 *
	 * Log level using LogLevel constants (DEBUG, INFO, WARNING, etc.). Defaults to INFO.
	 * @param	null|string						$level
	 *
	 * True if the log entry was written successfully, false if WP_DEBUG is disabled
	 * @return	bool
	 */
	public static function hook(
		null|string|array $hooks,
		?string $title = 'Hooks',
		?string $level = LogLevel::INFO
    ): bool {
		if (!self::isDebugEnabled()) {
            return false;
        }

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

	/**
	 * Create a rotating file writer for log output.
	 *
	 * Creates a callable that writes log messages to files with automatic rotation when size limits
	 * are exceeded. When the main log file reaches the maximum size, it rotates existing files by
	 * renaming them with numeric suffixes (.1, .2, etc.) and starts a fresh main log file.
	 * The directory is created automatically if it doesn't exist.
	 *
	 * The directory path where log files will be stored. Will be created if it doesn't exist.
	 * @param	string	$dir
	 *
	 * The base filename for the log file. Defaults to 'debug.log'.
	 * @param	string	$file
	 *
	 * Maximum file size in bytes before rotation occurs. Defaults to 2,000,000 bytes (2MB).
	 * @param	int		$maxBytes
	 *
	 * Maximum number of rotated files to keep. Older files beyond this limit are deleted.
	 * Defaults to 4 files.
	 * @param	int		$maxFiles
	 *
	 * A callable that accepts a log message string and writes it to the rotating log file.
	 * The callable handles all rotation logic internally and returns void.
	 * @return	callable(string):void
	 */
	public static function makeRotatingWriter(
		string $dir,
		string $file = 'debug.log',
		int $maxBytes = 2_000_000,
		int $maxFiles = 4
    ): callable {

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

	#region Setters

	/**
	 * Set a custom writer function as the output sink for all log entries.
	 *
	 * Replaces the default error_log() output with a custom callable that receives
	 * the formatted log message. This allows redirecting debug output to files,
	 * databases, or any other destination. Only one writer can be active at a time.
	 *
	 * @param	callable(string):void	$writer	A callable that accepts a single string parameter
	 * 											containing the complete formatted log message and
	 * 											handles writing it to the desired destination.
	 * 											The callable should not return any value.
	 *
	 * @return	void
	 */
	public static function setWriter(callable $writer): void
    {

		self::$sink = $writer;
    }

	/**
	 * Set the minimum log level threshold for filtering log entries.
	 *
	 * Configures a minimum log level that acts as a filter for all subsequent log entries.
	 * Only log messages at or above the specified level will be processed and written to
	 * the output sink. Log levels follow a hierarchy where higher severity levels have
	 * higher numeric values. When a minimum level is set, messages below that threshold
	 * are silently discarded. This allows for runtime control of log verbosity without
	 * modifying individual log calls throughout the codebase.
	 *
	 * @param	string	$level	The minimum log level to accept. Must be a valid LogLevel constant
	 * 							such as LogLevel::DEBUG, LogLevel::INFO, LogLevel::WARNING, etc.
	 * 							The level hierarchy from lowest to highest is: DEBUG, INFO, NOTICE,
	 * 							WARNING, ERROR, CRITICAL, ALERT, EMERGENCY. Setting a higher level
	 * 							will filter out lower-priority messages.
	 *
	 * @return	void			No return value. The minimum level threshold is stored internally
	 * 							and applied to all subsequent log operations until changed or reset.
	 */
	public static function setMinLevel(?string $level): void
    {

		self::$minLevel = $level;
    }

	#endregion


	#region Private Methods and Helpers

	/**
	 * Check if a log level meets the minimum threshold requirement for output.
	 *
	 * Determines whether a given log level should be allowed to output based on the
	 * configured minimum level threshold. Uses the LogLevel::ORDER array to compare
	 * numeric priority values where higher numbers indicate higher severity levels.
	 * If no minimum level is set (null), all levels are allowed through. This enables
	 * runtime filtering of log messages without modifying individual log calls.
	 *
	 * @param	string	$level	The log level to check against the minimum threshold.
	 * 							Must be a valid LogLevel constant such as LogLevel::DEBUG,
	 * 							LogLevel::INFO, LogLevel::WARNING, etc. The level must
	 * 							exist in the LogLevel::ORDER array for proper comparison.
	 *
	 * @return	bool			True if the level meets or exceeds the minimum threshold
	 * 							and should be allowed to output, false if it should be
	 * 							filtered out. Always returns true when no minimum level
	 * 							is configured (self::$minLevel is null).
	 */
	private static function isAllowed(?string $level): bool
    {

		if (self::$minLevel === null) {
            return true;
        }

		$level = $level ?? \SmartDesk\Utils\Support\LogLevel::INFO;
        $order = \SmartDesk\Utils\Support\LogLevel::ORDER;
        if (!isset($order[$level])) {
            return true;
        }
		if (!isset($order[self::$minLevel])) {
            return true;
        }

		return $order[$level] >= $order[self::$minLevel];
    }

	/**
	 * Low-level writer that outputs log text to the configured sink.
	 *
	 * This is the internal method responsible for actually writing log messages to their
	 * final destination. It checks if a custom writer sink has been configured via setWriter()
	 * and uses that if available, otherwise falls back to PHP's built-in error_log() function.
	 * This method is called by emit() after all formatting has been completed.
	 *
	 * @param	string	$text	The complete formatted log message string to be written.
	 * 							This should include all formatting, timestamps, headers,
	 * 							and content as it will be output exactly as provided.
	 *
	 * @return	void			No return value. The method either writes to the custom
	 * 							sink or calls error_log() and returns nothing.
	 */
	private static function write(string $text): void
    {

		if (self::$sink) {
            (self::$sink)($text);
            return;
        }
		error_log($text);
    }

	/**
	 * Compose final multi-line entry and write to sink.
	 *
	 * Creates a complete formatted log entry by combining caller information, timestamp,
	 * log level, and message body into a standardized multi-line format. The entry includes
	 * a header with namespace/function context, UTC timestamp with microseconds, optional
	 * SmartDesk request identifier, file location, the main message body, and a distinctive
	 * footer separator. The formatted entry is then written to the configured output sink.
	 * If the log level doesn't meet the minimum threshold, the entry is discarded and false is returned.
	 *
	 * The namespace portion of the calling class (e.g., 'SmartDesk\Utils')
	 * @param	string		$namespace
	 *
	 * The function name with class context (e.g., 'MyClass::myMethod()')
	 * @param	string		$function
	 *
	 * File path and line number where the log was called (e.g., '/path/file.php:123')
	 * @param	string		$location
	 *
	 * The main formatted message content to be logged, may contain multiple lines
	 * @param	string		$body
	 *
	 * Optional log level with emoji prefix from LogLevel constants. Defaults to LogLevel::INFO.
	 * If null or empty, no level prefix is added to the output.
	 * @param	null|string	$level
	 *
	 * True if the log entry was processed and written to the sink, false if the log level
	 * was filtered out by the minimum level threshold
	 * @return	bool
	 */
	private static function emit(
		string $namespace,
		string $function,
		string $location,
		string $body,
		?string $level = LogLevel::INFO
    ): bool {

		if (!self::isAllowed($level)) {
            return false;
        }

		$date = new \DateTime('now', new \DateTimeZone('UTC'));
        $time = $date->format('H:i:s') . ',' . substr($date->format('u'), 0, 5);
		$prefix = ($level !== null && $level !== '') ? $level . ' ' : '';
        $header  = $prefix . $namespace . '\\' . $function . ' - ' . $time . PHP_EOL;
        $header .= defined('SMARTDESK_REQ') ? var_export(constant('SMARTDESK_REQ'), true) . PHP_EOL : '';
        $header .= $location . PHP_EOL . PHP_EOL;
        $eol = '---------|---------|---------|---------|---------';
		$eol .= '|---------|---------|---------|---------|---------|-SmartDesk';
        self::write($header . rtrim($body, PHP_EOL) . PHP_EOL . $eol . PHP_EOL);
        return true;
    }

	/**
	 * Extract caller information from the debug backtrace for SmartDesk classes.
	 *
	 * Analyzes the debug backtrace to find the first calling frame that belongs to a SmartDesk
	 * namespace class (excluding this DebugLogger class itself). Extracts the namespace,
	 * function name with class context, and file location information. Uses reflection to
	 * get accurate file and line information when possible, falling back to backtrace data.
	 * Returns default values if no suitable SmartDesk caller is found in the trace.
	 *
	 * A three-element array containing:
	 * 		[0]	The namespace portion of the calling class (e.g., 'SmartDesk\Utils'),
	 * 			defaults to '[global scope]' if no SmartDesk caller found
	 * 		[1]	The function name with class context (e.g., 'MyClass::myMethod()'),
	 * 			defaults to '[unknown function]' if no SmartDesk caller found
	 * 		[2]	The file path and line number (e.g., '/path/file.php:123'),
	 * 			defaults to '[unknown location]' if no SmartDesk caller found
	 * @return array{0:string,1:string,2:string}
	 *
	 */
	private static function callerInfo(): array
    {

		$trace		= debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15);
        $caller		= null;
        $namespace	= '[global scope]';
        $function	= '[unknown function]';
        $location	= '[unknown location]';
        foreach ($trace as $frame) {
			$frameClass = $frame['class'] ?? null;
			if (!is_string($frameClass) || $frameClass === __CLASS__) {
				continue;
			}

			foreach (['SmartDesk\\', 'SmartDeskCore\\'] as $prefix) {
				if (str_starts_with($frameClass, $prefix)) {
					$caller = $frame;
					break 2;
				}
			}
		}
		if (!$caller) {
            return [$namespace, $function, $location];
        }

		if (isset($caller['class'])) {
            $fullClass = $caller['class'];
            $parts = explode('\\', $fullClass);
            $function = end($parts) . '::' . $caller['function'] . '()';
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
	 * Normalize and expand hook names into a deduplicated array of individual hook names.
	 *
	 * Processes various input formats for WordPress hook names and converts them into a standardized
	 * array of individual hook names. Supports preset expansion where preset keys like 'load', 'register',
	 * etc. are replaced with their corresponding arrays of hook names from HOOK_PRESETS. Handles
	 * comma-separated strings, arrays, and null values. Automatically deduplicates hook names and
	 * trims whitespace from all entries.
	 *
	 * The hook specification to normalize. Can be:
	 * 		- null:		Uses the default preset (DEFAULT_PRESET constant)
	 * 		- string:	Either a preset key ('load', 'register', etc.) or comma-separated hook names
	 * 		- array:	Mixed array of preset keys and/or individual hook names
	 * 					Preset keys are expanded to their full hook arrays from HOOK_PRESETS.
	 * 					Individual hook names are added directly after trimming whitespace.
	 * @param	array<int,string>|string|null	$hooks
	 *
	 * @return	string[]						Array of unique WordPress hook names with duplicates removed.
	 * 											Empty strings and whitespace-only entries are filtered out.
	 * 											Order is preserved based on input order and preset definitions.
	 */
	private static function normalizeHooks(null|string|array $hooks): array
    {

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
            if ($item === '') {
                continue;
            }

            if (isset(self::HOOK_PRESETS[$item])) {
                foreach (self::HOOK_PRESETS[$item] as $h) {
                    $push($h);
                }
            } else {
                $push($item);
            }
        }
		return $list;
    }

	/**
	 * Format a hooks status block for debug output.
	 *
	 * Creates a formatted multi-line string showing the execution status of WordPress hooks.
	 * Each hook is displayed with an emoji indicator: ♻️ for hooks that have been fired
	 * (with execution count), or ⏳ for hooks that are still pending. The output is
	 * tab-indented for consistent formatting within debug log entries.
	 *
	 * @param	string[]	$hooks	Array of WordPress hook names to check status for.
	 * 								Each element should be a valid WordPress hook name string.
	 * 								Empty array will result in an empty string return.
	 *
	 * @return	string				Formatted hooks status block with tab indentation and emoji indicators.
	 * 								Returns empty string if no hooks provided. Each hook appears on its own line
	 * 								with appropriate status indicator and execution count if applicable.
	 * 								Trailing newlines are removed from the final output.
	 */
	private static function formatHooksBlock(array $hooks): string
    {

		if (empty($hooks)) {
            return '';
        }

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

	/**
	 * Render any value into a pretty, tab-indented string for debug output.
	 *
	 * Converts any PHP data type into a human-readable, formatted string with consistent
	 * tab indentation. Arrays are processed through formatArray() for structured output,
	 * objects are displayed with their class name and print_r() representation, and
	 * scalar values are converted using scalarToString() with proper indentation.
	 * All output is formatted with single-level tab indentation for integration
	 * into debug log entries.
	 *
	 * @param	mixed	$data	The data to format - can be any PHP type including arrays,
	 * 							objects, scalars, null, booleans, etc. Each type is
	 * 							handled with appropriate formatting rules for readability.
	 *
	 * @return	string			Formatted string representation with tab indentation.
	 * 							Arrays get structured formatting, objects show class name
	 * 							and properties, scalars are converted to readable strings.
	 * 							Trailing newlines are removed from the final output.
	 */
	private static function formatData(mixed $data): string
    {
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
	 * Format arrays into human-readable, indented strings with proper nesting and braces.
	 *
	 * Recursively formats arrays into structured, tab-indented output with appropriate braces
	 * for nested structures. Handles both sequential arrays (lists) and associative arrays
	 * differently for optimal readability. Sequential arrays display items inline without keys,
	 * while associative arrays show key-value pairs with proper labeling. Nested arrays and
	 * objects are enclosed in braces with increased indentation levels.
	 *
	 * @param	array<mixed>	$arr		The array to format. Can contain any mix of data types
	 * 										including nested arrays, objects, and scalar values.
	 * 										Empty arrays are handled gracefully.
	 * @param	int				$depth		The current indentation depth level, where each level
	 * 										adds one tab character. Defaults to 1 for single-level
	 * 										indentation. Automatically incremented for nested structures.
	 * @param	bool			$topLevel	Whether this is a top-level array call. When true,
	 * 										suppresses the opening brace for the current array level.
	 * 										Defaults to false. Used internally for recursive calls.
	 *
	 * @return	string						Formatted string representation of the array with proper
	 * 										indentation, braces, and structure. Trailing newlines
	 * 										are removed. Empty arrays return empty strings.
	 */
	private static function formatArray(array $arr, int $depth = 1, bool $topLevel = false): string
    {
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

	/**
	 * Convert scalar values to human-readable strings with explicit markers for edge cases.
	 *
	 * Transforms scalar PHP values into string representations that clearly identify
	 * edge cases and special values that might otherwise be ambiguous in debug output.
	 * Boolean values are converted to literal 'true'/'false' strings, null becomes
	 * 'null', and empty values get descriptive markers to distinguish between different
	 * types of emptiness. All other values are cast to strings using PHP's default
	 * string conversion rules.
	 *
	 * @param	mixed	$v	The scalar value to convert to string. Can be any PHP type
	 * 						including boolean, null, string, integer, float, or other
	 * 						scalar types. Non-scalar types will be cast to string
	 * 						using PHP's default conversion behavior.
	 *
	 * @return	string		Human-readable string representation of the input value.
	 * 						Special cases return: 'true'/'false' for booleans, 'null'
	 * 						for null values, '[empty-string]' for empty strings, '[empty]'
	 * 						for other empty values, or the string-cast value otherwise.
	 */
	private static function scalarToString(mixed $v): string
    {
		if (is_bool($v)) {
            return $v ? 'true' : 'false';
        }
		if ($v === null) {
            return 'null';
        }
		if (is_string($v) && $v === '') {
            return '[empty-string]';
        }
		if ($v === '') {
            return '[empty]';
        }
		return (string) $v;
    }

	/**
	 * Determine if an array is a sequential list with consecutive integer keys starting from 0.
	 *
	 * Checks whether the given array is a sequential list (has consecutive integer keys
	 * starting from 0) versus an associative array with string keys or non-sequential
	 * numeric keys. Uses PHP 8.1's native array_is_list() function when available,
	 * otherwise falls back to manual key comparison for compatibility with older PHP versions.
	 *
	 * @param	array<mixed>	$arr	The array to check for list structure. Can be empty or contain
	 * 									any mix of values. Only the key structure is examined, not
	 * 									the values themselves.
	 *
	 * @return	bool			True if the array is a sequential list with keys [0, 1, 2, ...],
	 * 							false if it has string keys, non-sequential numeric keys, or
	 * 							gaps in the sequence. Empty arrays return true.
	 */
	private static function arrayIsList(array $arr): bool
    {
		if (function_exists('array_is_list')) {
            return array_is_list($arr);
        }
		return array_keys($arr) === range(0, count($arr) - 1);
    }

	#endregion

	#region Timer

	/**
	 * Start a named timer for performance measurement and debugging.
	 *
	 * Initializes a high-precision timer with the specified identifier that can be used
	 * to measure execution time between start and stop points. The timer uses microtime(true)
	 * for microsecond precision and stores the start timestamp in an internal registry.
	 * Multiple timers can run simultaneously with different IDs. Only active when WP_DEBUG
	 * is enabled, otherwise the call is silently ignored.
	 *
	 * @param	string	$id		Unique identifier for the timer. Used to reference this specific
	 * 							timer when calling timerStop(). If a timer with the same ID
	 * 							already exists, it will be overwritten with a new start time.
	 * 							Should be a descriptive name like 'database_query' or 'api_call'.
	 *
	 * @return	void			No return value. The timer is stored internally and can be
	 * 							stopped later using timerStop() with the same ID.
	 */
	public static function timerStart(string $id): void
    {
		if (!self::isDebugEnabled()) {
            return;
        }
		self::$timers[$id] = microtime(true);
    }

	/**
	 * Record a lap time for a named timer and log the elapsed duration since start or last lap.
	 *
	 * Records an intermediate timing measurement for a previously started timer without stopping it.
	 * If the timer doesn't exist, it will be automatically created with the current timestamp as
	 * the start time. The elapsed time since the timer was started (or last reset) is calculated
	 * and logged with microsecond precision. The timer continues running after the lap is recorded.
	 * Only active when WP_DEBUG is enabled, otherwise the call is silently ignored.
	 *
	 * @param	string			$id		The unique identifier of the timer to record a lap for.
	 * 									If no timer with this ID exists, a new timer will be
	 * 									automatically created starting from the current timestamp.
	 * @param	string			$title	Optional title for the log entry. Defaults to 'lap'.
	 * 									Used as the main heading in the log output to identify
	 * 									this particular lap measurement.
	 * @param	null|string		$level	Optional log level using LogLevel constants (DEBUG, INFO, WARNING, etc.).
	 * 									Defaults to LogLevel::DEBUG. Determines the priority level
	 * 									of the lap time log entry for filtering purposes.
	 *
	 * @return	void					No return value. The lap duration is logged to the configured
	 * 									output sink and the timer continues running for future laps or stop.
	 */
	public static function timerLap(string $id, string $title = 'lap', ?string $level = LogLevel::DEBUG): void
    {
		if (!self::isDebugEnabled()) {
            return;
        }

		if (!isset(self::$timers[$id])) {
            self::$timers[$id] = microtime(true);
        }

		$elapsed = microtime(true) - self::$timers[$id];
        [$ns, $func, $location] = self::callerInfo();
        $body = "\t" . ($title !== '' ? $title : 'lap') . PHP_EOL
			. "\tTimer '{$id}' finished in " . \number_format($elapsed, 4) . "s";
        self::emit($ns, $func, $location, $body, $level ?? LogLevel::DEBUG);
    }


	/**
	 * Stop a named timer and log the measured execution duration.
	 *
	 * Stops a previously started timer identified by the given ID, calculates the elapsed time
	 * since timerStart() was called with the same ID, and logs the duration with microsecond
	 * precision. The timer is automatically removed from the internal registry after stopping.
	 * If the timer was never started or already stopped, logs a warning message instead.
	 * Only active when WP_DEBUG is enabled, otherwise the call is silently ignored.
	 *
	 * @param	string			$id		The unique identifier of the timer to stop. Must match
	 * 									the ID used when calling timerStart(). If no timer with
	 * 									this ID exists, a warning will be logged instead.
	 * @param	null|string		$title	Optional title for the log entry. If null, defaults to
	 * 									'Timer finished' for successful stops or 'Timer error'
	 * 									for missing timers. Used as the main heading in the log output.
	 * @param	null|string		$level	Optional log level using LogLevel constants (DEBUG, INFO, WARNING, etc.).
	 * 									Defaults to LogLevel::INFO for successful timer stops.
	 * 									Warning messages for missing timers always use WARNING level.
	 *
	 * @return	void					No return value. The timer duration is logged to the configured
	 * 									output sink and the timer is removed from internal storage.
	 */
	public static function timerStop(
		string $id,
		?string $title = null,
		?string $level = \SmartDesk\Utils\Support\LogLevel::INFO
    ): void {

		if (!self::isDebugEnabled()) {
            return;
        }
		if (!isset(self::$timers[$id])) {
            self::log(
				"Timer '$id' not started",
				null,
				$title ?? 'Timer error',
				\SmartDesk\Utils\Support\LogLevel::WARNING
			);
            return;
        }
		$start = self::$timers[$id];
        unset(self::$timers[$id]);
        $duration = microtime(true) - $start;
        $formatted = number_format($duration, 4) . 's';
        self::log("Timer '$id' finished in " . $formatted, null, $title ?? 'Timer finished', $level);
    }

	#endregion

	#region Level shortcuts

	/**
	 * Log debug data with DEBUG level priority.
	 *
	 * Convenience method that logs debug data with human-friendly formatting and optional
	 * hook status information at DEBUG level. Equivalent to calling log() with LogLevel::DEBUG.
	 * Only active when WP_DEBUG is enabled, otherwise the call is silently ignored.
	 *
	 * The data to log - can be any type (array, object, scalar, etc.)
	 * @param	mixed				$data
	 *
	 * Optional hook preset name(s) or specific hook names to check status for.
	 * Can be a preset key ('load', 'register', 'admin', etc.), comma-separated string,
	 * or array of hook names. Defaults to 'register' preset if null.
	 * @param	array<int,string>|string|null	$hooks
	 *
	 * Optional title line to display at the top of the log entry
	 * @param	null|string						$title
	 *
	 * True if the log entry was written successfully, false if WP_DEBUG is disabled
	 * @return	bool
	 */
	public static function debug(mixed $data, null|string|array $hooks = null, ?string $title = null): bool
    {
		return self::log($data, $hooks, $title, LogLevel::DEBUG);
    }

	/**
	 * Log informational data with INFO level priority.
	 *
	 * Convenience method that logs informational data with human-friendly formatting and optional
	 * hook status information at INFO level. Equivalent to calling log() with LogLevel::INFO.
	 * Only active when WP_DEBUG is enabled, otherwise the call is silently ignored.
	 *
	 * The data to log - can be any type (array, object, scalar, etc.)
	 * @param	mixed							$data
	 *
	 * Optional hook preset name(s) or specific hook names to check status for.
	 * Can be a preset key ('load', 'register', 'admin', etc.), comma-separated string,
	 * or array of hook names. Defaults to 'register' preset if null.
	 * @param	array<int,string>|string|null	$hooks
	 *
	 * Optional title line to display at the top of the log entry
	 * @param	null|string						$title
	 *
	 * @return	bool	True if the log entry was written successfully, false if WP_DEBUG is disabled
	 */
	public static function info(mixed $data, null|string|array $hooks = null, ?string $title = null): bool
    {
		return self::log($data, $hooks, $title, LogLevel::INFO);
    }

	/**
	 * Log notice data with NOTICE level priority.
	 *
	 * Convenience method that logs notice data with human-friendly formatting and optional
	 * hook status information at NOTICE level. Equivalent to calling log() with LogLevel::NOTICE.
	 * Only active when WP_DEBUG is enabled, otherwise the call is silently ignored.
	 *
	 * The data to log - can be any type (array, object, scalar, etc.)
	 * @param	mixed							$data
	 *
	 * Optional hook preset name(s) or specific hook names to check status for.
	 * Can be a preset key ('load', 'register', 'admin', etc.), comma-separated string,
	 * or array of hook names. Defaults to 'register' preset if null.
	 * @param	array<int,string>|string|null	$hooks
	 *
	 * Optional title line to display at the top of the log entry
	 * @param	null|string						$title
	 *
	 * @return	bool	True if the log entry was written successfully, false if WP_DEBUG is disabled
	 */
	public static function notice(mixed $data, null|string|array $hooks = null, ?string $title = null): bool
    {
		return self::log($data, $hooks, $title, LogLevel::NOTICE);
    }

	/**
	 * Log warning data with WARNING level priority.
	 *
	 * Convenience method that logs warning data with human-friendly formatting and optional
	 * hook status information at WARNING level. Equivalent to calling log() with LogLevel::WARNING.
	 * Only active when WP_DEBUG is enabled, otherwise the call is silently ignored.
	 *
	 * The data to log - can be any type (array, object, scalar, etc.)
	 * @param	mixed							$data
	 *
	 * Optional hook preset name(s) or specific hook names to check status for.
	 * Can be a preset key ('load', 'register', 'admin', etc.), comma-separated string,
	 * or array of hook names. Defaults to 'register' preset if null.
	 * @param	array<int,string>|string|null	$hooks
	 *
	 * Optional title line to display at the top of the log entry
	 * @param	null|string						$title
	 *
	 * @return	bool	True if the log entry was written successfully, false if WP_DEBUG is disabled
	 */
	public static function warning(mixed $data, null|string|array $hooks = null, ?string $title = null): bool
    {
		return self::log($data, $hooks, $title, LogLevel::WARNING);
    }

	/**
	 * Log alert data with ALERT level priority.
	 *
	 * Convenience method that logs alert data with human-friendly formatting and optional
	 * hook status information at ALERT level. Equivalent to calling log() with LogLevel::ALERT.
	 * Only active when WP_DEBUG is enabled, otherwise the call is silently ignored.
	 *
	 * The data to log - can be any type (array, object, scalar, etc.)
	 * @param	mixed							$data
	 *
	 * Optional hook preset name(s) or specific hook names to check status for.
	 * Can be a preset key ('load', 'register', 'admin', etc.), comma-separated string,
	 * or array of hook names. Defaults to 'register' preset if null.
	 * @param	array<int,string>|string|null	$hooks
	 *
	 * Optional title line to display at the top of the log entry
	 * @param	null|string						$title
	 *
	 * @return	bool	True if the log entry was written successfully, false if WP_DEBUG is disabled
	 */
	public static function alert(mixed $data, null|string|array $hooks = null, ?string $title = null): bool
    {
		return self::log($data, $hooks, $title, LogLevel::ALERT);
    }

	/**
	 * Log emergency data with EMERGENCY level priority.
	 *
	 * Convenience method that logs emergency data with human-friendly formatting and optional
	 * hook status information at EMERGENCY level. Equivalent to calling log() with LogLevel::EMERGENCY.
	 * Only active when WP_DEBUG is enabled, otherwise the call is silently ignored.
	 *
	 * The data to log - can be any type (array, object, scalar, etc.)
	 * @param	mixed							$data
	 *
	 * Optional hook preset name(s) or specific hook names to check status for.
	 * Can be a preset key ('load', 'register', 'admin', etc.), comma-separated string,
	 * or array of hook names. Defaults to 'register' preset if null.
	 * @param	array<int,string>|string|null	$hooks
	 *
	 * Optional title line to display at the top of the log entry
	 * @param	null|string						$title
	 *
	 * @return	bool	True if the log entry was written successfully, false if WP_DEBUG is disabled
	 */
	public static function emergency(mixed $data, null|string|array $hooks = null, ?string $title = null): bool
    {
		return self::log($data, $hooks, $title, LogLevel::EMERGENCY);
    }

	/**
	 * Log error data with ERROR level priority.
	 *
	 * Convenience method that logs error data with human-friendly formatting and optional
	 * hook status information at ERROR level. Equivalent to calling log() with LogLevel::ERROR.
	 * Only active when WP_DEBUG is enabled, otherwise the call is silently ignored.
	 *
	 * The data to log - can be any type (array, object, scalar, etc.)
	 * @param	mixed							$data
	 *
	 * Optional hook preset name(s) or specific hook names to check status for.
	 * Can be a preset key ('load', 'register', 'admin', etc.), comma-separated string,
	 * or array of hook names. Defaults to 'register' preset if null.
	 * @param	array<int,string>|string|null	$hooks
	 *
	 * Optional title line to display at the top of the log entry
	 * @param	null|string						$title
	 *
	 * @return	bool	True if the log entry was written successfully, false if WP_DEBUG is disabled
	 */
	public static function error(mixed $data, null|string|array $hooks = null, ?string $title = null): bool
    {
		return self::log($data, $hooks, $title, LogLevel::ERROR);
    }

	/**
	 * Log critical data with CRITICAL level priority.
	 *
	 * Convenience method that logs critical data with human-friendly formatting and optional
	 * hook status information at CRITICAL level. Equivalent to calling log() with LogLevel::CRITICAL.
	 * Only active when WP_DEBUG is enabled, otherwise the call is silently ignored.
	 *
	 * The data to log - can be any type (array, object, scalar, etc.)
	 * @param	mixed							$data
	 *
	 * Optional hook preset name(s) or specific hook names to check status for.
	 * Can be a preset key ('load', 'register', 'admin', etc.), comma-separated string,
	 * or array of hook names. Defaults to 'register' preset if null.
	 * @param	array<int,string>|string|null	$hooks
	 *
	 * Optional title line to display at the top of the log entry
	 * @param	null|string						$title
	 *
	 * @return	bool	True if the log entry was written successfully, false if WP_DEBUG is disabled
	 */
	public static function critical(mixed $data, null|string|array $hooks = null, ?string $title = null): bool
    {
		return self::log($data, $hooks, $title, LogLevel::CRITICAL);
    }

	#endregion
}
