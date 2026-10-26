<?php
/**
 * Akeeba Engine
 *
 * @package   akeebaengine
 * @copyright Copyright (c)2006-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\S3\IntegrationTest;

use Akeeba\S3\Configuration;
use Akeeba\S3\Connector;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Base class of the integration tests. They run against a real S3 or S3-compatible service, selected by the
 * S3_TEST_ENV variable (the IntegrationTest/run.php runner sets it once per environment in .env.test).
 */
abstract class IntegrationTestCase extends TestCase
{
	public const TEN_KB = 10240;

	public const SIX_HUNDRED_KB = 614400;

	public const ONE_MB = 1048576;

	public const FIVE_MB = 5242880;

	public const SIX_MB = 6291456;

	public const TEN_MB = 10485760;

	public const ELEVEN_MB = 11534336;

	public const BLOCK_SIZE = 1048576;

	/**
	 * What the environment's key pair must be allowed to do for this class to make sense: `full` (read, write,
	 * delete), `no-delete` (archival buckets) or `write-only`. Other environments skip the class.
	 */
	protected const REQUIRED_PERMISSIONS = 'full';

	/** @var Connector */
	protected $s3;

	/** @var array<string,mixed> */
	protected $options;

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		$name = Environment::selected();

		if ($name === null)
		{
			self::markTestSkipped(
				'No S3 test environment selected. Define one in .env.test (see .env.test.dist) and run `composer test:integration`.'
			);
		}

		$options = Environment::options($name);

		if ($options['permissions'] !== static::REQUIRED_PERMISSIONS)
		{
			self::markTestSkipped(
				sprintf(
					'Environment %s has %s permissions; this class needs %s',
					$name, $options['permissions'], static::REQUIRED_PERMISSIONS
				)
			);
		}
	}

	protected function setUp(): void
	{
		parent::setUp();

		$this->options = static::environmentOptions();
		$this->s3      = static::createConnector($this->options);
	}

	/**
	 * Options of the selected environment. Usable from setUpBeforeClass / tearDownAfterClass.
	 */
	protected static function environmentOptions(): array
	{
		return Environment::options((string) Environment::selected());
	}

	/**
	 * Builds a connector the way Akeeba Backup does: the endpoint first, then the signature method and region again.
	 */
	protected static function createConnector(array $options): Connector
	{
		$configuration = new Configuration($options['access'], $options['secret'], $options['signature'], $options['region']);
		$configuration->setRegion($options['region']);
		$configuration->setSignatureMethod($options['signature']);

		if (!empty($options['endpoint']))
		{
			$configuration->setEndpoint($options['endpoint']);
			// Setting the endpoint may reset these
			$configuration->setRegion($options['region']);
			$configuration->setSignatureMethod($options['signature']);
		}

		$configuration->setUseDualstackUrl($options['dualstack']);
		$configuration->setUseLegacyPathStyle($options['path_access']);
		$configuration->setSSL($options['ssl']);
		$configuration->setAlternateDateHeaderFormat($options['alternateDateHeaderFormat']);
		$configuration->setUseHTTPDateHeader($options['useHTTPDateHeader']);
		$configuration->setPreSignedBucketInURL($options['preSignedBucketInURL']);

		return new Connector($configuration);
	}

	/**
	 * Randomises a key. Required for archive buckets where you cannot overwrite data.
	 */
	protected static function randomizeUri(string $uri): string
	{
		$dotPos = strrpos($uri, '.');

		return substr($uri, 0, $dotPos) . '.' . hash('md5', uniqid('', true)) . substr($uri, $dotPos);
	}

	/**
	 * Creates a file with random data and returns its path. The caller must unlink it.
	 */
	protected static function createFile(int $size = self::SIX_HUNDRED_KB): string
	{
		$tempFilePath = tempnam(sys_get_temp_dir(), 'as3');

		if ($tempFilePath === false || ($fp = @fopen($tempFilePath, 'w')) === false)
		{
			throw new RuntimeException('Cannot create a temporary file.');
		}

		$lastBlockSize = $size % self::BLOCK_SIZE;
		$wholeBlocks   = intdiv($size - $lastBlockSize, self::BLOCK_SIZE);
		$blockData     = static::getRandomData();

		for ($i = 0; $i < $wholeBlocks; $i++)
		{
			fwrite($fp, $blockData);
		}

		if ($lastBlockSize > 0)
		{
			fwrite($fp, $blockData, $lastBlockSize);
		}

		fclose($fp);

		return $tempFilePath;
	}

	/**
	 * Returns a path for a temporary download target. The caller must unlink it.
	 */
	protected static function tempPath(): string
	{
		$path = tempnam(sys_get_temp_dir(), 'as3');

		if ($path === false)
		{
			throw new RuntimeException('Cannot create a temporary file.');
		}

		return $path;
	}

	protected static function getRandomData(int $length = self::BLOCK_SIZE): string
	{
		return random_bytes($length);
	}

	/**
	 * Deletes a remote object, ignoring failures (cleanup must not mask the real test failure).
	 */
	protected function deleteQuietly(string $bucket, string $uri): void
	{
		try
		{
			$this->s3->deleteObject($bucket, $uri);
		}
		catch (\Exception $e)
		{
			// Nothing to do
		}
	}
}
