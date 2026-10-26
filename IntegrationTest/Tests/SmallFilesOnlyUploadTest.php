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
 * Upload small files (under 1MB) using a file source, with write-only credentials
 */
class SmallFilesOnlyUploadTest extends SmallFilesTest
{
	protected const REQUIRED_PERMISSIONS = 'write-only';

	protected $deleteRemote = false;

	protected $downloadAfter = false;
}
