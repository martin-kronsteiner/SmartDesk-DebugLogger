<?php
declare(strict_types=1);

namespace SmartDesk\Utils\Handlers;

/**
 * Sends a single line to PHP error_log().
 */
final class ErrorLogHandler
{
	/** @return callable(string,string,array):void */
	public function handler(): callable
	{
		return static function (string $level, string $line, array $payload): void {
			error_log($line);
		};
	}
}
