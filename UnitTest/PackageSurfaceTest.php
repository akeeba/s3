<?php
/**
 * Akeeba Engine
 *
 * @package   akeebaengine
 * @copyright Copyright (c)2006-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\S3\UnitTest;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Development files stay out of what applications install.
 *
 * Composer installs this library from the GitHub archive, which honours .gitattributes export-ignore; a
 * `composer archive` honours composer.json's archive excludes. Applications bundling the library would
 * otherwise ship IntegrationTest/, an executable harness which runs S3 tests against real buckets once a
 * .env.test sits in the repository root.
 */
class PackageSurfaceTest extends TestCase
{
	/**
	 * @return array<string, array{0: string}>
	 */
	public static function provideDevelopmentPaths(): array
	{
		return [
			'integration tests'       => ['IntegrationTest'],
			'integration PHPUnit cfg' => ['phpunit.integration.xml'],
			'integration env sample'  => ['.env.test.dist'],
			'testing instructions'    => ['TESTING.md'],
			'Phing build file'        => ['build.xml'],
			'Rector config'           => ['rector.php'],
			'unit tests'              => ['UnitTest'],
			'PHPUnit config'          => ['phpunit.xml'],
			'agent notes'             => ['AGENTS.md'],
			'project memory'          => ['.claude'],
		];
	}

	#[DataProvider('provideDevelopmentPaths')]
	public function testDevelopmentFilesAreNotInTheGitArchive(string $path): void
	{
		$root = \dirname(__DIR__);

		if (!is_dir($root . '/.git'))
		{
			$this->markTestSkipped('Not a Git checkout.');
		}

		$output = shell_exec(
			'git -C ' . escapeshellarg($root) . ' check-attr export-ignore -- ' . escapeshellarg($path) . ' 2>/dev/null'
		);

		$this->assertIsString($output, 'Git is not available.');
		$this->assertStringEndsWith(': export-ignore: set', trim($output));
	}

	#[DataProvider('provideDevelopmentPaths')]
	public function testDevelopmentFilesAreNotInAComposerArchive(string $path): void
	{
		$composer = json_decode(file_get_contents(\dirname(__DIR__) . '/composer.json'), true);
		$excluded = array_map(fn(string $entry) => ltrim($entry, '/'), $composer['archive']['exclude'] ?? []);

		$this->assertContains($path, $excluded);
	}
}
