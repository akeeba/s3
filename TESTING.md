# Testing

## Unit tests

`composer test:unit` (or `phpunit`). PHPUnit 11 with `phpunit.xml`, tests in `UnitTest/`. No network, no S3 account.
`UnitTest/CurlRecorder.php` records the cURL options the library sets, without connecting anywhere.

## Integration tests

PHPUnit suite (`integration`, `phpunit.integration.xml`, tests in `IntegrationTest/Tests/`) which exercises the
library against **real** S3 and S3-compatible services. It replaces the old `minitest` harness.

### Commands

| Command | What it does |
|---|---|
| `composer test:integration` | Runs the whole suite once against **each** environment in `.env.test` |
| `composer test:integration -- --env=C2,WASABI` | Only these environments |
| `composer test:integration -- --quick` | Skips the slow tests (big files, multipart, 2100-file listings) |
| `composer test:integration -- --filter SignedURLs` | Anything unknown is handed to PHPUnit |
| `composer test:integration -- --list` | Lists the discovered environments |
| `composer test:minio` | Starts MinIO in Docker, runs the suite against `MINIO`, removes the container |
| `composer minio:up` / `minio:down` | Start / remove the container yourself |
| `composer test` / `phing test` | Unit tests, then the integration tests (a notice only without `.env.test`) |

⚠️ A full run against several real services takes _hours_. `SignedURLs` (`--filter`) is a quick sanity check across
services; run the rest overnight.

### Environments: `.env.test`

Copy `.env.test.dist` to `.env.test` (git-ignored; never commit credentials). Every `<PREFIX>_ACCESS_KEY` (with a
`<PREFIX>_BUCKET`) defines an environment, e.g. `DEFAULT_`, `C2_`, `WASABI_`, `MINIO_`. The settings are listed
in the sample. Real environment variables override the file, so CI can inject credentials without a file.

To run PHPUnit by hand against one environment: `S3_TEST_ENV=C2 phpunit -c phpunit.integration.xml`.

`<PREFIX>_PERMISSIONS` says what the key pair may do: `full` (default) runs the standard classes; `no-delete`
(archival buckets) runs only the `*NoDelete*` classes; `write-only` runs only the `*OnlyUpload*` classes. Other
classes skip themselves.

### Notes per service

* **Amazon S3 proper** is the canonical target: not all of its quirks are documented. Test several regions
  (us-east-1, eu-west-1, a v4-only region such as eu-central-1).
* **Wasabi** nominally supports v4 signatures but reads the date only from `x-amz-date`; the library therefore always
  sends it. Endpoint, region and the bucket's region must match, or the tests fail.
* **Synology C2** implements Amazon's _documentation_ of v4 dates, not what Amazon does; the library works around it.
  The region is the part of the endpoint before `.s3.` (`eu-002`), which they do not document.
* **MinIO** runs in Docker (`IntegrationTest/docker/compose.yml`, image pinned to a release; override with
  `MINIO_IMAGE`) and is good for development and CI: free, fast, no account. The bucket `test` is created by a
  one-shot `mc` container. Only path-style access works over plain HTTP at `localhost:9000`, so
  `MINIO_VIRTUAL_HOSTS=false` skips the one test needing `bucket.host` names. It is an emulation: where it differs
  from Amazon S3, Amazon is right (see `.claude/memory/amazon-url-expectations.md`).
