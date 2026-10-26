<?php
/**
 * Akeeba Engine
 *
 * @package   akeebaengine
 * @copyright Copyright (c)2006-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/**
 * Runs the `integration` PHPUnit suite once against every environment defined in .env.test.
 *
 * Usage: php IntegrationTest/run.php [options] [PHPUnit arguments]
 *
 *   --list                 List the discovered environments and exit
 *   --env=A,B              Only run these environments (prefixes in .env.test, case insensitive)
 *   --quick                Skip the slow tests (big files, multipart uploads, listing thousands of files)
 *   --docker               Always start the MinIO container first and stop it afterwards. Without it the
 *                          container is started (and stopped) automatically only if a selected environment has
 *                          <PREFIX>_CONTAINER=true and the container is not already running
 *   --skip-if-unconfigured Exit successfully when there is no .env.test (used by `composer test`)
 *
 * Example: php IntegrationTest/run.php --env=C2 --filter SignedURLs
 */

use Akeeba\S3\IntegrationTest\Environment;

require_once __DIR__ . '/Environment.php';

$root       = dirname(__DIR__);
$only       = [];
$list       = $quick = $docker = $skipIfUnconfigured = false;
$phpunitArg = [];
$argv       = $_SERVER['argv'];

array_shift($argv);

while (($arg = array_shift($argv)) !== null)
{
	if ($arg === '--')
	{
		$phpunitArg = array_merge($phpunitArg, $argv);

		break;
	}

	switch (true)
	{
		case $arg === '--list':
			$list = true;
			break;

		case $arg === '--quick':
			$quick = true;
			break;

		case $arg === '--docker':
			$docker = true;
			break;

		case $arg === '--skip-if-unconfigured':
			$skipIfUnconfigured = true;
			break;

		case strpos($arg, '--env=') === 0:
			$only = array_filter(array_map('strtoupper', array_map('trim', explode(',', substr($arg, 6)))));
			break;

		default:
			// Anything else is for PHPUnit, e.g. --filter (composer drops the `--` separator)
			$phpunitArg[] = $arg;
	}
}

$environments = Environment::discover();

if ($list)
{
	echo $environments ? implode(PHP_EOL, $environments) . PHP_EOL : "No environments defined.\n";
	exit(0);
}

if (empty($environments))
{
	fwrite(STDERR, "No S3 test environments defined. Copy .env.test.dist to .env.test and edit it.\n");
	exit($skipIfUnconfigured ? 0 : 2);
}

if (!empty($only))
{
	$unknown      = array_diff($only, $environments);
	$environments = array_values(array_intersect($environments, $only));

	if ($unknown)
	{
		fwrite(STDERR, 'Unknown (or skipped) environment(s): ' . implode(', ', $unknown) . PHP_EOL);
		exit(2);
	}
}

$phpunit = is_file($root . '/vendor/bin/phpunit') ? $root . '/vendor/bin/phpunit' : 'phpunit';
$compose = 'docker compose -f ' . escapeshellarg(__DIR__ . '/docker/compose.yml');

// docker compose reads these from the process environment, so pass on the ones set in .env.test
foreach (['MINIO_IMAGE', 'MINIO_MC_IMAGE'] as $key)
{
	if (Environment::get($key) !== null)
	{
		putenv($key . '=' . Environment::get($key));
	}
}

$needsContainer = false;

foreach ($environments as $name)
{
	$needsContainer = $needsContainer || Environment::options($name)['container'];
}

// A container which is already running is the developer's; leave it running afterwards.
$wasRunning = trim((string) shell_exec($compose . ' ps -q --status running 2>/dev/null')) !== '';
$docker     = ($docker || $needsContainer) && !$wasRunning;

$results = [];

try
{
	if ($docker)
	{
		echo "▶ Starting MinIO…\n";
		passthru($compose . ' up -d --wait minio', $code);

		if ($code === 0)
		{
			passthru($compose . ' --profile init run --rm create-bucket', $code);
		}

		if ($code !== 0)
		{
			fwrite(STDERR, "Could not start MinIO.\n");
			$results['MinIO startup'] = $code;

			// The finally block below stops it
			$environments = [];
		}
	}

	foreach ($environments as $name)
	{
		echo PHP_EOL . "▶ Environment {$name}" . PHP_EOL . str_repeat('=', 80) . PHP_EOL;

		$command = array_merge(
			[$phpunit, '--configuration', $root . '/phpunit.integration.xml'],
			$quick ? ['--exclude-group', 'slow'] : [],
			$phpunitArg
		);

		$process = proc_open(
			$command, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes, $root,
			array_merge(getenv(), [Environment::SELECTOR => $name])
		);

		$results[$name] = is_resource($process) ? proc_close($process) : 255;
	}
}
finally
{
	if ($docker)
	{
		echo PHP_EOL . "▶ Stopping MinIO…\n";
		passthru($compose . ' down -v --remove-orphans');
	}
}

echo PHP_EOL . 'Summary' . PHP_EOL . str_repeat('=', 80) . PHP_EOL;

foreach ($results as $name => $code)
{
	printf("  %s %s\n", $code === 0 ? '✔' : '✘', $name);
}

exit(in_array(true, array_map(function ($c) { return $c !== 0; }, $results), true) ? 1 : 0);
