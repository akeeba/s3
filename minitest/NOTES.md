# Testing notes

## Against Amazon S3 proper

This is the _canonical_ method for testing this library since Amazon S3 proper is the canonical provider of the S3 API (and not all of its quirks are fully documented, we might add). 

Copy `config.dist.php` to `config.php` and enter the connection information to your Amazon S3 or compatible service.

## Against [LocalStack](https://localstack.cloud)

This method is very useful for development.

Install LocalStack [as per their documentation](https://docs.localstack.cloud/getting-started/installation/).

You will also need to install [`awslocal`](https://github.com/localstack/awscli-local) like so:
```php
pip install awscli
pip install awscli-local
```

Start LocalStack e.g. `localstack start -d`

Create a new bucket called `test` i.e. `awslocal s3 mk s3://test`

Copy `config.dist.php` to `config.php` and make the following changes:
```php
    define('DEFAULT_ENDPOINT', 'localhost.localstack.cloud:4566');
    define('DEFAULT_ACCESS_KEY', 'ANYRANDOMSTRINGWILLDO');
    define('DEFAULT_SECRET_KEY', 'ThisIsAlwaysIgnoredByLocalStack');
    define('DEFAULT_REGION', 'us-east-1');
    define('DEFAULT_BUCKET', 'test');
    define('DEFAULT_SIGNATURE', 'v4');
    define('DEFAULT_PATH_ACCESS', true);
```

Note that single- and dualstack tests result in the same URLs for all S3-compatible services, including LocalStack. These tests are essentially duplicates in this use case.

## Against Wasabi

Wasabi nominally supports v4 signatures, but their implementation is actually _non-canonical_, as they expect you to NOT set the date in the string to sign. We have added a workaround especially for Wasabi, hence the need to test with it.

Just like with Amazon S3 proper, copy `config.dist.php` to `config.php` and enter the connection information to your Wasabi storage. You will also need to set up the custom endpoint like so:
```php
define('DEFAULT_ENDPOINT', 's3.eu-central-2.wasabisys.com');
```

**IMPORTANT!** The above endpoint will be different, depending on which region you've created your bucket in. The example above assumes the `eu-central-2` region. If you use the wrong region the tests _will_ fail! 