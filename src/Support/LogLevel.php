<?php
declare(strict_types=1);

namespace SmartDesk\Debug\Support;

/**
 * Minimal level set with comparable order (low -> high).
 */
final class LogLevel {

	public const DEBUG		= '🐞 DEBUG';
	public const INFO		= 'ℹ️ INFO';
	public const NOTICE		= '📋 NOTICE';
	public const WARNING	= '⚠️ WARNING';
	public const ALERT		= '🚨 ALERT';
	public const EMERGENCY	= '⛔ EMERGENCY';
	public const ERROR		= '❌ ERROR';
	public const CRITICAL	= '☠️ CRITICAL';
	

	/** @var array<string,int> */
	public const ORDER = [

		self::DEBUG		=> 0,
		self::INFO		=> 1,
		self::NOTICE	=> 2,
		self::WARNING	=> 3,
		self::ALERT		=> 4,
		self::EMERGENCY	=> 5,
		self::ERROR		=> 6,
		self::CRITICAL	=> 7,

	];
}
