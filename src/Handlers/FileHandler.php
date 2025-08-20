<?php
declare(strict_types=1);

namespace SmartDesk\Utils\Handlers;

/**
 * Minimal file writer with size-based rotation.
 */
final class FileHandler
{
	private string $dir;
	private string $filename;
	private int $maxBytes;
	private int $maxFiles;

	public function __construct(string $dir, string $filename = 'debug.log', int $maxBytes = 2_000_000, int $maxFiles = 4)
	{
		$this->dir = rtrim($dir, DIRECTORY_SEPARATOR);
		$this->filename = $filename;
		$this->maxBytes = $maxBytes;
		$this->maxFiles = $maxFiles;
	}

	/** @return callable(string,string,array):void */
	public function handler(): callable
	{
		$self = $this;
		return static function (string $level, string $line, array $payload) use ($self): void {
			$self->ensureDir();
			$path = $self->path();
			$self->rotateIfNeeded($path);
			$fp = @fopen($path, 'ab');
			if ($fp) {
				fwrite($fp, $line . PHP_EOL);
				fclose($fp);
			}
		};
	}

	private function ensureDir(): void
	{
		if (!is_dir($this->dir)) {
			@mkdir($this->dir, 0775, true);
		}
	}

	private function path(): string
	{
		return $this->dir . DIRECTORY_SEPARATOR . $this->filename;
	}

	private function rotateIfNeeded(string $path): void
	{
		clearstatcache(true, $path);
		$size = is_file($path) ? filesize($path) : 0;
		if ($size !== false && $size > $this->maxBytes) {
			for ($i = $this->maxFiles; $i >= 1; $i--) {
				$src = $path . ($i === 1 ? '' : '.' . ($i - 1));
				$dst = $path . '.' . $i;
				if (is_file($src)) {
					@rename($src, $dst);
				}
			}
		}
	}
}
