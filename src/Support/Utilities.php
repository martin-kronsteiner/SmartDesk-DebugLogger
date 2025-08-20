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
 * Placeholder for future shared helpers (kept for BC with structure).
 */
final class Utilities
{
	/**
	 * Gets the current UTC time formatted as HH:MM:SS,microseconds.
	 * 
	 * This method creates a DateTime object in UTC timezone and formats it
	 * to include hours, minutes, seconds, and the first 5 digits of microseconds
	 * separated by a comma. This format is commonly used for precise logging
	 * and debugging purposes.
	 * 
	 * @return string The current UTC time in format "HH:MM:SS,MMMMM" where
	 *                HH is hours (00-23), MM is minutes (00-59), SS is seconds (00-59),
	 *                and MMMMM is the first 5 digits of microseconds (00000-99999)
	 */
	public static function utcTime(): string {
		$dt = new \DateTime('now', new \DateTimeZone('UTC'));
		return $dt->format('H:i:s') . ',' . substr($dt->format('u'), 0, 5);
	}

}
