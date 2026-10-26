<?php
/**
 * Akeeba Engine
 *
 * @package   akeebaengine
 * @copyright Copyright (c)2006-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Integration test bootstrap: the library's Composer autoloader and the test support classes. Unlike the unit test
// bootstrap it does NOT load CurlRecorder, because these tests must really talk to an S3 service.
if (!file_exists(__DIR__ . '/../vendor/autoload.php'))
{
	fwrite(STDERR, "Run `composer install` first.\n");

	exit(1);
}

// Every library file refuses to load (silently) without this.
defined('AKEEBAENGINE') || define('AKEEBAENGINE', 1);

require_once __DIR__ . '/../vendor/autoload.php';

spl_autoload_register(
	function (string $class): void {
		$prefix = 'Akeeba\\S3\\IntegrationTest\\';

		if (strpos($class, $prefix) !== 0)
		{
			return;
		}

		$file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

		if (is_file($file))
		{
			require_once $file;
		}
	}
);
