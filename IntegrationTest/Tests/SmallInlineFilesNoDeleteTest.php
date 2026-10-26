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
 * Upload and download small files (under 1MB) using a string source, without deleting them (archival buckets)
 */
class SmallInlineFilesNoDeleteTest extends SmallInlineFilesTest
{
	protected const REQUIRED_PERMISSIONS = 'no-delete';

	protected $deleteRemote = false;
}
