<?php
declare(strict_types=1);

namespace SmartDesk\Debug;

use SmartDesk\Debug\Support\LogLevel;
use SmartDesk\Debug\Handlers\FileHandler;
use SmartDesk\Debug\Handlers\ErrorLogHandler;
use SmartDesk\Debug\Handlers\CallbackHandler;

/**
 * DebugLogger
 *
 * Lightweight logger with:
 * - PSR-3-like levels (subset)
 * - Multiple handlers (file, PHP error_log, custom callback)
 * - File rotation by size and/or max age
 * - Context interpolation with {placeholders}
 * - Timers (start/stop/lap) using hrtime
 * - Optional WP helpers (safe to use outside WP)
 *
 * Tabs are used for indentation to match project style.
 */
final class DebugLogger
{
	/** @var string */
	private string $channel;

	/** @var string */
	private string $minLevel;

	/** @var array<int, callable(string, string, array): void> */
	private array $handlers = [];

	/** @var array<string, int> */
	private array $timers = [];

	/**
	 * @param string $channel Arbitrary channel name to tag entries.
	 * @param string $minLevel Minimum level to log (see LogLevel constants).
	 */
	public function __construct(string $channel = 'app', string $minLevel = LogLevel::DEBUG)
	{
		$this->channel = $channel;
		$this->minLevel = $minLevel;
	}

	/** Add default file handler with rotation. */
	public function withFile(string $dir, string $filename = 'debug.log', int $maxBytes = 5_000_000, int $maxFiles = 5, int $maxDays = 14): self
	{
		$this->handlers[] = (new FileHandler($dir, $filename, $maxBytes, $maxFiles, $maxDays))->handler();
		return $this;
	}

	/** Add PHP error_log handler. */
	public function withErrorLog(): self
	{
		$this->handlers[] = (new ErrorLogHandler())->handler();
		return $this;
	}

	/** Add arbitrary callback handler. */
	public function withCallback(callable $callback): self
	{
		$this->handlers[] = (new CallbackHandler($callback))->handler();
		return $this;
	}

	/** Set minimum level. */
	public function setMinLevel(string $minLevel): void
	{
		$this->minLevel = $minLevel;
	}

	/** Log at DEBUG level. */
	public function debug(string $message, array $context = []): void
	{
		$this->log(LogLevel::DEBUG, $message, $context);
	}

	public function info(string $message, array $context = []): void
	{
		$this->log(LogLevel::INFO, $message, $context);
	}

	public function notice(string $message, array $context = []): void
	{
		$this->log(LogLevel::NOTICE, $message, $context);
	}

	public function warning(string $message, array $context = []): void
	{
		$this->log(LogLevel::WARNING, $message, $context);
	}

	public function error(string $message, array $context = []): void
	{
		$this->log(LogLevel::ERROR, $message, $context);
	}

	public function critical(string $message, array $context = []): void
	{
		$this->log(LogLevel::CRITICAL, $message, $context);
	}

	/**
	 * Generic log method.
	 */
	public function log(string $level, string $message, array $context = []): void
	{
		if (!$this->shouldLog($level)) {
			return;
		}

		$timestamp = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z');
		$message = $this->interpolate($message, $context);
		$payload = [
			'ts' => $timestamp,
			'level' => $level,
			'channel' => $this->channel,
			'message' => $message,
			'context' => $this->sanitizeContext($context),
		];

		$line = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		if ($line === false) {
			$line = sprintf('{"ts":"%s","level":"%s","channel":"%s","message":"%s"}', $timestamp, $level, $this->channel, addslashes($message));
		}

		foreach ($this->handlers as $handler) {
			$handler($level, (string)$line, $payload);
		}
	}

	/** Timer helpers */
	public function timerStart(string $name): void
	{
		// Store high-resolution start time
		$this->timers[$name] = hrtime(true);
	}

	public function timerLap(string $name, string $message = 'lap', array $context = []): void
	{
		$elapsed = $this->elapsedNs($name);
		$this->debug($message, array_merge($context, ['timer' => $name, 'elapsed_ms' => $elapsed / 1_000_000]));
	}

	public function timerStop(string $name, string $message = 'stop', array $context = []): void
	{
		$elapsed = $this->elapsedNs($name);
		unset($this->timers[$name]);
		$this->info($message, array_merge($context, ['timer' => $name, 'elapsed_ms' => $elapsed / 1_000_000]));
	}

	/** WP helper: attach to hook and log execution time. Safe outside WP. */
	public function wrapHook(string $hookName, callable $callback, string $level = LogLevel::DEBUG): callable
	{
		return function (...$args) use ($hookName, $callback, $level) {
			$start = hrtime(true);
			try {
                /** @var mixed $result */
				$result = $callback(...$args);
				return $result;
			} finally {
				$elapsedMs = (hrtime(true) - $start) / 1_000_000;
				$this->log($level, 'hook {hook} executed', [
					'hook' => $hookName,
					'elapsed_ms' => $elapsedMs,
				]);
			}
		};
	}

	/** Optional: register shutdown/error/exception handlers. */
	public function registerGlobalHandlers(bool $captureErrors = true, bool $captureExceptions = true, bool $captureShutdown = true): void
	{
		if ($captureErrors) {
			set_error_handler(function (int $errno, string $errstr, ?string $file = null, ?int $line = null): bool {
				$this->error('php_error', ['errno' => $errno, 'message' => $errstr, 'file' => $file, 'line' => $line]);
				return false; // let PHP continue default handling
			});
		}
		if ($captureExceptions) {
			set_exception_handler(function (\Throwable $e): void {
				$this->critical('uncaught_exception', [
					'type' => $e::class,
					'message' => $e->getMessage(),
					'file' => $e->getFile(),
					'line' => $e->getLine(),
					'trace' => $e->getTraceAsString(),
				]);
			});
		}
		if ($captureShutdown) {
			register_shutdown_function(function (): void {
				$err = error_get_last();
				if ($err) {
					$this->critical('shutdown_error', $err);
				}
			});
		}
	}

	/** Determine if given level should be logged. */
	private function shouldLog(string $level): bool
	{
		$order = array_flip(LogLevel::ORDER); // DEBUG=>0, INFO=>1, ...
		return ($order[$level] ?? PHP_INT_MAX) >= ($order[$this->minLevel] ?? PHP_INT_MAX);
	}

	/** Simple {placeholder} interpolation. */
	private function interpolate(string $message, array $context): string
	{
		$replace = [];
		foreach ($context as $key => $val) {
			if (is_scalar($val)) {
				$replace['{' . $key . '}'] = (string)$val;
			}
		}
		return strtr($message, $replace);
	}

	/** Redact obvious PII fields. */
	private function sanitizeContext(array $context): array
	{
		$redactKeys = ['password', 'pass', 'pwd', 'token', 'authorization', 'auth', 'secret'];
		foreach ($redactKeys as $k) {
			if (array_key_exists($k, $context)) {
				$context[$k] = '[redacted]';
			}
		}
		return $context;
	}

	private function elapsedNs(string $name): int
	{
		$start = $this->timers[$name] ?? null;
		if ($start === null) {
			return 0;
		}
		return hrtime(true) - $start;
	}
}
