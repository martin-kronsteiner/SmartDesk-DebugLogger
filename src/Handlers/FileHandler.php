<?php
declare(strict_types=1);

namespace SmartDesk\Debug\Handlers;

/**
 * FileHandler with size-based rotation and optional day-based cleanup.
 */
final class FileHandler {
	
	private string $dir;
	private string $filename;
	private int $maxBytes;
	private int $maxFiles;
	private int $maxDays;

	public function __construct(string $dir, string $filename = 'debug.log', int $maxBytes = 5_000_000, int $maxFiles = 5, int $maxDays = 14)
	{
		$this->dir = rtrim($dir, DIRECTORY_SEPARATOR);
		$this->filename = $filename;
		$this->maxBytes = $maxBytes;
		$this->maxFiles = $maxFiles;
		$this->maxDays = $maxDays;
	}

	/** @return callable(string,string,array):void */
	public function handler(): callable
	{
		$self = $this;
		return static function (string $level, string $line, array $payload) use ($self): void {
			$self->ensureDir();
			$path = $self->path();
			$self->rotateIfNeeded($path);
			$fp = fopen($path, 'ab');
			if ($fp) {
				fwrite($fp, $line . PHP_EOL);
				fclose($fp);
			}
			$self->cleanupOldFiles();
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
			// Shift old files: .4 -> .5, .3 -> .4, ...
			for ($i = $this->maxFiles - 1; $i >= 1; $i--) {
				$src = $path . '.' . $i;
				$dst = $path . '.' . ($i + 1);
				if (is_file($src)) {
					@rename($src, $dst);
				}
			}
			@rename($path, $path . '.1');
			// Touch new file
			@touch($path);
		}
	}

	private function cleanupOldFiles(): void
	{
		if ($this->maxDays <= 0) {
			return;
		}
		$cutoff = time() - ($this->maxDays * 86400);
		foreach (glob($this->dir . DIRECTORY_SEPARATOR . $this->filename . '*') ?: [] as $file) {
			if (@filemtime($file) !== false && filemtime($file) < $cutoff) {
				@unlink($file);
			}
		}
	}
}
