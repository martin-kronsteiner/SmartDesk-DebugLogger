<?php
declare(strict_types=1);

namespace SmartDesk\Utils\Support;

/**
 * Placeholder for future shared helpers (kept for BC with structure).
 */
final class Utilities
{
	/** @return string UTC time like 12:34:56,12345 */
	public static function utcTime(): string
	{
		$dt = new \DateTime('now', new \DateTimeZone('UTC'));
		return $dt->format('H:i:s') . ',' . substr($dt->format('u'), 0, 5);
	}
}
