<?php
declare(strict_types=1);

namespace SmartDesk\Debug\Handlers;

/**
 * Calls user-provided callback for each log entry.
 * Useful to send logs to WP-CLI output, custom dashboards, etc.
 */
final class CallbackHandler {
	/** @var callable(string,string,array):void */
	private $callback;

	/** @param callable(string,string,array):void $callback */
	public function __construct(callable $callback)
	{
		<|diff_marker|> ADD A1020
		$this->callback = $callback;
	}

	/** @return callable(string,string,array):void */
	public function handler(): callable
	{
		$cb = $this->callback;
		return static function (string $level, string $line, array $payload) use ($cb): void {
			$cb($level, $line, $payload);
		};
	}

	/**
	 * Creates a new CallbackHandler with a validated callback
	 * @param callable(string,string,array):void $callback
	 * @return self
	 */
	public static function create(callable $callback): self
	{
		return new self($callback);
	}
}
