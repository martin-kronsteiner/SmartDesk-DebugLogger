<?php

// SPDX-License-Identifier: GPL-3.0-or-later
declare(strict_types=1);

namespace SmartDesk\Utils\Tests;

use PHPUnit\Framework\TestCase;
use SmartDesk\Utils\DebugLogger;
use SmartDesk\Utils\Support\LogLevel;

if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}

final class MinLevelTest extends TestCase
{
    private string $tmpFile;
    protected function setUp(): void
	{
        $this->tmpFile = sys_get_temp_dir() . '/sd_minlvl_' . uniqid('', true) . '.log';
        DebugLogger::setWriter(function (string $line): void {

            file_put_contents($this->tmpFile, $line, FILE_APPEND);
        });
        DebugLogger::setMinLevel(null);
    }

    protected function tearDown(): void
	{
        if (is_file($this->tmpFile)) {
            @unlink($this->tmpFile);
        }
    }

    public function testInfoSuppressedWhenMinLevelIsWarning(): void
	{
        DebugLogger::setMinLevel(LogLevel::WARNING);
        DebugLogger::info('will not appear');
        DebugLogger::warning('this one appears');
        $log = (string) @file_get_contents($this->tmpFile);
        $this->assertStringContainsString(LogLevel::WARNING, $log, 'WARNING level should be present');
        $this->assertStringNotContainsString(LogLevel::INFO, $log, 'INFO level should be filtered out');
        $this->assertStringContainsString("\tthis one appears", $log, 'WARNING message should be present');
        $this->assertStringNotContainsString("\twill not appear", $log, 'INFO message should be filtered out');
    }

    public function testAllAllowedWhenNoMinLevel(): void
	{
        DebugLogger::setMinLevel(null);
        DebugLogger::info('hello info');
        DebugLogger::error('boom error');
        $log = (string) @file_get_contents($this->tmpFile);
        $this->assertStringContainsString(LogLevel::INFO, $log);
        $this->assertStringContainsString(LogLevel::ERROR, $log);
        $this->assertStringContainsString("\thello info", $log);
        $this->assertStringContainsString("\tboom error", $log);
    }

    public function testCriticalLogsAboveEmergency(): void
	{
        DebugLogger::setMinLevel(LogLevel::EMERGENCY);
        DebugLogger::critical('must show');
        $log = (string) @file_get_contents($this->tmpFile);
        $this->assertStringContainsString(LogLevel::CRITICAL, $log);
        $this->assertStringContainsString("\tmust show", $log);
    }
}
