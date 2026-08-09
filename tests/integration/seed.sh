#!/usr/bin/env bash
#
# Seed the accounts the Newman collection needs.
#
# SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
# SPDX-License-Identifier: EUPL-1.2
#
# WHY A SCRIPT RATHER THAN AN INLINE COMMAND. The shared workflow runs the seed
# through `eval ${{ inputs.newman-seed-command }}` — UNQUOTED — so the outer bash
# parses whatever the value expands to and any shell metacharacter is parsed at the
# wrong level. Measured on the Playwright equivalent: `( … ) && ( … )` is a hard
# syntax error, and `sh -c '… ; …'` silently splits at the OUTER level so only part
# of it runs inside the sh -c. `bash <path>` is a single word with no
# metacharacters, so eval cannot mis-parse it.
#
# Invoked from `server/`, which is why occ is called bare.

set -euo pipefail

# The collection's `POST /api/role-feature-permissions non-admin → 403` request
# authenticates as {{regularUser}}. Without this account the variable stays
# unresolved, the request arrives with junk credentials, and the endpoint answers
# 400 (bad request) instead of 403 — so the assertion fails while testing nothing
# about authorization.
#
# Password is >= 10 chars because Nextcloud's default policy silently rejects
# shorter ones.
PASSWORD='Newman-Regular-Pw-123'

# `occ` is overridable so tests can drive this script's decision logic without
# a booted Nextcloud. It defaults to the real thing.
OCC="${OCC_CMD:-php occ}"

if OC_PASS="$PASSWORD" ${OCC} user:add --password-from-env newman-regular; then
	echo 'seeded newman-regular'
	echo 'newman seed complete'
	exit 0
fi

# `user:add` failed. The only tolerable reason is that the account is already
# there from an earlier run — ask the instance rather than trusting the exit
# code to mean one specific thing.
#
# The previous branch here said "already present (or could not be created) —
# the collection will show it" and continued. It does not show it: without this
# account the 403 assertion authenticates with unresolved credentials and the
# endpoint answers 400, so the assertion fails while testing nothing about
# authorization — which is the failure this seed exists to prevent.
if ${OCC} user:info newman-regular >/dev/null 2>&1; then
	echo 'newman-regular already present — idempotent re-run, continuing'
	echo 'newman seed complete'
	exit 0
fi

echo "FATAL: could not create 'newman-regular', and it does not exist afterwards." >&2
echo 'The collection authenticates as this user to assert a 403; without it the' >&2
echo 'request answers 400 and the assertion fails while proving nothing.' >&2
exit 1
