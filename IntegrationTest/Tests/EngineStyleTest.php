<?php
/**
 * Akeeba Engine
 *
 * @package   akeebaengine
 * @copyright Copyright (c)2006-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\S3\IntegrationTest\Tests;

use Akeeba\S3\Configuration;
use Akeeba\S3\Connector;
use Akeeba\S3\Input;
use Akeeba\S3\IntegrationTest\IntegrationTestCase;

/**
 * Use the library the way Akeeba Engine's Amazon S3 post-processing engine (Postproc\Amazons3) does: fetch an archive
 * back to the server in ranged chunks, hand the browser a pre-signed download URL carrying response-content-*
 * overrides, and set a custom endpoint without re-applying the signature method and region afterwards.
 */
class EngineStyleTest extends IntegrationTestCase
{
	/**
	 * Engine downloadToFile() with offsets: fetch a 3MB object back in 1MB ranged chunks and reassemble it.
	 */
	public function testRangedDownloadToFile(): void
	{
		$size   = 3 * self::ONE_MB + 12345;
		$data   = self::getRandomData($size);
		$bucket = $this->options['bucket'];
		$uri    = 'enginetest/ranged.' . hash('md5', uniqid('', true)) . '.dat';

		$this->s3->putObject(Input::createFromData($data), $bucket, $uri);

		$assembled = '';
		$chunk     = self::ONE_MB;

		try
		{
			for ($from = 0; $from < $size; $from += $chunk)
			{
				$length = min($chunk, $size - $from);
				$tmp    = self::tempPath();

				try
				{
					// Exactly as Amazons3::downloadToFile() computes it
					$this->s3->getObject($bucket, $uri, $tmp, $from, $from + $length - 1);
					$part = file_get_contents($tmp);
				}
				finally
				{
					@unlink($tmp);
				}

				$this->assertSame($length, strlen($part), sprintf('Range %d+%d returned %d bytes', $from, $length, strlen($part)));

				$assembled .= $part;
			}
		}
		finally
		{
			$this->deleteQuietly($bucket, $uri);
		}

		$this->assertSame($data, $assembled, 'Reassembled ranged download differs from upload');
	}

	/**
	 * Engine downloadToBrowser(): pre-signed URL carrying response-content-* overrides, fetched over HTTP.
	 */
	public function testDownloadToBrowserURL(): void
	{
		$this->fetchBrowserURL($this->s3, 'test file ' . hash('md5', uniqid('', true)) . '.jpa');
	}

	/**
	 * Same, but with the connection forced to the opposite path-style setting from the target's default.
	 */
	public function testDownloadToBrowserURLOtherPathStyle(): void
	{
		// Amazon S3 proper does not do path-style access with v2 signatures; Configuration forces it off.
		if ($this->options['signature'] !== 'v4' && strpos($this->options['endpoint'] ?? 's3.amazonaws.com', 'amazonaws.com') !== false)
		{
			$this->markTestSkipped('Amazon S3 does not support path-style access with v2 signatures');
		}

		if ($this->options['path_access'] && !$this->options['virtualHosts'])
		{
			$this->markTestSkipped('This environment cannot resolve virtual-hosted (bucket.host) URLs');
		}

		$options                = $this->options;
		$options['path_access'] = !$options['path_access'];

		$this->fetchBrowserURL(self::engineConnector($options, true), 'enginetest/other.' . hash('md5', uniqid('', true)) . '.jpa');
	}

	/**
	 * A connector configured the way the README says: endpoint set once, signature/region NOT re-applied.
	 */
	public function testNoReapplyAfterEndpoint(): void
	{
		$conn = self::engineConnector($this->options, false);
		$c    = $conn->getConfiguration();

		$this->assertSame($this->options['signature'], $c->getSignatureMethod(), 'Signature method changed');

		if ($this->options['signature'] === 'v4')
		{
			$this->assertSame($this->options['region'], $c->getRegion(), 'Region changed');
		}

		$data   = self::getRandomData(self::SIX_HUNDRED_KB);
		$bucket = $this->options['bucket'];
		$uri    = 'enginetest/noreapply.' . hash('md5', uniqid('', true)) . '.dat';
		$tmp    = self::tempPath();

		try
		{
			$conn->putObject(Input::createFromData($data), $bucket, $uri);
			$conn->getObject($bucket, $uri, $tmp);

			$this->assertSame($data, file_get_contents($tmp), 'Downloaded data differs');
		}
		finally
		{
			@unlink($tmp);
			$this->deleteQuietly($bucket, $uri);
		}
	}

	private function fetchBrowserURL(Connector $s3, string $remotePath): void
	{
		$data   = self::getRandomData(self::TEN_KB);
		$bucket = $this->options['bucket'];

		$s3->putObject(Input::createFromData($data), $bucket, $remotePath);

		try
		{
			$queryParameters = [
				'response-content-type'        => 'application/octet-stream',
				'response-content-disposition' => sprintf('attachment; filename="%s"', basename($remotePath)),
			];
			$url             = $s3->getAuthenticatedURL($bucket, $remotePath . '?' . http_build_query($queryParameters), 10, $this->options['ssl']);

			$ch      = curl_init($url);
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
		}
		finally
		{
			$this->deleteQuietly($bucket, $remotePath);
		}

		// Never print the URL: it carries a valid signature.
		$this->assertSame(200, $code, 'HTTP ' . $code . ': ' . substr((string) $body, 0, 300));
		$this->assertSame($data, $body, 'Wrong data from the pre-signed URL');

		$disposition = array_values(array_filter($headers, function ($h) {
			return stripos($h, 'content-disposition:') === 0;
		}));

		$this->assertNotEmpty($disposition, 'The response has no Content-Disposition header');
		$this->assertStringContainsString('attachment', $disposition[0]);
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
