<?php
/**
 * Akeeba Engine
 *
 * @package   akeebaengine
 * @copyright Copyright (c)2006-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\S3\IntegrationTest\Tests;

use Akeeba\S3\IntegrationTest\IntegrationTestCase;

class BucketsListTest extends IntegrationTestCase
{
	public function testListBucketsDetailed(): void
	{
		$buckets = $this->s3->listBuckets(true);

		$this->assertIsArray($buckets, 'Detailed buckets list is not an array');
		$this->assertArrayHasKey('owner', $buckets, 'Detailed buckets list does not list an owner');
		$this->assertArrayHasKey('id', $buckets['owner'], "Detailed buckets list does not list an owner's id");
		// Amazon no longer sends the owner's display name, so the name may legitimately be an empty string.
		$this->assertIsString($buckets['owner']['name'] ?? null, "Detailed buckets list does not list an owner's name");
		$this->assertArrayHasKey('buckets', $buckets, 'Detailed buckets list does not list any buckets');

		$names = [];

		foreach ($buckets['buckets'] as $bucketInfo)
		{
			$this->assertArrayHasKey('name', $bucketInfo, 'Bucket information does not list a name');
			$this->assertArrayHasKey('time', $bucketInfo, 'Bucket information does not list a created time');

			$names[] = $bucketInfo['name'];
		}

		$this->assertContains($this->options['bucket'], $names, 'Detailed buckets list does not include the configured bucket');
	}

	public function testListBucketsSimple(): void
	{
		$buckets = $this->s3->listBuckets(false);

		$this->assertIsArray($buckets, 'Simple buckets list is not an array');
		$this->assertContains($this->options['bucket'], $buckets, 'Simple buckets list does not include the configured bucket');
	}
}
