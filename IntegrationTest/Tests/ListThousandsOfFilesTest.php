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
 * Listing more files than fit in one response (S3 returns at most 1000 per request).
 *
 * Creating the 2100 files takes a while; set <ENVIRONMENT>_CREATE_2100_FILES=false to skip these tests.
 */
#[Group('slow')]
class ListThousandsOfFilesTest extends IntegrationTestCase
{
	private const PATH_PREFIX = 'massive/';

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		$options = self::environmentOptions();

		if (!$options['create2100Files'])
		{
			self::markTestSkipped('Creating 2100 files is disabled for this environment');
		}

		$s3   = self::createConnector($options);
		$data = self::getRandomData(128);

		for ($i = 1; $i <= 2100; $i++)
		{
			$s3->putObject(Input::createFromData($data), $options['bucket'], sprintf('%stest_%04u.dat', self::PATH_PREFIX, $i));
		}
	}

	public static function listingProvider(): array
	{
		return [
			'All'            => [self::PATH_PREFIX, null, 2100, 1],
			'First hundred'  => [self::PATH_PREFIX, 100, 100, 1],
			'First 1100'     => [self::PATH_PREFIX, 1100, 1100, 1],
			'Last hundred'   => [self::PATH_PREFIX . 'test_20', null, 100, 2000],
		];
	}

	#[DataProvider('listingProvider')]
	public function testListing(string $prefix, ?int $maxKeys, int $expectedCount, int $firstIndex): void
	{
		$listing = $this->s3->getBucket($this->options['bucket'], $prefix, null, $maxKeys);

		$this->assertIsArray($listing, 'The files listing must be an array');
		$this->assertCount($expectedCount, $listing);

		for ($i = $firstIndex; $i < $firstIndex + $expectedCount; $i++)
		{
			$this->assertArrayHasKey(sprintf('%stest_%04u.dat', self::PATH_PREFIX, $i), $listing);
		}
	}
}
