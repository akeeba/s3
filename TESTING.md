# Automated testing

There are two levels of automated tests we use when developing the library.

## Unit tests

**Unit tests** test the implementation of the library's internals against the way we intend them to work. They are used primarily to prevent regressions, but they do not really give us any information about how the library performs in the real world, against real storage providers. They take a second or two to run.

Run with `composer test:unit` (or just `phpunit`).

The unit tests are implemented with PHPUnit 11. The unit test suite is in the `phpunit.xml` file. The test classes are under `UnitTest/`. 

The unit test suite requires no network and no S3 account. The `UnitTest/CurlRecorder.php` class records the cURL options the library sets, without connecting anywhere.

## Integration tests

**Integration tests** exercise the library's features against real-world storage provider implementations. These are the tests which incover the subtle inconsistencies between documented behaviour and the real world, or how various S3-compatible implementations are subtly incompatible with Amazon S3 proper in some cases. They take several minutes per provider to run, depending on network conditions.

The integration tests are implemented with PHPUnit 11. The integration test suite is in the `phpunit.integration.xml` file. The test classes are under `IntegrationTest/Tests/`.

> [!NOTE]
> The integration tests suite replaces our previous, custom `minitest` harness.

### Commands

| Command | What it does |
|---|---|
| `composer test:integration` | Runs the whole suite once against **each** environment in `.env.test` |
| `composer test:integration -- --env=C2,WASABI` | Only these environments |
| `composer test:integration -- --quick` | Skips the slow tests (big files, multipart, 2100-file listings) |
| `composer test:integration -- --no-big-files` | Skips only the big file tests (likewise `--no-multipart`, `--no-2100`) |
| `composer test:integration -- --filter SignedURLs` | Anything unknown is handed to PHPUnit |
| `composer test:integration -- --list` | Lists the discovered environments |
| `composer test:garage` | Starts Garage in Docker, runs the suite against `GARAGE`, removes the container |
| `composer garage:up` / `garage:down` | Start / remove the container yourself |
| `composer test` / `phing test` | Unit tests, then the integration tests (a notice only without `.env.test`) |

> [!NOTE]
> The `test:integration` and `test:garage` scripts disable Composer's 300-second `process-timeout`, which would
> otherwise kill the runner halfway through a long run against live services (leaving PHPUnit orphaned in the
> background, still printing). To bypass Composer entirely, run `php IntegrationTest/run.php [options]` — it takes
> the same options as the table above.

> [!WARNING]
> A full run against several real services can take _hours_. Just running `SignedURLs` (`--filter SignedURLs`) is a quick sanity check across services to ascertain compatibility. The rest should run overnight / in a CI environment.

### Environments

The S3-compatible services the library is exercised against are configured in the `.env.test` environment file. 

Copy `.env.test.dist` to `.env.test` (git-ignored; never commit credentials). Every `<PREFIX>_ACCESS_KEY` (with a
`<PREFIX>_BUCKET`) defines an environment, e.g. `DEFAULT_`, `C2_`, `WASABI_`, `GARAGE_`. The settings are listed
in the sample. Real environment variables override the file, so CI can inject credentials without a file.

To run PHPUnit by hand against one environment: `S3_TEST_ENV=C2 phpunit -c phpunit.integration.xml`. Alternatively, use `composer test:integration -- --env=C2`.

`<PREFIX>_PERMISSIONS` says what the key pair may do: `full` (default) runs the standard classes; `no-delete`
(archival buckets) runs only the `*NoDelete*` classes; `write-only` runs only the `*OnlyUpload*` classes. Other
classes skip themselves.

### Default test targets

At a minimum, we recommend testing against the following services.

#### Amazon S3

Amazon S3 proper is the canonical target.

During the development and testing of this library, we found that Amazon's documentation has minor errors and inconsistencies compared to how S3 _actually_ works. When the documentation disagrees with the observed behaviourm we consider the observed real-world behaviour the canonical implementation target.

We also note that the observed behaviour may be different depending on the region. `us-east-1` is the default region, allowing you to list buckets from other regions but not use them without then switching to the bucket's region. Newer regions which only support v4 signatures (e.g. `eu-central-1`) will of course refuse to work with v2 signatures. If you are running your own tests take these differences into account.

#### Garage

[Garage](https://garagehq.deuxfleurs.fr) runs in Docker and is good for development and CI: it's free, fast, and requires no account on any third party service. It replaced MinIO, which stopped publishing container images.

We treat Garage merely as an emulation, not as a reference implementation. Where it differs from Amazon S3 proper, Amazon is right. That said, it's our default environment to test features in development. The full integration suite against multiple real-world services only runs when we are considering a release, when we address a service-specific quirk, or when we suspect a change may exhibit different behaviour across different providers.

The Garage container is set up in the `IntegrationTest/docker/compose.yml` file (configuration in `garage.toml` next to it), with the image pinned to a specific release; override with `GARAGE_IMAGE`. The Garage image has no shell, so `IntegrationTest/docker/provision.sh` (needs bash) lays out the node's storage, imports the access key and creates and authorises the bucket `test`, through `docker compose exec`; `composer garage:up` and the runner both do that for you. The access key, secret and bucket come from `GARAGE_ACCESS_KEY`, `GARAGE_SECRET_KEY` and `GARAGE_BUCKET` (Garage requires an access key of `GK` and 24 hex digits, and a secret of 64 hex digits).

Only path-style access works over plain HTTP at the default bound address `127.0.0.1:3900` (not `localhost`, which resolves to `::1` first; Docker does not publish that and the connection hangs), so `GARAGE_VIRTUAL_HOSTS=false` skips the one test needing `bucket.host` names. Change the host port with `GARAGE_PORT` and `GARAGE_ENDPOINT`. Garage accepts every storage class, so there is no `GARAGE_STORAGE_CLASSES` restriction.

> [!WARNING]
> Garage supports **only v4 signatures** (v2 requests are refused with "Unsupported authorization method"). No server we can run locally supports v2 signatures any more, so there is no Docker environment for them (there used to be one, `MINIO_V2`).
>
> What covers v2 signing now:
>
> - The unit tests cover only part of it: `UnitTest/V2ResponseOverridesTest.php` checks the string-to-sign and signature of a v2 pre-signed URL with `response-*` overrides, and `PresignedUrlTest.php` / `RequestTlsTest.php` check the shape of v2 URLs and host names. Nothing in the unit suite checks the v2 `Authorization` header (the canonicalised string-to-sign of regular requests) against known values.
> - Live v2 requests are only exercised against a real third party service which still accepts them, if you define one in `.env.test` with `<PREFIX>_SIGNATURE=v2` (and an empty `<PREFIX>_REGION`). Amazon S3 itself only accepts v2 in its oldest regions, e.g. `us-east-1`.
>
> If you touch `src/Signature/V2.php`, run the integration suite against such a service before releasing.
