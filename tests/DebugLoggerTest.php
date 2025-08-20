<?php
declare(strict_types=1);

namespace SmartDesk\Debug\Tests;

use PHPUnit\Framework\TestCase;
use SmartDesk\Debug\DebugLogger;
use SmartDesk\Debug\Support\LogLevel;

final class DebugLoggerTest extends TestCase {

	public function test_it_logs_to_file_and_rotates(): void
	{
		$dir = sys_get_temp_dir() . '/sd-logger-' . bin2hex(random_bytes(3));
		$logger = (new DebugLogger('test', LogLevel::DEBUG))
			->withFile($dir, 'test.log', 500, 2, 1);

		for ($i = 0; $i < 300; $i++) {
			$logger->debug('hello {i}', ['i' => $i, 'password' => 'secret']);
		}

		$this->assertFileExists($dir . '/test.log');
		$this->assertFileExists($dir . '/test.log.1');
		$this->assertStringContainsString('[redacted]', file_get_contents(glob($dir . '/test.log*')[0]));
	}

	public function test_timer_helpers(): void
	{
		$logger = new DebugLogger();
		$logger->timerStart('work');
		usleep(10_000);
		$logger->timerStop('work', 'finished');
		$this->assertTrue(true); // basic smoke test
	}
	
}
