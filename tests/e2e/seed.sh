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
# The accounts the suite needs, as `uid:password` pairs.
#
#   e2e-grantee — the object-grant recipient (manifest-grants spec).
#   recipient   — the share target for dashboard-sharing.spec.ts. That spec
#                 reads LAUNCHPAD_E2E_SHAREE (default `recipient`) and resets
#                 the password itself via the OCS provisioning API before the
#                 recipient-side scenario logs in, so what it needs from here
#                 is only that the ACCOUNT EXISTS. Without it the whole file
#                 was excluded from the job — 4 of 4 failing for want of a
#                 second user to be.
readonly SEED_ACCOUNTS=(
	'e2e-grantee:E2eGranteePw123'
	'recipient:Recipient-e2e-A1!'
)

OCC="${OCC_CMD:-php occ}"

# Exit non-zero if ANY account could not be seeded. Each account is handled
# independently so one already-present account never masks a real failure on
# another.
# Disable Nextcloud's first-run wizard for the WHOLE INSTANCE.
#
# `global-setup.ts` dismisses it for the admin, but that is a PER-USER
# preference and the suite logs in as other people: the seeded `recipient`,
# and the throwaway accounts several specs provision mid-run. A brand-new
# account meets the wizard on first login, and it does not merely cover the
# page — it swallows clicks and keystrokes. Measured: with `recipient` seeded
# but the wizard enabled, "recipient sees the shared dashboard in their
# switcher" failed because `.launchpad-sidebar-toggle` never became
# actionable.
#
# Best-effort: an instance without the app returns non-zero, which is fine.
${OCC} app:disable firstrunwizard >/dev/null 2>&1 \
	&& echo "disabled firstrunwizard" \
	|| echo "firstrunwizard not present or already disabled — continuing"

rc=0
for entry in "${SEED_ACCOUNTS[@]}"; do
	uid="${entry%%:*}"
	password="${entry#*:}"

	if OC_PASS="${password}" ${OCC} user:add --password-from-env "${uid}"; then
		echo "seeded ${uid}"
		continue
	fi

	if ${OCC} user:info "${uid}" >/dev/null 2>&1; then
		echo "${uid} already present — idempotent re-run, continuing"
		continue
	fi

	echo "FATAL: could not create '${uid}', and it does not exist afterwards." >&2
	echo "The Playwright suite needs this account; starting without it would" >&2
	echo "fail for a reason unrelated to the code under test." >&2
	rc=1
done

exit "${rc}"
