<?php
/**
 * Akeeba Engine
 *
 * @package   akeebaengine
 * @copyright Copyright (c)2006-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/**
 * Register class aliases for the legacy `Akeeba\Engine\Postproc\Connector\S3v4` namespace.
 */
foreach (
	[
		'\Akeeba\Engine\Postproc\Connector\S3v4\Acl'                              => \Akeeba\S3\Acl::class,
		'\Akeeba\Engine\Postproc\Connector\S3v4\Configuration'                    => \Akeeba\S3\Configuration::class,
		'\Akeeba\Engine\Postproc\Connector\S3v4\Connector'                        => \Akeeba\S3\Connector::class,
		'\Akeeba\Engine\Postproc\Connector\S3v4\Input'                            => \Akeeba\S3\Input::class,
		'\Akeeba\Engine\Postproc\Connector\S3v4\Request'                          => \Akeeba\S3\Request::class,
		'\Akeeba\Engine\Postproc\Connector\S3v4\Response'                         => \Akeeba\S3\Response::class,
		'\Akeeba\Engine\Postproc\Connector\S3v4\Signature'                        => \Akeeba\S3\Signature::class,
		'\Akeeba\Engine\Postproc\Connector\S3v4\StorageClass'                     => \Akeeba\S3\StorageClass::class,
		'\Akeeba\Engine\Postproc\Connector\S3v4\Exception\CannotDeleteFile'       => \Akeeba\S3\Exception\CannotDeleteFile::class,
		'\Akeeba\Engine\Postproc\Connector\S3v4\Exception\CannotGetBucket'        => \Akeeba\S3\Exception\CannotGetBucket::class,
		'\Akeeba\Engine\Postproc\Connector\S3v4\Exception\CannotGetFile'          => \Akeeba\S3\Exception\CannotGetFile::class,
		'\Akeeba\Engine\Postproc\Connector\S3v4\Exception\CannotListBuckets'      => \Akeeba\S3\Exception\CannotListBuckets::class,
		'\Akeeba\Engine\Postproc\Connector\S3v4\Exception\CannotOpenFileForRead'  => \Akeeba\S3\Exception\CannotOpenFileForRead::class,
		'\Akeeba\Engine\Postproc\Connector\S3v4\Exception\CannotOpenFileForWrite' => \Akeeba\S3\Exception\CannotOpenFileForWrite::class,
		'\Akeeba\Engine\Postproc\Connector\S3v4\Exception\CannotPutFile'          => \Akeeba\S3\Exception\CannotPutFile::class,
		'\Akeeba\Engine\Postproc\Connector\S3v4\Exception\ConfigurationError'     => \Akeeba\S3\Exception\ConfigurationError::class,
		'\Akeeba\Engine\Postproc\Connector\S3v4\Exception\InvalidAccessKey'       => \Akeeba\S3\Exception\InvalidAccessKey::class,
		'\Akeeba\Engine\Postproc\Connector\S3v4\Exception\InvalidBody'            => \Akeeba\S3\Exception\InvalidBody::class,
		'\Akeeba\Engine\Postproc\Connector\S3v4\Exception\InvalidEndpoint'        => \Akeeba\S3\Exception\InvalidEndpoint::class,
		'\Akeeba\Engine\Postproc\Connector\S3v4\Exception\InvalidFilePointer'     => \Akeeba\S3\Exception\InvalidFilePointer::class,
		'\Akeeba\Engine\Postproc\Connector\S3v4\Exception\InvalidRegion'          => \Akeeba\S3\Exception\InvalidRegion::class,
		'\Akeeba\Engine\Postproc\Connector\S3v4\Exception\InvalidSecretKey'       => \Akeeba\S3\Exception\InvalidSecretKey::class,
		'\Akeeba\Engine\Postproc\Connector\S3v4\Exception\InvalidSignatureMethod' => \Akeeba\S3\Exception\InvalidSignatureMethod::class,
		'\Akeeba\Engine\Postproc\Connector\S3v4\Exception\PropertyNotFound'       => \Akeeba\S3\Exception\PropertyNotFound::class,
		'\Akeeba\Engine\Postproc\Connector\S3v4\Response\Error'                   => \Akeeba\S3\Response\Error::class,
		'\Akeeba\Engine\Postproc\Connector\S3v4\Signature\V2'                     => \Akeeba\S3\Signature\V2::class,
		'\Akeeba\Engine\Postproc\Connector\S3v4\Signature\V4'                     => \Akeeba\S3\Signature\V4::class,
	] as $old => $new
)
{
	class_alias($new, $old, false);
}