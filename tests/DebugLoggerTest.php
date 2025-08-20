<?php

// SPDX-License-Identifier: GPL-3.0-or-later
declare(strict_types=1);

namespace SmartDesk\Utils\Tests;

use PHPUnit\Framework\TestCase;
use SmartDesk\Utils\DebugLogger;
use SmartDesk\Utils\Support\LogLevel;

final class DebugLoggerTest extends TestCase
{
	/** @var string[] */
	private array $captures = [];
    protected function setUp(): void
    {
		parent::setUp();
        $this->captures = [];
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
		$this->assertStringContainsString(
			'ℹ️ INFO SmartDesk\\Utils\\Tests\\DebugLoggerTest::testLogWritesWithInfoLevelAndTitle()',
			$entry
		);
        $this->assertStringContainsString("My Block", $entry);
        $this->assertStringContainsString("\t🧪 => ok", $entry);
        $this->assertStringContainsString("-SmartDesk", $entry, 'Footer separator missing');
        $this->assertStringContainsString(
			"Timestamp: 1755609635.4039 / Request: abc123",
			$entry,
			'SMARTDESK_REQ missing'
		);
    }

	public function testHookBlockRendersPendingIconsWithoutWordPress(): void
    {
		DebugLogger::log('hooks only', ['init', 'plugins_loaded'], 'Hooks test', LogLevel::DEBUG);
        $entry = $this->lastEntry();
		$norm = preg_replace("/\r\n?/", "\n", $entry);
        $this->assertStringContainsString("\thooks:\n", $norm);
        $this->assertStringContainsString("⏳ init", $norm);
        $this->assertStringContainsString("⏳ plugins_loaded", $norm);
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
        usleep(20_000);
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
		$writer("first line\n");
        $this->assertFileExists($dir . '/test.log');
		$writer(str_repeat('X', 200) . "\n");
		$writer("trigger rotation\n");
		$this->assertFileExists($dir . '/test.log');
        $this->assertFileExists($dir . '/test.log.1');
	}
}
