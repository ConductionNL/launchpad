#!/usr/bin/env bash
#
# deploy-to-launchpad.sh — deploy the LaunchPad source into the disposable
# e2e Nextcloud container as the transformed `launchpad` app (the NC App
# Store id is `launchpad`, the source namespace is `OCA\LaunchPad`). The
# container app MUST be a full transform — a partial one 500s the whole
# instance (router loads every app's attribute routes, so one unresolvable
# class kills even /login).
#
# The transform (reverse-engineered + proven; see
# memory reference_launchpad-launchpad-deploy-mismatch):
#   1. PHP + info.xml:  OCA\LaunchPad  -> OCA\LaunchPad   (namespace, repair-steps,
#                       commands, settings class refs).
#   2. PHP:             launchpad_     -> launchpad_      (DB table names →
#                       oc_launchpad_* once NC adds its prefix).
#   3. info.xml:        <namespace>LaunchPad</namespace> -> <namespace>LaunchPad</namespace>
#   4. JS bundles:      js/launchpad-<n>.js -> js/launchpad-<n>.js (+ .map/.LICENSE)
#                       and the chunk-loader string "launchpad-"+ -> "launchpad-"+.
#   5. App dir:         custom_apps/launchpad -> custom_apps/launchpad.
#   6. chown www-data, occ app:enable launchpad, occ upgrade, maintenance --off.
#
# Re-run any time the target container is recreated (the launchpad copy is
# NOT persistent — it is a generated transform copied in via `docker cp`).
#
# Target container (FIXED 2026-07-29 — see below):
#   `LAUNCHPAD_DEPLOY_CONTAINER` env var, default `lp-vue3-e2e` (the
#   disposable e2e container the Playwright suite actually points at via
#   `NC_BASE_URL=http://localhost:8098`). Override only for a deliberately
#   different disposable target — never point this at a shared dev
#   container that bind-mounts a real checkout (see the bind-mount guard
#   below; it will refuse and tell you why).
#
#   PAST BUG: this script used to resolve its target with
#   `docker ps -qf name=nextcloud`, a SUBSTRING match against container
#   names. On a box that also runs a shared `nextcloud` dev container (used
#   by other apps/worktrees), that substring innocently matched the WRONG
#   container — one that bind-mounts `custom_apps/launchpad` straight onto
#   a real host checkout. Every "successful" deploy wrote through the mount
#   onto disk instead of reaching `lp-vue3-e2e` at all: the e2e suite kept
#   testing a stale bundle for the whole session while every app-source fix
#   silently landed on somebody else's checkout instead. `docker ps -qf` is
#   never safe for picking a deploy target when container names aren't
#   guaranteed unique/disjoint on the box — name the target explicitly and
#   verify what you got before writing to it.
#
# Usage:  bash tools/deploy-to-launchpad.sh            # full deploy (PHP+JS+info)
#         bash tools/deploy-to-launchpad.sh --js-only  # fast: only redeploy bundles
#         LAUNCHPAD_DEPLOY_CONTAINER=other-e2e bash tools/deploy-to-launchpad.sh
#
# SPDX-FileCopyrightText: 2026 LaunchPad Contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
set -euo pipefail

SRC="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
JS_ONLY=0
[ "${1:-}" = "--js-only" ] && JS_ONLY=1

DEST=/var/www/html/custom_apps/launchpad
CID="${LAUNCHPAD_DEPLOY_CONTAINER:-lp-vue3-e2e}"

# Resolve by EXACT name/id (never a substring match — see the header
# comment for why that bit us) and confirm it is actually running.
if [ "$(docker inspect -f '{{.State.Running}}' "$CID" 2>/dev/null || true)" != "true" ]; then
	echo "ERROR: container '$CID' is not running (checked via \`docker inspect\`)." >&2
	echo "Set LAUNCHPAD_DEPLOY_CONTAINER to the correct disposable e2e container." >&2
	exit 1
fi

# Refuse to deploy if $DEST (or any ancestor of it) is a BIND mount. A bind
# mount means the container's app directory is wired straight onto a real
# host path — deploying there overwrites someone's actual checkout instead
# of a disposable e2e instance (this is exactly how a substring-matched
# `nextcloud` container silently absorbed a whole session's worth of deploys
# meant for `lp-vue3-e2e`). A volume or a plain in-container directory is
# fine; only `bind` is refused.
while IFS='|' read -r mount_type mount_dest mount_src; do
	[ -z "$mount_dest" ] && continue
	case "$DEST" in
		"$mount_dest" | "$mount_dest"/*)
			if [ "$mount_type" = "bind" ]; then
				echo "ERROR: $CID:$mount_dest is a BIND MOUNT onto host path '$mount_src'." >&2
				echo "Deploying to $CID:$DEST would write through to that real checkout —" >&2
				echo "refusing. This is very likely someone else's work, or the shared dev" >&2
				echo "instance this project's rules say never to deploy to." >&2
				echo "Set LAUNCHPAD_DEPLOY_CONTAINER to the correct disposable e2e container." >&2
				exit 1
			fi
			;;
	esac
done < <(docker inspect "$CID" --format '{{range .Mounts}}{{.Type}}|{{.Destination}}|{{.Source}}
{{end}}')

# Transform a single file in-place (host staging copy only — never the source).
# Rules: (1) PHP namespace OCA\LaunchPad → OCA\LaunchPad; (2) DB table prefix
# launchpad_ → launchpad_; (3) app-id string constants `const X = 'launchpad'`
# → 'launchpad' (APP_ID so routes register under the installed id `launchpad` — else
# NC's nav requests `launchpad.page.index` against a `launchpad.*`-registered route
# and 500s; REGISTER so the manifest resolves the OR register slugged `launchpad`);
# (4) hardcoded quoted route-name literals 'launchpad.<ctrl>.<method>' →
# 'launchpad.…' (the `[a-zA-Z.]*'` anchor avoids matching log strings like
# 'launchpad.cleanup.job_skipped reason=…').
transform_php() {
	sed -i \
		-e 's/OCA\\LaunchPad/OCA\\LaunchPad/g' \
		-e 's/launchpad_/launchpad_/g' \
		-e "s/const \([A-Z_]*\) = 'launchpad'/const \1 = 'launchpad'/g" \
		-e "s/'launchpad\.\([a-zA-Z.]*\)'/'launchpad.\1'/g" \
		-e "s/'launchpad-main'/'launchpad-main'/g" \
		-e "s/'launchpad-admin'/'launchpad-admin'/g" \
		"$1"
	# NOTE: the CSS handle stays 'launchpad' (css/launchpad.css is NOT renamed);
	# 'launchpad-legacy' / 'launchpad-adopt' are logic/config markers, not handles.
}

if [ "$JS_ONLY" -eq 0 ]; then
	[ -f "$SRC/js/launchpad-main.js" ] || { echo "ERROR: build first (npm run build)" >&2; exit 1; }
	STAGE="$(mktemp -d)/launchpad"
	mkdir -p "$STAGE"
	echo "→ staging source (excluding node_modules/.git/tests/docs/openspec)…"
	tar -C "$SRC" \
		--exclude=node_modules --exclude=.git --exclude=tests --exclude=docs \
		--exclude=openspec --exclude='js/*.map' \
		-cf - lib appinfo js img css l10n templates composer.json 2>/dev/null \
		| tar -C "$STAGE" -xf -

	echo "→ transforming PHP + info.xml namespaces and table names…"
	while IFS= read -r -d '' f; do transform_php "$f"; done \
		< <(grep -rlZ -e 'OCA\\LaunchPad' -e 'launchpad_' "$STAGE" --include='*.php' --include='*.xml' || true)
	sed -i 's#<namespace>LaunchPad</namespace>#<namespace>LaunchPad</namespace>#' "$STAGE/appinfo/info.xml"

	echo "→ renaming + rewriting JS bundles (launchpad- → launchpad-)…"
	# NOTE: the source and target app ids are both "launchpad" now (see the
	# header comment), so `new` below is IDENTICAL to `f` for every file.
	# Writing sed's output straight to "$new" via `>` truncates the file
	# before sed even opens it for reading (bash processes redirections
	# before exec'ing the command) — every bundle would come out empty. The
	# trailing `mv "$f.LICENSE.txt" "$new.LICENSE.txt"` then fails outright
	# ("are the same file") since both sides are one path. Stage the sed
	# output in a throwaway temp file and `mv` it into place instead, and
	# only rename the LICENSE sidecar when the path genuinely differs — this
	# stays correct if the ids are ever un-aliased back to two different
	# strings.
	for f in "$STAGE"/js/launchpad-*.js; do
		[ -e "$f" ] || continue
		new="$STAGE/js/$(basename "$f" | sed 's/^launchpad-/launchpad-/')"
		tmp_js="$(mktemp)"
		sed 's/"launchpad-"+/"launchpad-"+/g' "$f" > "$tmp_js"
		[ "$f" != "$new" ] && rm -f "$f"
		mv "$tmp_js" "$new"
		if [ -f "$f.LICENSE.txt" ] && [ "$f.LICENSE.txt" != "$new.LICENSE.txt" ]; then
			mv "$f.LICENSE.txt" "$new.LICENSE.txt"
		fi
	done

	echo "→ deploying to container $CID:$DEST…"
	# custom_apps is a tmpfs mount; `docker cp` into it silently no-ops. Ship a
	# tarball to the container's /tmp (normal overlay) and extract IN-container.
	#
	# $DEST itself can ALSO be its own bind mount (observed: WSL Docker
	# Desktop wired a host directory straight onto
	# /var/www/html/custom_apps/launchpad on this dev box) — `rm -rf "$DEST"`
	# then fails with "Device or resource busy" on the mount point after it
	# has already deleted every file underneath, leaving the app directory
	# EMPTY and the instance broken until the next successful deploy. Clear
	# the directory's CONTENTS instead of the directory itself so this works
	# whether $DEST is a plain directory or a mount point.
	tar -C "$STAGE" -czf /tmp/launchpad-deploy.tar.gz .
	docker cp /tmp/launchpad-deploy.tar.gz "$CID:/tmp/launchpad-deploy.tar.gz"
	docker exec "$CID" sh -c "mkdir -p '$DEST' && find '$DEST' -mindepth 1 -delete && tar -xzf /tmp/launchpad-deploy.tar.gz -C '$DEST' && rm -f /tmp/launchpad-deploy.tar.gz"
	docker exec "$CID" chown -R www-data:www-data "$DEST"
	rm -f /tmp/launchpad-deploy.tar.gz
	rm -rf "$(dirname "$STAGE")"

	echo "→ enabling + upgrading…"
	docker exec -u www-data "$CID" php occ app:enable launchpad 2>&1 | tail -1 || true
	docker exec -u www-data "$CID" php occ upgrade 2>&1 | tail -2 || true
	docker exec -u www-data "$CID" php occ maintenance:mode --off 2>&1 | tail -1 || true
else
	echo "→ --js-only: transforming + copying bundles (via /tmp, tmpfs-safe)…"
	for f in "$SRC"/js/launchpad-*.js; do
		[ -e "$f" ] || continue
		new="launchpad-$(basename "$f" | sed 's/^launchpad-//')"
		tmp="$(mktemp)"
		sed 's/"launchpad-"+/"launchpad-"+/g' "$f" > "$tmp"
		docker cp "$tmp" "$CID:/tmp/$new"
		docker exec "$CID" sh -c "cp /tmp/$new '$DEST/js/$new' && rm -f /tmp/$new"
		rm -f "$tmp"
	done
	docker exec "$CID" chown -R www-data:www-data "$DEST/js"
fi

docker exec -u www-data "$CID" php -r 'opcache_reset();' 2>/dev/null || true
echo "✓ launchpad deploy complete"
