# TLS host name verification for Amazon S3

`Request::getResponse()` turns `CURLOPT_SSL_VERIFYHOST` off only for Amazon hosts with more dots than the
endpoint itself contributes: 4 for `bucket.s3.REGION.amazonaws.com`, plus 1 when the `dualstack` option is
on, plus 1 for the China endpoints (`….amazonaws.com.cn`).

- Decide by counting the dots of the **whole host name**, adjusted for the endpoint shape. Do **not** switch
  to checking only whether the bucket name contains a dot.
- Never fall back to path-style access by default to avoid the problem. Path-style access is a
  user-selected option (`Configuration::setUseLegacyPathStyle()`), nothing else.

**Why:** the maintainer's decision when fixing plg_filesystem_s3 security finding M3 (dual-stack hosts had
verification turned off for every bucket). The maintainer has reasons of their own for both rules; do not
re-propose the alternatives.

**How to apply:** when a new endpoint shape adds labels to the host, raise the dot allowance for that shape
and add a case to `UnitTest/RequestTlsTest.php`, including a dotted-bucket case that must still turn
verification off.
