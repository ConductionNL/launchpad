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

if OC_PASS="$PASSWORD" php occ user:add --password-from-env newman-regular; then
	echo 'seeded newman-regular'
else
	echo 'newman-regular already present (or could not be created) — the collection will show it'
fi

echo 'newman seed complete'
