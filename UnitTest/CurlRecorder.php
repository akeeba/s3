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
 * Request lives in the Akeeba\S3 namespace and calls curl_setopt() / curl_exec() / curl_getinfo()
 * unqualified, so PHP looks for Akeeba\S3\curl_setopt() before the global function. These shadows delegate
 * to the real functions, except while CurlRecorder is recording: then every option is recorded, and
 * curl_exec() either fails at once or, with respond(), plays a canned response through Request's own header
 * and body callbacks, without connecting anywhere.
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

		/** @var array{0: int, 1: array<string, string>, 2: string}|null */
		public static $response = null;

		public static function start(): void
		{
			self::$recording = true;
			self::$options   = [];
			self::$response  = null;
		}

		public static function stop(): void
		{
			self::$recording = false;
			self::$response  = null;
		}

		/**
		 * Answer the next request with this HTTP status, headers and body.
		 *
		 * @param   array<string, string>  $headers
		 */
		public static function respond(int $code, array $headers, string $body): void
		{
			self::$response = [$code, $headers, $body];
		}

		/**
		 * Play the canned response through the request's own cURL callbacks.
		 *
		 * @param   mixed  $handle
		 */
		public static function play($handle): bool
		{
			if (self::$response === null)
			{
				return false;
			}

			[$code, $headers, $body] = self::$response;

			self::callback(CURLOPT_HEADERFUNCTION, $handle, "HTTP/1.1 $code Canned\r\n");

			foreach ($headers as $name => $value)
			{
				self::callback(CURLOPT_HEADERFUNCTION, $handle, "$name: $value\r\n");
			}

			self::callback(CURLOPT_WRITEFUNCTION, $handle, $body);

			return true;
		}

		/**
		 * @param   mixed  $handle
		 */
		private static function callback(int $option, $handle, string $data): void
		{
			[$object, $method] = self::$options[$option];

			// Request's callbacks are protected; cURL may call them, a test needs reflection.
			(new \ReflectionMethod($object, $method))->invoke($object, $handle, $data);
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
			return CurlRecorder::$recording ? CurlRecorder::play($handle) : \curl_exec($handle);
		}
	}

	if (!\function_exists(__NAMESPACE__ . '\\curl_getinfo'))
	{
		/**
		 * @return mixed
		 */
		function curl_getinfo($handle, ?int $option = null)
		{
			if (CurlRecorder::$recording && CurlRecorder::$response !== null && $option === CURLINFO_HTTP_CODE)
			{
				return CurlRecorder::$response[0];
			}

			return $option === null ? \curl_getinfo($handle) : \curl_getinfo($handle, $option);
		}
	}
}
