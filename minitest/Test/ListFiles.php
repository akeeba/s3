<?php
/**
 * Akeeba Engine
 *
 * @package   akeebaengine
 * @copyright Copyright (c)2006-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\MiniTest\Test;


use Akeeba\Engine\Postproc\Connector\S3v4\Connector;
use Akeeba\Engine\Postproc\Connector\S3v4\Exception\CannotPutFile;
use Akeeba\Engine\Postproc\Connector\S3v4\Input;

class ListFiles extends AbstractTest
{
	private static $paths = array(
		'listtest_one.dat',
		'listtest_two.dat',
		'listtest_three.dat',
		'list_deeper/listtest_four.dat',
		'list_deeper/listtest_five.dat',
		'list_deeper/listtest_six.dat',
		'list_deeper/listtest_deeper/seven.dat',
		'list_deeper/listtest_deeper/eight.dat',
		'spam.dat',
	);

	public static function setup(Connector $s3, array $options)
	{
		$data = self::getRandomData(self::TEN_KB);

		foreach (self::$paths as $uri)
		{
			$input = Input::createFromData($data);
			try
			{
				$s3->putObject($input, $options['bucket'], $uri);
			}
			catch (CannotPutFile $e)
			{
				// Expected for archival buckets
			}
		}
	}

	public static function teardown(Connector $s3, array $options)
	{
		foreach (self::$paths as $uri)
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
	}

	public static function testGetAll(Connector $s3, array $options)
	{
		$listing = $s3->getBucket($options['bucket'], 'listtest_');

		self::assert(is_array($listing), "The files listing must be an array");
		self::assert(count($listing) == 3, "I am expecting to see 3 files");

		// Make sure I have the expected files
		self::assert(array_key_exists('listtest_one.dat', $listing), "File listtest_one.dat not in listing");
		self::assert(array_key_exists('listtest_two.dat', $listing), "File listtest_two.dat not in listing");
		self::assert(array_key_exists('listtest_three.dat', $listing), "File listtest_three.dat not in listing");

		// I must not see the files in subdirectories
		self::assert(!array_key_exists('listtest_four.dat', $listing), "File listtest_four.dat in listing");
		self::assert(!array_key_exists('listtest_five.dat', $listing), "File listtest_five.dat in listing");
		self::assert(!array_key_exists('listtest_six.dat', $listing), "File listtest_six.dat in listing");

		// I must not see the files not matching the prefix I gave
		self::assert(!array_key_exists('spam.dat', $listing), "File spam.dat in listing");
		self::assert(!array_key_exists('ham.dat', $listing), "File ham.dat in listing");

		foreach ($listing as $fileName => $info)
		{
			self::assert(isset($info['name']), "File entries must have a name");
			self::assert(isset($info['time']), "File entries must have a time");
			self::assert(isset($info['size']), "File entries must have a size");
			self::assert(isset($info['hash']), "File entries must have a hash");
		}

		return true;
	}

	public static function testGetContinue(Connector $s3, array $options)
	{
		$listing = $s3->getBucket($options['bucket'], 'listtest_', null, 1);

		self::assert(is_array($listing), "The files listing must be an array");
		self::assert(count($listing) == 1, sprintf("I am expecting to see 1 file, %s seen", count($listing)));

		$files     = array_keys($listing);
		$continued = $s3->getBucket($options['bucket'], 'listtest_', array_shift($files));

		self::assert(is_array($continued), "The continued files listing must be an array");
		self::assert(count($continued) == 2, sprintf("I am expecting to see 2 files, %s seen", count($continued)));

		$listing = array_merge($listing, $continued);

		// Make sure I have the expected files
		self::assert(array_key_exists('listtest_one.dat', $listing), "File listtest_one.dat not in listing");
		self::assert(array_key_exists('listtest_two.dat', $listing), "File listtest_two.dat not in listing");
		self::assert(array_key_exists('listtest_three.dat', $listing), "File listtest_three.dat not in listing");

		// I must not see the files in subdirectories
		self::assert(!array_key_exists('listtest_four.dat', $listing), "File listtest_four.dat in listing");
		self::assert(!array_key_exists('listtest_five.dat', $listing), "File listtest_five.dat in listing");
		self::assert(!array_key_exists('listtest_six.dat', $listing), "File listtest_six.dat in listing");

		// I must not see the files not matching the prefix I gave
		self::assert(!array_key_exists('spam.dat', $listing), "File spam.dat in listing");
		self::assert(!array_key_exists('ham.dat', $listing), "File ham.dat in listing");

		foreach ($listing as $fileName => $info)
		{
			self::assert(isset($info['name']), "File entries must have a name");
			self::assert(isset($info['time']), "File entries must have a time");
			self::assert(isset($info['size']), "File entries must have a size");
			self::assert(isset($info['hash']), "File entries must have a hash");
		}

		return true;
	}

	// TODO Test subdirectory
	// TODO Test subdirectory with continue
	// TODO Test with continue, prefix list_deeper/listtest_, maxkeys 1 -- It will currently fail because only the common prefix is returned. Whomp.
	// TODO Test returning common prefixes using prefix list_deeper/listtest_ (reports files and folders)
}