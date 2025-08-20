<?php
declare(strict_types=1);

namespace SmartDesk\Debug\Support;

/**
 * Minimal level set with comparable order (low -> high).
 */
final class LogLevel {

	public const DEBUG		= 'DEBUG';
	public const INFO		= 'INFO';
	public const NOTICE		= 'NOTICE';
	public const WARNING	= 'WARNING';
	public const ERROR		= 'ERROR';
	public const CRITICAL	= 'CRITICAL';

	/** @var array<string,int> */
	public const ORDER = [

		self::DEBUG		=> 0,
		self::INFO		=> 1,
		self::NOTICE	=> 2,
		self::WARNING	=> 3,
		self::ERROR		=> 4,
		self::CRITICAL	=> 5,

	];
}
