<?php


namespace Akeeba\MiniTest\Test;


use Akeeba\Engine\Postproc\Connector\S3v4\Connector;
use RuntimeException;

class BucketLocation extends AbstractTest
{
	public static function getBucketLocation(Connector $s3, array $options)
	{
		$location = $s3->getBucketLocation($options['bucket']);

		self::assert($location === $options['region'], "Bucket ‘{$options['bucket']}′ reports being in region ‘{$location}′ instead of expected ‘{$options['region']}′");

		return true;
	}
}