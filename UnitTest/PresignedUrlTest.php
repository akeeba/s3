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
use Akeeba\S3\Connector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Where the bucket goes in a pre-signed URL.
 *
 * Path-style access is the caller's choice (setUseLegacyPathStyle()): a pre-signed URL must follow it, or the
 * URL names a host such as bucket.minio:9000 which S3-compatible servers without per-bucket DNS never answer.
 */
#[CoversClass(Connector::class)]
class PresignedUrlTest extends TestCase
{
	/**
	 * @return array<string, array{0: string, 1: array<string, mixed>, 2: string}>
	 */
	public static function provideConnections(): array
	{
		$custom = ['endpoint' => 'storage.example.com:9000'];

		return [
			'v4 custom, path-style'               => ['v4', $custom + ['pathStyle' => true], 'https://storage.example.com:9000/my-bucket/some/key.txt?'],
			'v4 custom, virtual-hosted'           => ['v4', $custom, 'https://my-bucket.storage.example.com:9000/some/key.txt?'],
			'v4 custom, bucket-in-URL option'     => ['v4', $custom + ['bucketInUrl' => true], 'https://storage.example.com:9000/my-bucket/some/key.txt?'],
			'v4 Amazon, path-style'               => ['v4', ['pathStyle' => true], 'https://s3.amazonaws.com/my-bucket/some/key.txt?'],
			'v4 Amazon, virtual-hosted'           => ['v4', [], 'https://my-bucket.s3.amazonaws.com/some/key.txt?'],
			'v2 custom, path-style'               => ['v2', $custom + ['pathStyle' => true], 'https://storage.example.com:9000/my-bucket/some/key.txt?'],
		];
	}

	#[DataProvider('provideConnections')]
	public function testTheBucketGoesWhereTheConnectionSays(string $signature, array $setup, string $expectedPrefix): void
	{
		$config = new Configuration('AKIAEXAMPLE', 'secret', $signature, 'us-east-1');

		if (isset($setup['endpoint']))
		{
			$config->setEndpoint($setup['endpoint']);
		}

		// After setEndpoint(), which switches non-Amazon endpoints to v2 (the order applications use)
		$config->setSignatureMethod($signature);
		$config->setUseLegacyPathStyle($setup['pathStyle'] ?? false);
		$config->setPreSignedBucketInURL($setup['bucketInUrl'] ?? false);

		$url = (new Connector($config))->getAuthenticatedURL('my-bucket', 'some/key.txt', 3600, true);

		$this->assertStringStartsWith($expectedPrefix, $url);
		$this->assertStringContainsString($signature === 'v4' ? 'X-Amz-Signature=' : 'Signature=', $url);
	}

	public function testThePresignedUrlDoesNotChangeTheCallersConfiguration(): void
	{
		$config = new Configuration('AKIAEXAMPLE', 'secret', 'v4', 'us-east-1');
		$config->setEndpoint('storage.example.com:9000');
		$config->setSignatureMethod('v4');
		$config->setUseLegacyPathStyle(true);

		(new Connector($config))->getAuthenticatedURL('my-bucket', 'some/key.txt', 3600, true);

		$this->assertFalse($config->getPreSignedBucketInURL());
	}
}
