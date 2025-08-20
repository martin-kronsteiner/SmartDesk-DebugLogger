<?php
declare(strict_types=1);

namespace SmartDesk\Debug\Handlers;

/**
 * Writes to PHP's error_log() (useful on hosts piping this to syslog).
 */
final class ErrorLogHandler {

	/** @return callable(string,string,array):void */
	public function handler(): callable
	{
		return static function (string $level, string $line, array $payload): void {
			error_log($line);
		};
	}
	
}
