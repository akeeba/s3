# Who depends on this library's behaviour

Before changing what a `Configuration` setter or a `Connector` method does (not only its signature), check the
callers, relative to the common projects root:

- **`akeeba/engine2`** (Akeeba Backup, `engine/Postproc/Amazons3.php`): builds the configuration, then for a custom
  endpoint calls `setEndpoint()`, `setSignatureMethod()` and `setRegion()` again, in that order. It also sets
  `setPreSignedBucketInURL()` from its own option.
- **`j4-akeeba/s3filesystem`** (plg_filesystem_s3): `setEndpoint()` then `setSignatureMethod()`, region from the
  constructor; `setDebug()` from Joomla's Site Debug. It has drift tests that record the real cURL options and
  headers this library produces.
- **`akeeba/kickstart`** bundles its own, separate copy of the S3 classes (`source/s3/`); changes here do not reach it.

Semantics that changed in 2026-09, which old callers may still work around: `setEndpoint()` no longer switches to
v2 or empties the region; a path-style configuration keeps the bucket in the path of v4 pre-signed URLs; the
"Debug info" dump is off unless `setDebug(true)`. `setSignatureMethod('v2')` still empties the region.

**Why:** the operator asked for these library-level fixes after checking that no caller relied on the old side
effects; the next change needs the same check.

**How to apply:** grep the callers above for the method you are changing, and say in the commit message which of
them are affected. See also [tls-host-verification.md](tls-host-verification.md).
