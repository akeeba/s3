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
use PHPUnit\Framework\Attributes\DataProvider;

class SignedURLsTest extends IntegrationTestCase
{
	public static function aclProvider(): array
	{
		return [
			'Public object'                 => [Acl::ACL_PUBLIC_READ, false],
			'Public object, with spaces'    => [Acl::ACL_PUBLIC_READ, true],
			'Private object'                => [Acl::ACL_PRIVATE, false],
			'Private object, with spaces'   => [Acl::ACL_PRIVATE, true],
		];
	}

	#[DataProvider('aclProvider')]
	public function testSignedURL(string $acl, bool $spaces): void
	{
		$bucket   = $this->options['bucket'];
		$tempData = self::getRandomData(self::TEN_KB);
		$uri      = ($spaces ? 'test file' : 'test') . '.' . hash('md5', uniqid('', true)) . '.dat';

		try
		{
			$this->s3->putObject(Input::createFromData($tempData), $bucket, $uri, $acl);

			$downloadURL    = $this->s3->getAuthenticatedURL($bucket, $uri, null, $this->options['ssl']);
			$downloadedData = @file_get_contents($downloadURL);
		}
		finally
		{
			$this->deleteQuietly($bucket, $uri);
		}

		// Never print the URL: it carries a valid signature.
		$this->assertNotFalse($downloadedData, 'Failed to download from the signed URL');
		$this->assertSame($tempData, $downloadedData, 'Wrong data received from the signed URL');
	}
}
