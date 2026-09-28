<?php
/**
 * Akeeba Engine
 *
 * @package   akeebaengine
 * @copyright Copyright (c)2006-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\MiniTest\Test;

use Akeeba\S3\Configuration;
use Akeeba\S3\Connector;
use Akeeba\S3\Input;
use RuntimeException;

/**
 * Use the library the way Akeeba Engine's Amazon S3 post-processing engine (Postproc\Amazons3) does: fetch an archive
 * back to the server in ranged chunks, hand the browser a pre-signed download URL carrying response-content-*
 * overrides, and set a custom endpoint without re-applying the signature method and region afterwards.
 */
class EngineStyle extends AbstractTest
{
	/**
	 * Engine downloadToFile() with offsets: fetch a 3MB object back in 1MB ranged chunks and reassemble it.
	 */
	public static function rangedDownloadToFile(Connector $s3, array $options): bool
	{
		$size   = 3 * 1048576 + 12345;
		$data   = static::getRandomData($size);
		$bucket = $options['bucket'];
		$uri    = 'enginetest/ranged.' . hash('md5', microtime(false)) . '.dat';

		$s3->putObject(Input::createFromData($data), $bucket, $uri);

		$assembled = '';
		$chunk     = 1048576;

		try
		{
			for ($from = 0; $from < $size; $from += $chunk)
			{
				$length = min($chunk, $size - $from);
				$tmp    = tempnam(static::getTempFolder(), 'as3');
				// Exactly as Amazons3::downloadToFile() computes it
				$s3->getObject($bucket, $uri, $tmp, $from, $from + $length - 1);
				$part = file_get_contents($tmp);
				@unlink($tmp);

				static::assert(strlen($part) === $length, sprintf('Range %d+%d returned %d bytes', $from, $length, strlen($part)));

				$assembled .= $part;
			}
		}
		finally
		{
			try { $s3->deleteObject($bucket, $uri); } catch (\Exception $e) {}
		}

		static::assert(static::areStringsEqual($data, $assembled), 'Reassembled ranged download differs from upload');

		return true;
	}

	/**
	 * Engine downloadToBrowser(): pre-signed URL carrying response-content-* overrides, fetched over HTTP.
	 */
	public static function downloadToBrowserURL(Connector $s3, array $options): bool
	{
		return static::fetchBrowserURL($s3, $options, 'test file ' . hash('md5', microtime(false)) . '.jpa');
	}

	/**
	 * Same, but with the connection forced to the opposite path-style setting from the target's default.
	 */
	public static function downloadToBrowserURLOtherPathStyle(Connector $s3, array $options): bool
	{
		// Amazon S3 proper does not do path-style access with v2 signatures; Configuration forces it off.
		if ($options['signature'] !== 'v4' && strpos($options['endpoint'] ?? 's3.amazonaws.com', 'amazonaws.com') !== false)
		{
			return true;
		}

		$options['path_access'] = !$options['path_access'];

		return static::fetchBrowserURL(static::engineConnector($options, true), $options, 'enginetest/other.' . hash('md5', microtime(false)) . '.jpa');
	}

	/**
	 * A connector configured the way the updated README says: endpoint set once, signature/region NOT re-applied.
	 */
	public static function noReapplyAfterEndpoint(Connector $s3, array $options): bool
	{
		$conn   = static::engineConnector($options, false);
		$c      = $conn->getConfiguration();

		static::assert($c->getSignatureMethod() === $options['signature'], 'Signature method changed to ' . $c->getSignatureMethod());

		if ($options['signature'] === 'v4')
		{
			static::assert($c->getRegion() === $options['region'], 'Region changed to "' . $c->getRegion() . '"');
		}

		$data   = static::getRandomData(AbstractTest::SIX_HUNDRED_KB);
		$bucket = $options['bucket'];
		$uri    = 'enginetest/noreapply.' . hash('md5', microtime(false)) . '.dat';

		$conn->putObject(Input::createFromData($data), $bucket, $uri);

		try
		{
			$tmp = tempnam(static::getTempFolder(), 'as3');
			$conn->getObject($bucket, $uri, $tmp);
			$back = file_get_contents($tmp);
			@unlink($tmp);
		}
		finally
		{
			try { $conn->deleteObject($bucket, $uri); } catch (\Exception $e) {}
		}

		static::assert(static::areStringsEqual($data, $back), 'Downloaded data differs');

		return true;
	}

	private static function fetchBrowserURL(Connector $s3, array $options, string $remotePath): bool
	{
		$data   = static::getRandomData(AbstractTest::TEN_KB);
		$bucket = $options['bucket'];

		$s3->putObject(Input::createFromData($data), $bucket, $remotePath);

		try
		{
			$queryParameters = [
				'response-content-type'        => 'application/octet-stream',
				'response-content-disposition' => sprintf('attachment; filename="%s"', basename($remotePath)),
			];
			$uri = $remotePath . '?' . http_build_query($queryParameters);
			$url = $s3->getAuthenticatedURL($bucket, $uri, 10, true);

			echo "\n\tURL: " . preg_replace('/(Signature|X-Amz-Signature|X-Amz-Credential|AWSAccessKeyId)=[^&]+/', '$1=…', $url) . "\n";

			$ch = curl_init($url);
			$headers = [];
			curl_setopt_array($ch, [
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HEADERFUNCTION => function ($ch, $h) use (&$headers) {
					$headers[] = trim($h);

					return strlen($h);
				},
				CURLOPT_TIMEOUT        => 60,
			]);
			$body = curl_exec($ch);
			$code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
			curl_close($ch);
		}
		finally
		{
			try { $s3->deleteObject($bucket, $remotePath); } catch (\Exception $e) {}
		}

		if ($code !== 200)
		{
			throw new RuntimeException("HTTP $code: " . substr((string) $body, 0, 300));
		}

		static::assert(static::areStringsEqual($data, $body), 'Wrong data from pre-signed URL');

		$cd = array_values(array_filter($headers, fn($h) => stripos($h, 'content-disposition:') === 0));
		echo "\t" . ($cd[0] ?? 'NO Content-Disposition header') . "\n";

		return true;
	}

	/**
	 * Build a connector like Postproc\Amazons3::getConnector(), optionally re-applying signature/region after the
	 * endpoint as the engine does.
	 */
	private static function engineConnector(array $o, bool $reapply): Connector
	{
		$c = new Configuration($o['access'], $o['secret'], $o['signature'] ?? 'v2', $o['region'] ?? '');
		$c->setSSL($o['ssl'] ?? true);
		$c->setUseDualstackUrl($o['dualstack'] ?? false);

		if (!empty($o['endpoint']))
		{
			$c->setEndpoint($o['endpoint']);

			if ($reapply)
			{
				$c->setSignatureMethod($o['signature'] ?? 'v2');
				$c->setRegion($o['region'] ?? '');
			}
		}

		$c->setUseLegacyPathStyle((bool) $o['path_access']);
		$c->setAlternateDateHeaderFormat((bool) ($o['alternateDateHeaderFormat'] ?? false));
		$c->setUseHTTPDateHeader((bool) ($o['useHTTPDateHeader'] ?? false));
		$c->setPreSignedBucketInURL((bool) ($o['preSignedBucketInURL'] ?? false));

		return new Connector($c);
	}
}
