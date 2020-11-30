<?php
/**
 * Akeeba Engine
 *
 * @package   akeebaengine
 * @copyright Copyright (c)2006-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\MiniTest\Test;


/**
 * Upload small files (under 1MB) using a file source
 *
 * @package Akeeba\MiniTest\Test
 */
class SmallFilesOnlyUpload extends SmallFiles
{
	protected static $deleteRemote = false;
	protected static $downloadAfter = false;
}