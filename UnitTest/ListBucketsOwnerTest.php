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
use PHPUnit\Framework\TestCase;

/**
 * The detailed bucket listing keeps the owner even when Amazon sends no display name.
 *
 * Amazon's ListBuckets response now carries only <Owner><ID>; <DisplayName> is gone. The owner used to be dropped
 * from the result altogether when the name was missing.
 */
#[CoversClass(Connector::class)]
class ListBucketsOwnerTest extends TestCase
{
	protected function tearDown(): void
	{
		CurlRecorder::stop();

		parent::tearDown();
	}

	public function testOwnerWithoutDisplayNameIsStillListed(): void
	{
		$listing = $this->listDetailed('<Owner><ID>abc123</ID></Owner>');

		$this->assertSame(['id' => 'abc123', 'name' => ''], $listing['owner']);
		$this->assertSame('my-bucket', $listing['buckets'][0]['name']);
	}

	public function testOwnerWithDisplayNameKeepsIt(): void
	{
		$listing = $this->listDetailed('<Owner><ID>abc123</ID><DisplayName>someUser</DisplayName></Owner>');

		$this->assertSame(['id' => 'abc123', 'name' => 'someUser'], $listing['owner']);
	}

	private function listDetailed(string $ownerXml): array
	{
		$body = '<?xml version="1.0" encoding="UTF-8"?>'
			. '<ListAllMyBucketsResult xmlns="http://s3.amazonaws.com/doc/2006-03-01/">'
			. $ownerXml
			. '<Buckets><Bucket><Name>my-bucket</Name><CreationDate>2026-01-02T03:04:05.000Z</CreationDate></Bucket></Buckets>'
			. '</ListAllMyBucketsResult>';

		$connector = new Connector(new Configuration('AKIAEXAMPLE', 'secret', 'v4', 'us-east-1'));

		CurlRecorder::start();
		CurlRecorder::respond(200, ['Content-Type' => 'application/xml'], $body);

		return $connector->listBuckets(true);
	}
}
