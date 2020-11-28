<?php
/**
 * Akeeba Engine
 *
 * @package   akeebaengine
 * @copyright Copyright (c)2006-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Default Amazon S3 Access Key
define('DEFAULT_ACCESS_KEY', 'your s3 access key');
// Default Amazon S3 Secret Key
define('DEFAULT_SECRET_KEY', 'your secret key');
// Default region for the bucket
define('DEFAULT_REGION', 'us-east-1');
// Default bucket name
define('DEFAULT_BUCKET', 'example');
// Default signature method (v4 or v2)
define('DEFAULT_SIGNATURE', 'v4');
// Use Dualstack unless otherwise specified?
define('DEFAULT_DUALSTACK', false);
// Use legacy path access by default?
define('DEFAULT_PATH_ACCESS', false);

/**
 * These are the individual test configurations.
 *
 * Each configuration consists of two keys:
 *
 * * **configuration**  Overrides to the default configuration.
 * * **tests**          The names of the test classes to execute. Use the format ['classname', 'method'] to execute
 *                      specific test methods only.
 */
$testConfigurations = array(
	'Description of this configuration' => array(
		'configuration' => array(
			// You can skip one or more keys. The defaults will be used.
			'access' => 'a different access key',
			'secret' => 'a different secret key',
			'region' => 'eu-west-1',
			'bucket' => 'different_example',
			'signature' => 'v2',
			'dualstack' => true,
			'path_access' => true,
		),
		'tests' => array(
			''
		)
	),
	// ...repeat as necessary
);