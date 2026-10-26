<?php
/**
 * Akeeba Engine
 *
 * @package   akeebaengine
 * @copyright Copyright (c)2006-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\S3\IntegrationTest\Tests;

use Akeeba\S3\Exception\CannotPutFile;
use Akeeba\S3\Input;
use Akeeba\S3\IntegrationTest\Environment;
use Akeeba\S3\IntegrationTest\IntegrationTestCase;

class ListFilesTest extends IntegrationTestCase
{
	private const PATHS = [
		'listtest_one.dat',
		'listtest_two.dat',
		'listtest_three.dat',
		'list_deeper/test_one.dat',
		'list_deeper/test_two.dat',
		'list_deeper/test_three.dat',
		'list_deeper/listtest_four.dat',
		'list_deeper/listtest_five.dat',
		'list_deeper/listtest_six.dat',
		'list_deeper/spam.dat',
		'list_deeper/listtest_deeper/seven.dat',
		'list_deeper/listtest_deeper/eight.dat',
		'spam.dat',
	];

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		$options = self::environmentOptions();
		$s3      = self::createConnector($options);
		$data    = self::getRandomData(self::TEN_KB);

		foreach (self::PATHS as $uri)
		{
			try
			{
				$s3->putObject(Input::createFromData($data), $options['bucket'], $uri);
			}
			catch (CannotPutFile $e)
			{
				// Expected for archival buckets
			}
		}
	}

	public static function tearDownAfterClass(): void
	{
		if (Environment::selected() === null)
		{
			parent::tearDownAfterClass();

			return;
		}

		$options = self::environmentOptions();
		$s3      = self::createConnector($options);

		foreach (self::PATHS as $uri)
		{
			try
			{
				$s3->deleteObject($options['bucket'], $uri);
			}
			catch (\Exception $e)
			{
				// No problem if I can't delete the file
			}
		}

		parent::tearDownAfterClass();
	}

	public function testGetAll(): void
	{
		$listing = $this->s3->getBucket($this->options['bucket'], 'listtest_');

		$this->assertListing(
			$listing,
			['listtest_one.dat', 'listtest_two.dat', 'listtest_three.dat'],
			// Not files in subdirectories, nor files not matching the prefix
			['listtest_four.dat', 'listtest_five.dat', 'listtest_six.dat', 'spam.dat', 'ham.dat']
		);
	}

	public function testGetContinue(): void
	{
		$bucket  = $this->options['bucket'];
		$listing = $this->s3->getBucket($bucket, 'listtest_', null, 1);

		$this->assertIsArray($listing, 'The files listing must be an array');
		$this->assertCount(1, $listing);

		$files     = array_keys($listing);
		$continued = $this->s3->getBucket($bucket, 'listtest_', array_shift($files));

		$this->assertIsArray($continued, 'The continued files listing must be an array');
		$this->assertCount(2, $continued);

		$this->assertListing(
			array_merge($listing, $continued),
			['listtest_one.dat', 'listtest_two.dat', 'listtest_three.dat'],
			['listtest_four.dat', 'listtest_five.dat', 'listtest_six.dat', 'spam.dat', 'ham.dat']
		);
	}

	public function testGetSubdirectoryFiles(): void
	{
		$listing = $this->s3->getBucket($this->options['bucket'], 'list_deeper/test_');

		$this->assertListing(
			$listing,
			['list_deeper/test_one.dat', 'list_deeper/test_two.dat', 'list_deeper/test_three.dat'],
			// Not files with a different prefix, nor files in subdirectories
			[
				'list_deeper/listtest_four.dat', 'list_deeper/listtest_five.dat', 'list_deeper/listtest_six.dat',
				'list_deeper/spam.dat', 'list_deeper/listtest_deeper/seven.dat', 'list_deeper/listtest_deeper/eight.dat',
			]
		);
	}

	public function testGetSubdirectoryFilesWithContinue(): void
	{
		$bucket  = $this->options['bucket'];
		$listing = $this->s3->getBucket($bucket, 'list_deeper/test_', null, 1);

		$this->assertIsArray($listing, 'The files listing must be an array');
		$this->assertCount(1, $listing);

		$files     = array_keys($listing);
		$continued = $this->s3->getBucket($bucket, 'list_deeper/test_', array_shift($files));

		$this->assertIsArray($continued, 'The continued files listing must be an array');
		$this->assertCount(2, $continued);

		$this->assertListing(
			array_merge($listing, $continued),
			['list_deeper/test_one.dat', 'list_deeper/test_two.dat', 'list_deeper/test_three.dat'],
			[
				'list_deeper/listtest_four.dat', 'list_deeper/listtest_five.dat', 'list_deeper/listtest_six.dat',
				'list_deeper/spam.dat', 'list_deeper/listtest_deeper/seven.dat', 'list_deeper/listtest_deeper/eight.dat',
			]
		);
	}

	/**
	 * The prefix list_deeper/listtest_ matches BOTH keys (files) and common prefixes (folders).
	 *
	 * Common prefixes have priority so the first request would return zero files. The Connector catches that
	 * internally and performs more requests until it has at least as many files as we requested.
	 */
	public function testListWithPrefixSharedWithFolder(): void
	{
		$bucket  = $this->options['bucket'];
		$listing = $this->s3->getBucket($bucket, 'list_deeper/listtest_', null, 1);

		$this->assertIsArray($listing, 'The files listing must be an array');
		$this->assertCount(1, $listing);

		$files     = array_keys($listing);
		$continued = $this->s3->getBucket($bucket, 'list_deeper/listtest_', array_shift($files));

		$this->assertIsArray($continued, 'The continued files listing must be an array');
		$this->assertCount(2, $continued);

		$this->assertListing(
			array_merge($listing, $continued),
			['list_deeper/listtest_four.dat', 'list_deeper/listtest_five.dat', 'list_deeper/listtest_six.dat'],
			[
				'list_deeper/test_one.dat', 'list_deeper/test_two.dat', 'list_deeper/test_three.dat',
				'list_deeper/spam.dat', 'list_deeper/listtest_deeper/seven.dat', 'list_deeper/listtest_deeper/eight.dat',
			]
		);
	}

	public function testCommonPrefixes(): void
	{
		$listing = $this->s3->getBucket($this->options['bucket'], 'list_deeper/listtest_', null, null, '/', true);

		$this->assertIsArray($listing, 'The files listing must be an array');
		$this->assertCount(4, $listing, 'Expected three files and one folder');

		foreach (
			[
				'list_deeper/listtest_four.dat', 'list_deeper/listtest_five.dat', 'list_deeper/listtest_six.dat',
				'list_deeper/listtest_deeper/',
			] as $key
		)
		{
			$this->assertArrayHasKey($key, $listing, "$key not in listing");
		}

		foreach (
			[
				'list_deeper/listtest_deeper/seven.dat', 'list_deeper/listtest_deeper/eight.dat', 'list_deeper/spam.dat',
				'list_deeper/test_one.dat', 'list_deeper/test_two.dat', 'list_deeper/test_three.dat',
			] as $key
		)
		{
			$this->assertArrayNotHasKey($key, $listing, "$key in listing");
		}

		foreach ($listing as $fileName => $info)
		{
			if (substr($fileName, -1) === '/')
			{
				$this->assertArrayHasKey('prefix', $info, 'Folder entries must return a prefix');

				continue;
			}

			$this->assertEntryInfo($info);
		}
	}

	/**
	 * @param   mixed     $listing   The result of getBucket()
	 * @param   string[]  $expected  Keys which must be listed
	 * @param   string[]  $absent    Keys which must not be listed
	 */
	private function assertListing($listing, array $expected, array $absent): void
	{
		$this->assertIsArray($listing, 'The files listing must be an array');
		$this->assertCount(count($expected), $listing);

		foreach ($expected as $key)
		{
			$this->assertArrayHasKey($key, $listing, "File $key not in listing");
		}

		foreach ($absent as $key)
		{
			$this->assertArrayNotHasKey($key, $listing, "File $key in listing");
		}

		foreach ($listing as $info)
		{
			$this->assertEntryInfo($info);
		}
	}

	private function assertEntryInfo(array $info): void
	{
		foreach (['name', 'time', 'size', 'hash'] as $field)
		{
			$this->assertArrayHasKey($field, $info, "File entries must have a $field");
		}
	}
}
