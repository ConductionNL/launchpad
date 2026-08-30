#!/usr/bin/env bash
#
# Tests for tests/e2e/seed.sh — the Playwright account seed.
#
# SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
# SPDX-License-Identifier: EUPL-1.2
#
# WHY THIS EXISTS. The seed's whole job is to tell two failures apart:
#
#   "the account is already there"  -> tolerate, the postcondition holds
#   "the account could not be made" -> fail loudly, the suite cannot run
#
# The `|| true` it replaces could not tell them apart, and — this is the part
# that matters — NOTHING WOULD HAVE NOTICED. A seed that always exits 0 looks
# exactly like a seed that works. So the discipline itself is what is asserted
# here, including a case that MUST fail: a test suite where nothing can fail
# proves nothing.
#
# `occ` is stubbed via OCC_CMD, so this runs anywhere — it exercises the
# script's decision logic, which is what the change actually is. It does not
# claim to test Nextcloud's user:add.
#
# Run: bash tests/e2e/seed.test.sh

set -uo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SEED="${HERE}/seed.sh"
WORK="$(mktemp -d)"
trap 'rm -rf "${WORK}"' EXIT

failures=0

# Build a stub `occ` whose two subcommands each exit with a chosen status.
# $1 = exit status for `user:add`, $2 = exit status for `user:info`
make_occ() {
	local add_rc="$1" info_rc="$2"
	cat >"${WORK}/occ-stub" <<STUB
#!/usr/bin/env bash
for arg in "\$@"; do
	case "\$arg" in
		user:add)  echo "stub: user:add called" ; exit ${add_rc} ;;
		user:info) exit ${info_rc} ;;
	esac
done
exit 0
STUB
	chmod +x "${WORK}/occ-stub"
}

# $1 = human name, $2 = expected exit status, $3 = add rc, $4 = info rc
expect() {
	local name="$1" want="$2" add_rc="$3" info_rc="$4"
	make_occ "${add_rc}" "${info_rc}"
	local out rc
	out="$(OCC_CMD="${WORK}/occ-stub" bash "${SEED}" 2>&1)"
	rc=$?
	if [ "${rc}" -eq "${want}" ]; then
		echo "ok   — ${name} (exit ${rc})"
	else
		echo "FAIL — ${name}: expected exit ${want}, got ${rc}"
		echo "       output: ${out}"
		failures=$((failures + 1))
	fi
}

# 1. Fresh instance: user:add succeeds. Nothing to tolerate.
expect "fresh instance seeds the account" 0 0 0

# 2. Warm instance: user:add fails because the account is already there, and
#    user:info confirms it. This is the case `|| true` existed for, and it is
#    still tolerated.
expect "re-run against an existing account is tolerated" 0 1 0

# 3. THE CASE `|| true` SWALLOWED. user:add fails AND the account does not
#    exist — a rejected password, a broken occ, a bad user id. This MUST be a
#    non-zero exit, or the Playwright suite starts against an instance with no
#    grantee.
expect "a real failure is NOT swallowed" 1 1 1

# 4. Positive control on the control itself: if the script were still
#    `|| true`, case 3 would exit 0. Assert the stub can actually produce the
#    failing condition, so a passing case 3 cannot come from a stub that never
#    fails.
make_occ 1 1
if OCC_CMD="${WORK}/occ-stub" "${WORK}/occ-stub" user:info x >/dev/null 2>&1; then
	echo "FAIL — stub sanity: user:info was supposed to fail here"
	failures=$((failures + 1))
else
	echo "ok   — stub sanity: the failing condition is reachable"
fi

echo
if [ "${failures}" -eq 0 ]; then
	echo "seed.sh: all checks passed"
	exit 0
fi
echo "seed.sh: ${failures} check(s) failed"
exit 1
