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

class BucketLocationTest extends IntegrationTestCase
{
	public function testGetBucketLocation(): void
	{
		$this->assertSame(
			$this->options['region'],
			$this->s3->getBucketLocation($this->options['bucket']),
			'The bucket reports a different region than the configured one'
		);
	}
}
