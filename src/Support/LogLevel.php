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

namespace SmartDesk\Utils\Support;

/**
 * Defines log levels with emoji indicators and hierarchical ordering.
 * 
 * This class provides a standardized set of log levels for debugging and monitoring
 * purposes. Each level includes an emoji indicator for visual identification and
 * maintains a numerical order from lowest (DEBUG) to highest (CRITICAL) severity.
 * 
 * The ordering system allows for level comparison and filtering, where higher
 * numbers indicate more severe log conditions that typically require immediate
 * attention.
 */
final class LogLevel {

	public const DEBUG		= '🐞 DEBUG';
	public const INFO		= 'ℹ️ INFO';
	public const NOTICE		= '📋 NOTICE';
	public const WARNING	= '⚠️ WARNING';
	public const ALERT		= '🚨 ALERT';
	public const EMERGENCY	= '⛔ EMERGENCY';
	public const ERROR		= '❌ ERROR';
	public const CRITICAL	= '☠️ CRITICAL';
	

	/** @var array<string,int> */
	public const ORDER = [

		self::DEBUG		=> 0,
		self::INFO		=> 1,
		self::NOTICE	=> 2,
		self::WARNING	=> 3,
		self::ALERT		=> 4,
		self::EMERGENCY	=> 5,
		self::ERROR		=> 6,
		self::CRITICAL	=> 7,

	];
	
}
