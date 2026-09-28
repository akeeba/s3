<?php
/**
 * Akeeba Engine
 *
 * @package   akeebaengine
 * @copyright Copyright (c)2006-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/**
 * Records the cURL options Request::getResponse() really sets, without any network traffic.
 *
 * Request lives in the Akeeba\S3 namespace and calls curl_setopt() / curl_exec() unqualified, so PHP looks
 * for Akeeba\S3\curl_setopt() before the global function. These shadows delegate to the real functions,
 * except while CurlRecorder is recording: then every option is recorded and curl_exec() fails at once
 * instead of connecting anywhere.
 *
 * This must be loaded before Request first calls either function (the bootstrap does that), because PHP
 * caches the resolution of an unqualified function call.
 */

namespace Akeeba\S3\UnitTest
{
	final class CurlRecorder
	{
		/** @var bool */
		public static $recording = false;

		/** @var array<int, mixed> */
		public static $options = [];

		public static function start(): void
		{
			self::$recording = true;
			self::$options   = [];
		}

		public static function stop(): void
		{
			self::$recording = false;
		}

		/**
		 * @return mixed
		 */
		public static function option(int $option)
		{
			return self::$options[$option] ?? null;
		}
	}
}

namespace Akeeba\S3
{
	use Akeeba\S3\UnitTest\CurlRecorder;

	if (!\function_exists(__NAMESPACE__ . '\\curl_setopt'))
	{
		function curl_setopt($handle, int $option, $value): bool
		{
			if (CurlRecorder::$recording)
			{
				CurlRecorder::$options[$option] = $value;

				return true;
			}

			return \curl_setopt($handle, $option, $value);
		}
	}

	if (!\function_exists(__NAMESPACE__ . '\\curl_exec'))
	{
		/**
		 * @return string|bool
		 */
		function curl_exec($handle)
		{
			return CurlRecorder::$recording ? false : \curl_exec($handle);
		}
	}
}
