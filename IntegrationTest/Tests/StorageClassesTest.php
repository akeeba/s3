<?php
/**
 * Akeeba Engine
 *
 * @package   akeebaengine
 * @copyright Copyright (c)2006-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\S3\IntegrationTest\Tests;

use Akeeba\S3\Acl;
use Akeeba\S3\Input;
use Akeeba\S3\IntegrationTest\IntegrationTestCase;
use Akeeba\S3\StorageClass;
use PHPUnit\Framework\Attributes\DataProvider;

class StorageClassesTest extends IntegrationTestCase
{
	public static function storageClassProvider(): array
	{
		return [
			'Reduced redundancy'  => [StorageClass::REDUCED_REDUNDANCY],
			'Intelligent tiering' => [StorageClass::INTELLIGENT_TIERING],
		];
	}

	#[DataProvider('storageClassProvider')]
	public function testUpload(string $storageClass): void
	{
		if ($this->options['storageClasses'] !== null && !in_array($storageClass, $this->options['storageClasses'], true))
		{
			$this->markTestSkipped(sprintf('Environment %s does not support the %s storage class', $this->options['name'], $storageClass));
		}

		$uri        = self::randomizeUri('storage_class_test_10kb.dat');
		$bucket     = $this->options['bucket'];
		$sourceData = self::getRandomData(self::TEN_KB);
		$headers    = [];

		StorageClass::setStorageClass($headers, $storageClass);

		try
		{
			$this->s3->putObject(Input::createFromData($sourceData), $bucket, $uri, Acl::ACL_PRIVATE, $headers);

			$this->assertSame($sourceData, $this->s3->getObject($bucket, $uri), 'The downloaded data differs from the uploaded data');
		}
		finally
		{
			$this->deleteQuietly($bucket, $uri);
		}
	}
}
