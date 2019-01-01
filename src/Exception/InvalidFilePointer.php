<?php
/**
 * Akeeba Engine
 * The PHP-only site backup engine
 *
 * @copyright Copyright (c)2006-2019 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU GPL version 3 or, at your option, any later version
 * @package   akeebaengine
 */

namespace Akeeba\Engine\Postproc\Connector\S3v4\Exception;

// Protection against direct access
defined('AKEEBAENGINE') or die();

use Exception;

class InvalidFilePointer extends \InvalidArgumentException
{
	public function __construct($message = "", $code = 0, Exception $previous = null)
	{
		if (empty($message))
		{
			$message = 'The specified file pointer is not a valid stream resource';
		}

		parent::__construct($message, $code, $previous);
	}

}
