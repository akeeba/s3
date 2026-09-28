<?php
/**
 * Akeeba Engine
 *
 * @package   akeebaengine
 * @copyright Copyright (c)2006-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Unit test bootstrap: the library's own Composer autoloader, plus the cURL recorder.
if (!file_exists(__DIR__ . '/../vendor/autoload.php'))
{
	fwrite(STDERR, "Run `composer install` first.\n");

	exit(1);
}

// Every library file refuses to load (silently) without this.
defined('AKEEBAENGINE') || define('AKEEBAENGINE', 1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/CurlRecorder.php';
