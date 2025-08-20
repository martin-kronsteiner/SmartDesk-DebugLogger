<?php
declare(strict_types=1);

namespace SmartDesk\Utils\Handlers;

/**
 * Simple callback wrapper to unify signature.
 * Not used directly by DebugLogger, but provided for symmetry.
 */
final class CallbackHandler
{
	/** @var callable(string,string,array):void */
	private $callback;

	/** @param callable(string,string,array):void $callback */
	public function __construct(callable $callback)
	{
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
}
