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

namespace SmartDesk\Utils\Handlers;

/**
 * Minimal file writer with size-based rotation.
 * 
 * This class provides a file-based logging handler that automatically manages
 * log file rotation based on file size limits. It creates a callable handler
 * that can be used to write log entries to files while maintaining a specified
 * number of rotated backup files. The handler ensures directory creation,
 * manages file size limits, and performs automatic cleanup of old log files.
 * 
 * The rotation mechanism works by renaming existing files with numeric suffixes
 * (e.g., debug.log becomes debug.log.1, debug.log.1 becomes debug.log.2, etc.)
 * when the current log file exceeds the maximum size threshold. Files beyond
 * the configured maximum number of rotated files are automatically removed.
 */
final class FileHandler {

	private string	$dir;
	private string	$filename;
	private int		$maxBytes;
	private int		$maxFiles;

	/**
	 * Initializes a new FileHandler instance with directory and rotation settings.
	 *
	 * @param	string		$dir		The directory path where log files will be stored. Trailing directory separators will be removed.
	 * @param	string		$filename	The name of the log file. Defaults to 'debug.log'.
	 * @param	int			$maxBytes	The maximum file size in bytes before rotation occurs. Defaults to 2,000,000 bytes (2MB).
	 * @param	int			$maxFiles	The maximum number of rotated files to keep. Defaults to 4 files.
	 */
	public function __construct(string $dir, string $filename = 'debug.log', int $maxBytes = 2_000_000, int $maxFiles = 4) {
		$this->dir		= rtrim($dir, DIRECTORY_SEPARATOR);
		$this->filename	= $filename;
		$this->maxBytes	= $maxBytes;
		$this->maxFiles	= $maxFiles;
	}

	/**
	 * Returns a callable handler function for writing log entries to a file with automatic rotation.
	 *
	 * The returned callable accepts log level, formatted log line, and payload data,
	 * then writes the log line to the configured file. The handler automatically
	 * ensures the target directory exists, rotates files when size limits are exceeded,
	 * and safely handles file operations.
	 *
	 * @return callable(string,string,array):void	A callable that accepts three parameters:
	 * 													- string	$level:		The log level (e.g., 'info', 'error', 'debug')
	 * 													- string	$line:		The formatted log message ready for writing
	 * 													- array		$payload:	Additional context data (not used in file writing)
	 */
	/** @return array<string,mixed> */
	public function handler(): callable	{
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

	/**
	 * Ensures that the target directory exists, creating it if necessary.
	 *
	 * This method checks if the configured directory path exists and is a valid directory.
	 * If the directory does not exist, it attempts to create it with 0775 permissions,
	 * including any necessary parent directories. The operation is performed with
	 * error suppression to handle cases where directory creation might fail due to
	 * permissions or other filesystem issues.
	 *
	 * @return void
	 */
	private function ensureDir(): void {
		if (!is_dir($this->dir)) {
			@mkdir($this->dir, 0775, true);
		}
	}

	/**
	 * Constructs and returns the full file path for the log file.
	 *
	 * This method combines the configured directory path with the filename
	 * using the appropriate directory separator for the current operating system.
	 * The resulting path points to the main log file where new entries will be written.
	 *
	 * @return string The complete file path to the log file, including directory and filename.
	 */
	private function path(): string {
		return $this->dir . DIRECTORY_SEPARATOR . $this->filename;
	}

	/**
	 * Rotates log files when the current file exceeds the maximum size limit.
	 *
	 * This method checks if the current log file has exceeded the configured maximum
	 * file size. If rotation is needed, it shifts existing rotated files by renaming
	 * them with incremented numeric suffixes (e.g., debug.log.1 becomes debug.log.2),
	 * and moves the current log file to become the first rotated file. Files beyond
	 * the maximum number of rotated files are automatically removed. The operation
	 * uses error suppression to handle potential filesystem issues gracefully.
	 *
	 * @param	string	$path	The full file path to the current log file that should be checked for rotation.
	 *
	 * @return	void
	 */
	private function rotateIfNeeded(string $path): void	{
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
