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
use PHPUnit\Framework\TestCase;

/**
 * A custom (S3-compatible) endpoint keeps the signature method and region the caller chose.
 *
 * setEndpoint() used to switch every non-Amazon endpoint to v2, and switching to v2 empties the region. A
 * caller setting v4 again afterwards got v4 requests signed for region "", which services that check the region
 * (as Amazon S3 does) refuse.
 */
#[CoversClass(Configuration::class)]
class CustomEndpointTest extends TestCase
{
	protected function tearDown(): void
	{
		CurlRecorder::stop();

		parent::tearDown();
	}

	public function testSettingACustomEndpointKeepsV4AndTheRegion(): void
	{
		$config = new Configuration('AKIAEXAMPLE', 'secret', 'v4', 'us-east-1');
		$config->setEndpoint('storage.example.com:9000');

		$this->assertSame('v4', $config->getSignatureMethod());
		$this->assertSame('us-east-1', $config->getRegion());
	}

	public function testV4RequestsToACustomEndpointAreSignedForTheRegion(): void
	{
		$config = new Configuration('AKIAEXAMPLE', 'secret', 'v4', 'eu-central-1');
		$config->setEndpoint('storage.example.com:9000');
		$config->setUseLegacyPathStyle(true);

		$request = new Request('HEAD', 'my-bucket', '/key.txt', $config);

		CurlRecorder::start();
		$request->getResponse();
		CurlRecorder::stop();

		$authorization = array_values(
			preg_grep('/^Authorization:/i', CurlRecorder::option(CURLOPT_HTTPHEADER) ?? [])
		)[0] ?? '';

		$this->assertMatchesRegularExpression('#Credential=AKIAEXAMPLE/\d{8}/eu-central-1/s3/aws4_request#', $authorization);
	}

	public function testTheDefaultSignatureMethodIsStillV2(): void
	{
		$config = new Configuration('AKIAEXAMPLE', 'secret');
		$config->setEndpoint('storage.example.com:9000');

		$this->assertSame('v2', $config->getSignatureMethod());
	}
}
