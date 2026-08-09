#!/usr/bin/env bash
#
# Seed the accounts the Playwright suite needs.
#
# SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
# SPDX-License-Identifier: EUPL-1.2
#
# WHY A SCRIPT RATHER THAN AN INLINE COMMAND. The shared workflow runs the seed
# through `eval ${{ inputs.playwright-seed-command }}` — UNQUOTED — so the outer
# bash parses whatever the value expands to, and any shell metacharacter is
# parsed at the wrong level. `bash <path>` is a single word with no
# metacharacters, so eval cannot mis-parse it. This mirrors
# tests/integration/seed.sh, which documents the same constraint.
#
# WHAT THIS REPLACES, AND WHY IT IS NOT JUST `|| true`.
#
# This seed used to be an inline `… php occ user:add … || true`. The `|| true`
# was there for a real reason — the suite must survive a re-run against a warm
# instance where the account already exists — but it does not say "tolerate an
# account that already exists", it says "tolerate ANYTHING". A wrong password
# policy, a missing occ, a broken database, a typo in the user id: all of them
# exited 0 and let Playwright start against an instance with no grantee. The
# grant spec then authenticates as a user that does not exist and fails for a
# reason that has nothing to do with the code under test — or, worse, silently
# tests less than it appears to.
#
# So the tolerance is kept, and narrowed to the one postcondition that actually
# matters: after this script, the account EXISTS. `user:add` failing is not
# itself an error; `user:add` failing while the account is still absent is.
# That question is answered by asking the instance, not by parsing an error
# message (which is localised and version-dependent).
#
# Invoked from `server/`, which is why occ is called bare.

set -euo pipefail

# The grant spec needs a second, non-admin account to be the share recipient;
# a grant to yourself proves nothing.
readonly SEED_UID='e2e-grantee'
# >= 10 chars: Nextcloud's default password policy silently rejects shorter
# ones, which would leave the account uncreated with a success-looking log.
readonly SEED_PASSWORD='E2eGranteePw123'

# `occ` is overridable so the accompanying test can drive this script's
# decision logic without a booted Nextcloud. It defaults to the real thing.
OCC="${OCC_CMD:-php occ}"

if OC_PASS="${SEED_PASSWORD}" ${OCC} user:add --password-from-env "${SEED_UID}"; then
	echo "seeded ${SEED_UID}"
	exit 0
fi

# `user:add` failed. The ONLY tolerable reason is that the account is already
# there from an earlier run. Ask the instance rather than trusting the exit
# code to mean one specific thing.
if ${OCC} user:info "${SEED_UID}" >/dev/null 2>&1; then
	echo "${SEED_UID} already present — idempotent re-run, continuing"
	exit 0
fi

echo "FATAL: could not create '${SEED_UID}', and it does not exist afterwards." >&2
echo "The Playwright grant spec needs this account; starting the suite without" >&2
echo "it would fail for a reason unrelated to the code under test." >&2
exit 1
