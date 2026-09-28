# Expected Amazon URLs come from Amazon, not from the code's current output

When a test asserts an Amazon S3 host name or URL, take the expected value from how Amazon actually routes it,
never from what the code produces today:

- Path-style (bucket in the path) on the global endpoint `s3.amazonaws.com` only works for us-east-1 buckets; every
  other region answers 301 PermanentRedirect. Path-style URLs need the bucket's regional endpoint
  (`s3.REGION.amazonaws.com`, `….amazonaws.com.cn` in China).
- Virtual-hosted URLs (`bucket.s3.amazonaws.com`) work for any region: DNS routes them.
- Include a non-us-east-1 region in every Amazon URL test; us-east-1 hides endpoint mistakes.

**Why:** f964fcd's PresignedUrlTest expected `https://s3.amazonaws.com/my-bucket/…` for an Amazon path-style
connection. That enshrined the bug instead of catching it; it broke Akeeba Backup's "download to browser" for
buckets outside us-east-1 until ce87df8.

**How to apply:** for a change to host names or pre-signed URLs, check the expectation against Amazon's
documentation, and confirm live against a bucket outside us-east-1 (minitest) before pushing. See also
[callers-and-compatibility.md](callers-and-compatibility.md).
