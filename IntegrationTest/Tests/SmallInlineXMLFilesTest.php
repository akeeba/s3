<?php
/**
 * Akeeba Engine
 *
 * @package   akeebaengine
 * @copyright Copyright (c)2006-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\S3\IntegrationTest\Tests;

/**
 * Upload, download and delete small XML files (under 1MB) using a string source
 */
class SmallInlineXMLFilesTest extends SmallInlineFilesTest
{
	public static function fileProvider(): array
	{
		return [
			'10KB, root'          => [self::TEN_KB, 'root_10kb.xml'],
			'10KB, root, Greek'   => [self::TEN_KB, 'δοκιμή_10kb.xml'],
			'10KB, folder, Greek' => [self::TEN_KB, 'ο_φάκελός_μου/δοκιμή_10kb.xml'],
			'600KB, root'         => [self::SIX_HUNDRED_KB, 'root_600kb.xml'],
			'10KB, folder'        => [self::TEN_KB, 'my_folder/10kb.xml'],
			'600KB, folder'       => [self::SIX_HUNDRED_KB, 'my_folder/600kb.xml'],
		];
	}

	protected function createPayload(int $size): string
	{
		$out = <<< XML
<?xml version="1.0" encoding="utf-8" ?>
<root>
XML;

		$chunks = floor(($size - 55) / 1024);

		for ($i = 1; $i <= $chunks; $i++)
		{
			$randomBlock = $this->randomAlphanumeric(1024 - 63);
			$out         .= <<< XML
		<element>
			<id>$i</id>
			<data><![CDATA[$randomBlock]]></data>
		</element>
XML;
		}

		$out .= <<< XML
</root>
XML;

		return $out;
	}

	private function randomAlphanumeric(int $length): string
	{
		$chars     = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ01234567890';
		$maxLength = strlen($chars) - 1;
		$salt      = '';

		for ($i = 0; $i < $length; $i++)
		{
			$salt .= $chars[random_int(0, $maxLength)];
		}

		return $salt;
	}
}
