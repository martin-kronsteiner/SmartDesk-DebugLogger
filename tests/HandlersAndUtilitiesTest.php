<?php

// SPDX-License-Identifier: GPL-3.0-or-later
declare(strict_types=1);

namespace SmartDesk\Utils\Tests;

use PHPUnit\Framework\TestCase;
use SmartDesk\Utils\Handlers\CallbackHandler;
use SmartDesk\Utils\Handlers\ErrorLogHandler;
use SmartDesk\Utils\Handlers\FileHandler;
use SmartDesk\Utils\Support\Utilities;

final class HandlersAndUtilitiesTest extends TestCase
{
	public function testCallbackHandlerPassesThroughArguments(): void
	{
		$seen = [];
        $cb = new CallbackHandler(function (string $level, string $line, array $payload) use (&$seen): void {
			$seen = [$level, $line, $payload];
        });
        $h = $cb->handler();
        $h('ℹ️ INFO', 'hello world', ['a' => 1]);
        $this->assertSame('ℹ️ INFO', $seen[0]);
        $this->assertSame('hello world', $seen[1]);
        $this->assertSame(['a' => 1], $seen[2]);
    }

	public function testErrorLogHandlerCallableExists(): void
	{
		$h = (new ErrorLogHandler())->handler();
		$this->assertIsCallable($h);
        $h('ℹ️ INFO', 'line', []);
        $this->assertTrue(true);
    }

	public function testFileHandlerWritesAndRotates(): void
    {
		$dir = sys_get_temp_dir() . '/sdlogger_handlers_' . uniqid();
        $handler = new FileHandler($dir, 'f.log', 60, 2);
        $h = $handler->handler();
        $h('ℹ️ INFO', 'short line', []);
        $this->assertFileExists($dir . '/f.log');
        $this->assertStringContainsString('short line', (string) file_get_contents($dir . '/f.log'));
		$h('ℹ️ INFO', str_repeat('Z', 200), []);
		$h('ℹ️ INFO', 'trigger rotation', []);
        $this->assertFileExists($dir . '/f.log');
        $this->assertFileExists($dir . '/f.log.1');
    }

	public function testUtcTimeFormat(): void
    {
		$time = Utilities::utcTime();
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}:\d{2},\d{5}$/', $time);
	}
}
