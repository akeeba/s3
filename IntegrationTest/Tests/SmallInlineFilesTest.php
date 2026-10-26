<?php
/**
 * Akeeba Engine
 *
 * @package   akeebaengine
 * @copyright Copyright (c)2006-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\S3\IntegrationTest\Tests;

use Akeeba\S3\Input;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Upload, download and delete small files (under 1MB) using a string source
 */
class SmallInlineFilesTest extends SmallFilesTest
{
	#[DataProvider('fileProvider')]
	public function testUpload(int $size, string $uri): void
	{
		$uri        = self::randomizeUri($uri);
		$bucket     = $this->options['bucket'];
		$sourceData = $this->createPayload($size);

		try
		{
			$this->s3->putObject(Input::createFromData($sourceData), $bucket, $uri);

			if ($this->downloadAfter)
			{
				$this->assertSame($sourceData, $this->s3->getObject($bucket, $uri), 'The downloaded data differs from the uploaded data');
			}
			else
			{
				$this->addToAssertionCount(1);
			}
		}
		finally
		{
			if ($this->deleteRemote)
			{
				$this->s3->deleteObject($bucket, $uri);
			}
		}
	}

	protected function createPayload(int $size): string
	{
		return self::getRandomData($size);
	}
}
