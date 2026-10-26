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
use PHPUnit\Framework\Attributes\Group;

/**
 * Upload, download and delete big files (over 1MB), without multipart uploads. Uses string or file sources.
 */
#[Group('slow')]
class BigFilesTest extends IntegrationTestCase
{
	/** Use multipart (chunked) uploads? */
	protected $multipart = false;

	/** Chunk size of each multipart upload. Must be at least 5MB or the library overrides us. */
	protected $uploadChunkSize = 5242880;

	/** Chunks per upload size when multipart is on. */
	protected const EXPECTED_CHUNKS = [
		self::FIVE_MB   => 1,
		self::SIX_MB    => 2,
		self::TEN_MB    => 2,
		self::ELEVEN_MB => 3,
	];

	public static function sizeProvider(): array
	{
		return [
			'5MB string'  => [self::FIVE_MB, true],
			'6MB string'  => [self::SIX_MB, true],
			'10MB string' => [self::TEN_MB, true],
			'11MB string' => [self::ELEVEN_MB, true],
			'5MB file'    => [self::FIVE_MB, false],
			'6MB file'    => [self::SIX_MB, false],
			'10MB file'   => [self::TEN_MB, false],
			'11MB file'   => [self::ELEVEN_MB, false],
		];
	}

	#[DataProvider('sizeProvider')]
	public function testUpload(int $size, bool $useString): void
	{
		$uri        = self::randomizeUri(sprintf('bigtest_%dmb.dat', $size / self::ONE_MB));
		$bucket     = $this->options['bucket'];
		$sourceFile = null;
		$download   = null;

		if ($useString)
		{
			$sourceData = self::getRandomData($size);
			$makeInput  = function () use ($sourceData): Input {
				return Input::createFromData($sourceData);
			};
		}
		else
		{
			$sourceFile = self::createFile($size);
			$makeInput  = function () use ($sourceFile): Input {
				return Input::createFromFile($sourceFile);
			};
		}

		try
		{
			$chunks = $this->multipart ? $this->uploadMultipart($makeInput, $bucket, $uri) : $this->uploadSingle($makeInput, $bucket, $uri);

			if ($this->multipart)
			{
				$this->assertSame(
					static::EXPECTED_CHUNKS[$size], $chunks,
					sprintf('Expected %d chunks, upload completed in %d chunks', static::EXPECTED_CHUNKS[$size], $chunks)
				);
			}

			if ($useString)
			{
				$this->assertSame($sourceData, $this->s3->getObject($bucket, $uri), 'The downloaded data differs from the uploaded data');
			}
			else
			{
				$download = self::tempPath();
				$this->s3->getObject($bucket, $uri, $download);

				$this->assertFileEquals($sourceFile, $download, 'The downloaded file differs from the uploaded one');
			}
		}
		finally
		{
			@unlink((string) $sourceFile);
			@unlink((string) $download);
			$this->deleteQuietly($bucket, $uri);
		}
	}

	private function uploadSingle(callable $makeInput, string $bucket, string $uri): int
	{
		$this->s3->putObject($makeInput(), $bucket, $uri);

		return 0;
	}

	/**
	 * @return  int  Number of uploaded parts
	 */
	private function uploadMultipart(callable $makeInput, string $bucket, string $uri): int
	{
		$uploadSession = $this->s3->startMultipart($makeInput(), $bucket, $uri);
		$eTags         = [];
		$partNumber    = 1;

		while (true)
		{
			// We need a new input for each upload chunk
			$input = $makeInput();
			$input->setUploadID($uploadSession);
			$input->setEtags($eTags);
			$input->setPartNumber($partNumber);

			$etag = $this->s3->uploadMultipart($input, $bucket, $uri, [], $this->uploadChunkSize);

			// A null result means there are no more file parts to process
			if ($etag === null)
			{
				break;
			}

			$eTags[] = $etag;
			$input->setEtags($eTags);
			$partNumber++;
		}

		// Tells the service to construct the object from the uploaded parts
		$this->s3->finalizeMultipart($input, $bucket, $uri);

		return count($eTags);
	}
}
