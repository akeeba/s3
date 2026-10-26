<?php
/**
 * Akeeba Engine
 *
 * @package   akeebaengine
 * @copyright Copyright (c)2006-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\S3\IntegrationTest\Tests;

use PHPUnit\Framework\Attributes\Group;

/**
 * Same as BigFilesTest, but with multipart (chunked) uploads, also checking the number of chunks
 */
#[Group('slow')]
class MultipartTest extends BigFilesTest
{
	protected $multipart = true;
}
