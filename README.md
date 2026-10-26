# Akeeba Amazon S3 Connector

A compact, dependency-less Amazon S3 API client implementing the most commonly used features.

This library is designed to work with Amazon S3 proper, as well as S3-compatible services such as but not limited to Wasabi, Google Storage, Synology C2, ExoScale etc.

## Quick example

This is just to give you an idea of what's possible, and how easy it is to use this library.

You can also list buckets, list "subdirectories" in a bucket, upload from files, strings, and streams, use multi-part (resumable) uploads, download to files, create pre-signed URLs, and test if an object exists (HEAD request). Read [the documentation](docs/using.md) for all possibilities.

As far as configuration goes, you can use S3-compatible services with v2 or v4 signatures, custom endpoints, path-style or subdomain access, use Amazon S3's Dual Stack support, use an HTTP `Date` header instead of `X-Amz-Date`, use alternate date formats, set up timeouts, and get debug messages. Read [the configuration documentation](docs/config.md) for more information.

```php
// Necessary define (library files have protection against direct web access based on this constant's presence).
defined('AKEEBAENGINE') or define('AKEEBAENGINE', 1);

// Create a configuration object
$configuration = new \Akeeba\S3\Configuration(
	'YourAmazonAccessKey',
	'YourAmazonSecretKey'
);

// Get the connect
$connector = new \Akeeba\S3\Connector($configuration);

// Upload a file
$input = \Akeeba\S3\Input::createFromFile($sourceFile);   
$connector->putObject($input, 'mybucket', 'path/to/myfile.txt');

// List files in the target "directory"
$listing = $connector->getBucket('mybucket', 'path/to/');

// Download to a string
$content = $connector->getObject('mybucket', 'path/to/myfile.txt', false);

// Delete a file
$connector->deleteObject('mybucket', 'path/to/myfile.txt');
```

## Documentation

* [Why it exists](docs/why.md)
* [Breaking changes](docs/breaking_changes.md)
* [Using the library](docs/using.md)
* [Configuration options](docs/config.md)
* [Automated testing](TESTING.md)
* [Regulatory notices](docs/regulatory.md)
* [License](LICENSE.md)

## Footnote

Developing and maintaining software is neither easy nor free. The development of this software is subsidised by sales of our commercial offerings. If you like this software and would like to see it maintained in the future, please consider [purchasing a subscription](https://www.akeeba.com/subscribe.html) to one of our commercial offerings. _Thank you!_
