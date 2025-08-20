<?php
declare(strict_types=1);

namespace SmartDesk\Utils\Tests;

use PHPUnit\Framework\TestCase;
use SmartDesk\Utils\DebugLogger;
use SmartDesk\Utils\Support\LogLevel;

/**
 * Tests for pretty logging, levels, hooks and timers.
 * comments: English; tabs used for indent
 */
final class DebugLoggerTest extends TestCase
{
	/** @var string[] */
	private array $captures = [];

	protected function setUp(): void
	{
		parent::setUp();
		$this->captures = [];
		// capture sink into local buffer
		DebugLogger::setWriter(function (string $text): void {
			$this->captures[] = $text;
		});
	}

	private function lastEntry(): string
	{
		$this->assertNotEmpty($this->captures, 'No log was captured.');
		return (string) end($this->captures);
	}

	public function testLogWritesWithInfoLevelAndTitle(): void
	{
		DebugLogger::log(['🧪' => 'ok'], 'register', 'My Block', LogLevel::INFO);

		$entry = $this->lastEntry();

		// header contains level and caller "DebugLoggerTest::<method>()"
		$this->assertStringContainsString('ℹ️ INFO SmartDesk\\Utils\\Tests\\DebugLoggerTest::testLogWritesWithInfoLevelAndTitle()', $entry);
		$this->assertStringContainsString("My Block", $entry);
		$this->assertStringContainsString("\t🧪 => ok", $entry);
		$this->assertStringContainsString("-SmartDesk", $entry, 'Footer separator missing');
		$this->assertStringContainsString("Timestamp: 1755609635.4039 / Request: abc123", $entry, 'SMARTDESK_REQ missing');
	}

	public function testHookBlockRendersPendingIconsWithoutWordPress(): void
	{
		DebugLogger::log('hooks only', ['init', 'plugins_loaded'], 'Hooks test', LogLevel::DEBUG);

		$entry = $this->lastEntry();

		$this->assertStringContainsString("\thooks:\n", $entry);
		$this->assertStringContainsString("⏳ init", $entry);
		$this->assertStringContainsString("⏳ plugins_loaded", $entry);
	}

	public function testLevelShortcutsWork(): void
	{
		DebugLogger::warning('warn here', 'frontend');
		$entry = $this->lastEntry();

		$this->assertStringContainsString('⚠️ WARNING', $entry);
		$this->assertStringContainsString("\twarn here", $entry);
	}

	public function testTimerStartStopLogsDurationAndTitle(): void
	{
		DebugLogger::timerStart('import');
		usleep(20_000); // 20 ms to ensure a measurable duration
		DebugLogger::timerStop('import', 'Import job', LogLevel::NOTICE);

		$entry = $this->lastEntry();

		$this->assertStringContainsString('📋 NOTICE', $entry);
		$this->assertStringContainsString("\tImport job", $entry);
		$this->assertMatchesRegularExpression("/Timer 'import' finished in [0-9]+\\.[0-9]{4}s/", $entry);
	}

	public function testTimerStopWithoutStartLogsWarning(): void
	{
		DebugLogger::timerStop('unknown', null, LogLevel::WARNING);
		$entry = $this->lastEntry();

		$this->assertStringContainsString('⚠️ WARNING', $entry);
		$this->assertStringContainsString("\tTimer error", $entry);
		$this->assertStringContainsString("Timer 'unknown' not started", $entry);
	}

	public function testMakeRotatingWriterCreatesAndRotatesFiles(): void
	{
		$dir = sys_get_temp_dir() . '/sdlogger_' . uniqid();
		$writer = DebugLogger::makeRotatingWriter($dir, 'test.log', 80, 2);

		// 1st write
		$writer("first line\n");
		$this->assertFileExists($dir . '/test.log');

		// Force size >80 bytes to trigger rotation
		$big = str_repeat('X', 200) . "\n";
		$writer($big);

		// After rotation, current file exists and .1 likely exists
		$this->assertFileExists($dir . '/test.log');
		$this->assertFileExists($dir . '/test.log.1');
	}
}
