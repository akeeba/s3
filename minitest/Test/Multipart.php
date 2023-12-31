<?php
/**
 * Akeeba Engine
 *
 * @package   akeebaengine
 * @copyright Copyright (c)2006-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\MiniTest\Test;


use Akeeba\S3\Connector;

class Multipart extends BigFiles
{
	public static function setup(Connector $s3, array $options): void
	{
		static::$multipart = true;

		parent::setup($s3, $options);
	}

	public static function upload5MBString(Connector $s3, array $options): bool
	{
		$result = parent::upload5MBString($s3, $options);

		$expectedChunks = 1;
		static::assert(static::$numberOfChunks === $expectedChunks, sprintf("Expected %s chunks, upload complete in %s chunks", $expectedChunks, static::$numberOfChunks));

		return $result;
	}

	public static function upload6MBString(Connector $s3, array $options): bool
	{
		$result = parent::upload6MBString($s3, $options);

		$expectedChunks = 2;
		static::assert(static::$numberOfChunks === $expectedChunks, sprintf("Expected %s chunks, upload complete in %s chunks", $expectedChunks, static::$numberOfChunks));

		return $result;
	}

	public static function upload10MBString(Connector $s3, array $options): bool
	{
		$result = parent::upload10MBString($s3, $options);

		$expectedChunks = 2;
		static::assert(static::$numberOfChunks === $expectedChunks, sprintf("Expected %s chunks, upload complete in %s chunks", $expectedChunks, static::$numberOfChunks));

		return $result;
	}

	public static function upload11MBString(Connector $s3, array $options): bool
	{
		$result = parent::upload11MBString($s3, $options);

		$expectedChunks = 3;
		static::assert(static::$numberOfChunks === $expectedChunks, sprintf("Expected %s chunks, upload complete in %s chunks", $expectedChunks, static::$numberOfChunks));

		return $result;
	}

	public static function upload5MBFile(Connector $s3, array $options): bool
	{
		$result = parent::upload5MBFile($s3, $options);

		$expectedChunks = 1;
		static::assert(static::$numberOfChunks === $expectedChunks, sprintf("Expected %s chunks, upload complete in %s chunks", $expectedChunks, static::$numberOfChunks));

		return $result;
	}

	public static function upload6MBFile(Connector $s3, array $options): bool
	{
		$result = parent::upload6MBFile($s3, $options);

		$expectedChunks = 2;
		static::assert(static::$numberOfChunks === $expectedChunks, sprintf("Expected %s chunks, upload complete in %s chunks", $expectedChunks, static::$numberOfChunks));

		return $result;
	}

	public static function upload10MBFile(Connector $s3, array $options): bool
	{
		$result = parent::upload10MBFile($s3, $options);

		$expectedChunks = 2;
		static::assert(static::$numberOfChunks === $expectedChunks, sprintf("Expected %s chunks, upload complete in %s chunks", $expectedChunks, static::$numberOfChunks));

		return $result;
	}

	public static function upload11MBFile(Connector $s3, array $options): bool
	{
		$result = parent::upload11MBFile($s3, $options);

		$expectedChunks = 3;
		static::assert(static::$numberOfChunks === $expectedChunks, sprintf("Expected %s chunks, upload complete in %s chunks", $expectedChunks, static::$numberOfChunks));

		return $result;
	}
}