<?php
/**
 * Akeeba Engine
 *
 * @package   akeebaengine
 * @copyright Copyright (c)2006-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\S3\IntegrationTest\Tests;

use Akeeba\S3\Exception\CannotGetFile;
use Akeeba\S3\Input;
use Akeeba\S3\IntegrationTest\IntegrationTestCase;

class HeadObjectTest extends IntegrationTestCase
{
	public function testExistingFile(): void
	{
		$uri        = self::randomizeUri('head_test.dat');
		$bucket     = $this->options['bucket'];
		$sourceFile = self::createFile(self::TEN_KB);

		try
		{
			$this->s3->putObject(Input::createFromFile($sourceFile), $bucket, $uri);

			$headers = $this->s3->headObject($bucket, $uri);

			$this->assertArrayHasKey('size', $headers, 'The returned headers do not contain the object size');
			$this->assertEquals(self::TEN_KB, $headers['size'], 'The returned size does not match');
		}
		finally
		{
			@unlink($sourceFile);
			$this->deleteQuietly($bucket, $uri);
		}
	}

	public function testMissingFile(): void
	{
		$this->expectException(CannotGetFile::class);

		$this->s3->headObject($this->options['bucket'], hash('md5', uniqid('', true)) . '_does_not_exist');
	}
}
