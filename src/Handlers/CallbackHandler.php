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
 * Simple callback wrapper to unify signature.
 *
 * This class provides a standardized wrapper around user-defined callback functions
 * to ensure they conform to the logging framework's expected signature. It acts as
 * an adapter that allows arbitrary callable functions to be integrated seamlessly
 * into the logging system while maintaining consistent parameter handling.
 *
 * The CallbackHandler is designed for symmetry with other handlers in the framework,
 * even though it's not directly used by DebugLogger. It enables developers to
 * create custom logging handlers using simple callback functions without needing
 * to implement complex handler interfaces.
 */
final class CallbackHandler
{
	/** @var callable(string,string,array<string,mixed>):void */
	private $callback;

	/**
	 * Initializes the CallbackHandler with a user-provided callback function.
	 *
	 * The constructor accepts a callable that must conform to the logging signature,
	 * taking a log level, message line, and payload array as parameters. This callback
	 * will be stored and later executed through the handler() method.
	 *
	 * The callback function to be wrapped.
	 * Must accept three parameters:
	 * 		- string $level: The log level (e.g., 'info', 'error')
	 * 		- string $line: The log message or line content
	 * 		- array $payload: Additional data or context for the log entry
	 * @param callable(string,string,array<string,mixed>):void  $callback
	 *
	 */
	public function __construct(callable $callback)
    {
		$this->callback = $callback;
    }

	/**
	 * Returns a callable wrapper that executes the stored callback with logging parameters.
	 *
	 * This method provides a standardized callable interface that can be used by logging
	 * systems. The returned function maintains the same signature as other handlers in
	 * the logging framework while delegating execution to the user-provided callback.
	 *
	 * @return callable(string,string,array<string,mixed>):void	A callable that accepts logging parameters
	 * 															and forwards them to the stored callback
	 */
	public function handler(): callable
    {
		$cb = $this->callback;
        return static function (string $level, string $line, array $payload) use ($cb): void {

			$cb($level, $line, $payload);
        };
	}
}
