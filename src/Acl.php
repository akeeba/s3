<?php
/**
 * Akeeba Engine
 * The PHP-only site backup engine
 *
 * @copyright Copyright (c)2006-2019 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU GPL version 3 or, at your option, any later version
 * @package   akeebaengine
 */

namespace Akeeba\Engine\Postproc\Connector\S3v4;

// Protection against direct access
defined('AKEEBAENGINE') or die();

/**
 * Shortcuts to often used access control privileges
 */
class Acl
{
	const ACL_PRIVATE = 'private';

	const ACL_PUBLIC_READ = 'public-read';

	const ACL_PUBLIC_READ_WRITE = 'public-read-write';

	const ACL_AUTHENTICATED_READ = 'authenticated-read';

	const ACL_BUCKET_OWNER_READ = 'bucket-owner-read';

	const ACL_BUCKET_OWNER_FULL_CONTROL = 'bucket-owner-full-control';
}
