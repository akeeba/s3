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
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Upload, download and delete small files (under 1MB) using a file source
 */
class SmallFilesTest extends IntegrationTestCase
{
	/** Download the file after uploading it, to check the contents survived? */
	protected $downloadAfter = true;

	/** Delete the uploaded file at the end of the test? */
	protected $deleteRemote = true;

	public static function fileProvider(): array
	{
		return [
			'10KB, root'           => [self::TEN_KB, 'root_10kb.dat'],
			'10KB, root, Greek'    => [self::TEN_KB, 'δοκιμή_10kb.dat'],
			'10KB, folder, Greek'  => [self::TEN_KB, 'ο_φάκελός_μου/δοκιμή_10kb.dat'],
			'600KB, root'          => [self::SIX_HUNDRED_KB, 'root_600kb.dat'],
			'10KB, folder'         => [self::TEN_KB, 'my_folder/10kb.dat'],
			'600KB, folder'        => [self::SIX_HUNDRED_KB, 'my_folder/600kb.dat'],
		];
	}

	#[DataProvider('fileProvider')]
	public function testUpload(int $size, string $uri): void
	{
		$uri    = self::randomizeUri($uri);
		$bucket = $this->options['bucket'];
		$files  = [];

		try
		{
			$files[] = $sourceFile = self::createFile($size);

			$this->s3->putObject(Input::createFromFile($sourceFile), $bucket, $uri);

			if ($this->downloadAfter)
			{
				$files[] = $downloadedFile = self::tempPath();

				$this->s3->getObject($bucket, $uri, $downloadedFile);

				$this->assertFileEquals($sourceFile, $downloadedFile, 'The downloaded file differs from the uploaded one');
			}
			else
			{
				$this->addToAssertionCount(1);
			}
		}
		finally
		{
			array_map('unlink', array_filter($files, 'is_file'));

			if ($this->deleteRemote)
			{
				$this->s3->deleteObject($bucket, $uri);
			}
		}
	}
}
