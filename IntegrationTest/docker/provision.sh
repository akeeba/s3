#!/usr/bin/env bash
# Provision the freshly started Garage container of compose.yml: a storage layout, an access key and a bucket.
#
#   IntegrationTest/docker/provision.sh
#
# Garage is not usable straight after it starts, and its image contains nothing but the `garage` binary (no
# shell), so every step runs through `docker compose exec`. The key is IMPORTED, so its credentials are fixed and
# can be written into .env.test up front. Garage insists on its own formats: the access key is `GK` followed by
# 24 hex digits, the secret 64 hex digits. Every step is idempotent. Needs bash and docker compose.
#
# Settings, from the environment: GARAGE_ACCESS_KEY, GARAGE_SECRET_KEY, GARAGE_BUCKET (the defaults match the
# GARAGE environment of .env.test.dist).
set -euo pipefail

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
key="${GARAGE_ACCESS_KEY:-GK0123456789abcdef01234567}"
secret="${GARAGE_SECRET_KEY:-0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef}"
bucket="${GARAGE_BUCKET:-test}"

garage_cli() { docker compose -f "${here}/compose.yml" exec -T garage /garage "$@"; }
die() { echo "$*" >&2; exit 1; }

# Wait for the node to answer RPC.
tries=0
until status="$(garage_cli status 2>/dev/null)" && [ -n "${status}" ]; do
	tries=$((tries + 1))
	[ "${tries}" -gt 60 ] && die "Garage did not start answering in time."
	sleep 1
done

# A node with no role has no storage. Assign it one, once.
if printf '%s' "${status}" | grep -q 'NO ROLE ASSIGNED'; then
	node="$(printf '%s\n' "${status}" | awk '/NO ROLE ASSIGNED/ { print $1; exit }')"
	garage_cli layout assign -z dc1 -c 1G "${node}" >/dev/null 2>&1 || die "Could not assign a Garage storage layout."
	garage_cli layout apply --version 1 >/dev/null 2>&1 || die "Could not apply the Garage storage layout."
fi

# `key import` fails if the key exists, which is the idempotent case.
garage_cli key info "${key}" >/dev/null 2>&1 \
	|| garage_cli key import --yes -n akeeba-s3-tests "${key}" "${secret}" >/dev/null 2>&1 \
	|| die "Could not import the Garage access key (GK + 24 hex digits, secret 64 hex digits)."

garage_cli bucket info "${bucket}" >/dev/null 2>&1 \
	|| garage_cli bucket create "${bucket}" >/dev/null 2>&1 \
	|| die "Could not create the Garage bucket '${bucket}'."
garage_cli bucket allow --read --write --owner "${bucket}" --key akeeba-s3-tests >/dev/null 2>&1 \
	|| die "Could not grant the key access to the Garage bucket '${bucket}'."

echo "Garage is ready: bucket '${bucket}', access key ${key}."
