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

namespace SmartDesk\Utils\Handlers;

/**
 * Handles logging messages to PHP's built-in error log.
 *
 * This class provides a simple handler for logging messages using PHP's error_log() function.
 * It implements a factory pattern that returns a callable handler function which can be used
 * by logging systems to write log entries to the system error log.
 */
final class ErrorLogHandler
{
	/**
	 * Returns a callable handler function that logs messages to PHP's error log.
	 *
	 * This method creates and returns a closure that can be used as a logging handler.
	 * The returned function accepts logging parameters and writes the formatted log
	 * line to PHP's error log using the built-in error_log() function.
	 *
	 * A callable that accepts three parameters:
	 * 		- string $level:	The log level (e.g., 'error', 'warning', 'info')
	 * 		- string $line:		The formatted log message to be written
	 * 		- array $payload:	Additional context data (unused in this implementation)
	 * Returns void after logging the message
	 * @return callable(string,string,array<string,mixed>):void
	 */
	public function handler(): callable
    {
		return static function (string $level, string $line, array $payload): void {

			error_log($line);
        };
	}
}
