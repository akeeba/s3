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
use Akeeba\S3\IntegrationTest\IntegrationTestCase;

/**
 * Upload, download and delete a tiny text file using a string source
 */
class SingleSmallFileTest extends IntegrationTestCase
{
	public function testUpload(): void
	{
		$uri        = self::randomizeUri('test.txt');
		$bucket     = $this->options['bucket'];
		$sourceData = 'This is a small text file.';

		try
		{
			$this->s3->putObject(Input::createFromData($sourceData), $bucket, $uri);

			$this->assertSame($sourceData, $this->s3->getObject($bucket, $uri));
		}
		finally
		{
			$this->deleteQuietly($bucket, $uri);
		}
	}
}
