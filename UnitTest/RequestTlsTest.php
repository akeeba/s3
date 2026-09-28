<?php
/**
 * Akeeba Engine
 *
 * @package   akeebaengine
 * @copyright Copyright (c)2006-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\S3\UnitTest;

use Akeeba\S3\Configuration;
use Akeeba\S3\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * TLS host name verification for Amazon S3 hosts.
 *
 * Host name verification may only be turned off when the host name has more labels than Amazon's
 * wildcard certificate can match, i.e. when a dotted bucket name is used with virtual-hosted access. The
 * number of dots Amazon's own endpoint contributes depends on the endpoint: the dual-stack endpoint
 * (s3.dualstack.REGION.amazonaws.com) has one more than the plain one (s3.REGION.amazonaws.com), and the
 * China endpoints (….amazonaws.com.cn) have one more again.
 */
#[CoversClass(Request::class)]
class RequestTlsTest extends TestCase
{
	protected function tearDown(): void
	{
		CurlRecorder::stop();

		parent::tearDown();
	}

	/**
	 * @return array<string, array{0: array<string, mixed>, 1: string, 2: string, 3: int}>
	 */
	public static function provideConnections(): array
	{
		$v4       = ['signature' => 'v4', 'region' => 'us-east-1'];
		$dual     = ['dualstack' => true] + $v4;
		$path     = ['pathStyle' => true];
		$cn       = ['signature' => 'v4', 'region' => 'cn-north-1'];
		$cnDual   = ['dualstack' => true] + $cn;

		return [
			// Plain regional endpoint: bucket.s3.us-east-1.amazonaws.com
			'v4, plain bucket'                   => [$v4, 'mybucket', 'mybucket.s3.us-east-1.amazonaws.com', 2],
			'v4, dotted bucket'                  => [$v4, 'my.bucket', 'my.bucket.s3.us-east-1.amazonaws.com', 0],
			// Dual-stack endpoint: bucket.s3.dualstack.us-east-1.amazonaws.com
			'v4 dual-stack, plain bucket'        => [$dual, 'mybucket', 'mybucket.s3.dualstack.us-east-1.amazonaws.com', 2],
			'v4 dual-stack, dotted bucket'       => [$dual, 'my.bucket', 'my.bucket.s3.dualstack.us-east-1.amazonaws.com', 0],
			// China: the endpoint ends in amazonaws.com.cn, one more dot (bucket.s3.cn-north-1.amazonaws.com.cn)
			'China, plain bucket'                => [$cn, 'mybucket', 'mybucket.s3.cn-north-1.amazonaws.com.cn', 2],
			'China, dotted bucket'               => [$cn, 'my.bucket', 'my.bucket.s3.cn-north-1.amazonaws.com.cn', 0],
			'China dual-stack, plain bucket'     => [$cnDual, 'mybucket', 'mybucket.s3.dualstack.cn-north-1.amazonaws.com.cn', 2],
			'China dual-stack, dotted bucket'    => [$cnDual, 'my.bucket', 'my.bucket.s3.dualstack.cn-north-1.amazonaws.com.cn', 0],
			// Path-style: the bucket is not part of the host name, so nothing can ever trip verification
			'v4 dual-stack path, dotted bucket'  => [$dual + $path, 'my.bucket', 's3.dualstack.us-east-1.amazonaws.com', 2],
			'v4 path, dotted bucket'             => [$v4 + $path, 'my.bucket', 's3.us-east-1.amazonaws.com', 2],
			// v2 signatures use the configured endpoint as-is; dual-stack does not apply
			'v2 with dual-stack on, plain bucket' => [['signature' => 'v2', 'dualstack' => true], 'mybucket', 'mybucket.s3.amazonaws.com', 2],
			// Not Amazon: never turned off
			'custom endpoint, many dots'         => [['signature' => 'v2', 'endpoint' => 'a.b.c.d.example.com'], 'my.bucket', 'my.bucket.a.b.c.d.example.com', 2],
		];
	}

	#[DataProvider('provideConnections')]
	public function testVerifiesTheHostNameUnlessTheBucketBreaksTheCertificate(
		array $setup, string $bucket, string $expectedHost, int $expectedVerifyHost
	): void
	{
		$request = new Request('HEAD', $bucket, '/some/key.txt', $this->configuration($setup));

		$this->assertSame($expectedHost, $request->getHeaders()['Host'], 'The test is not exercising the intended host.');

		CurlRecorder::start();
		$request->getResponse();
		CurlRecorder::stop();

		$this->assertSame($expectedVerifyHost, CurlRecorder::option(CURLOPT_SSL_VERIFYHOST));
		$this->assertTrue(CurlRecorder::option(CURLOPT_SSL_VERIFYPEER), 'The certificate itself must always be verified.');
	}

	public function testDualStackDoesNotSwitchToPathStyleAccess(): void
	{
		// Path-style access is an explicit option; fixing verification must never turn it on by itself.
		$config  = $this->configuration(['signature' => 'v4', 'region' => 'us-east-1', 'dualstack' => true]);
		$request = new Request('HEAD', 'my.bucket', '/some/key.txt', $config);

		$this->assertFalse($config->getUseLegacyPathStyle());
		$this->assertSame('my.bucket.s3.dualstack.us-east-1.amazonaws.com', $request->getHeaders()['Host']);
	}

	/**
	 * @param   array<string, mixed>  $setup
	 */
	private function configuration(array $setup): Configuration
	{
		$config = new Configuration('AKIAEXAMPLE', 'secret', $setup['signature'] ?? 'v4', $setup['region'] ?? '');

		$config->setSSL(true);

		if (isset($setup['endpoint']))
		{
			$config->setEndpoint($setup['endpoint']);
		}

		$config->setUseDualstackUrl($setup['dualstack'] ?? false);
		$config->setUseLegacyPathStyle($setup['pathStyle'] ?? false);

		return $config;
	}
}
