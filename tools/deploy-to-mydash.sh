#!/usr/bin/env bash
#
# deploy-to-mydash.sh — deploy the LaunchPad source into the dev Nextcloud
# container as the transformed `mydash` app (the NC App Store id is `mydash`,
# the source namespace is `OCA\LaunchPad`). The container app MUST be a full
# transform — a partial one 500s the whole instance (router loads every app's
# attribute routes, so one unresolvable class kills even /login).
#
# The transform (reverse-engineered + proven; see
# memory reference_launchpad-mydash-deploy-mismatch):
#   1. PHP + info.xml:  OCA\LaunchPad  -> OCA\MyDash   (namespace, repair-steps,
#                       commands, settings class refs).
#   2. PHP:             launchpad_     -> mydash_      (DB table names →
#                       oc_mydash_* once NC adds its prefix).
#   3. info.xml:        <namespace>LaunchPad</namespace> -> <namespace>MyDash</namespace>
#   4. JS bundles:      js/launchpad-<n>.js -> js/mydash-<n>.js (+ .map/.LICENSE)
#                       and the chunk-loader string "launchpad-"+ -> "mydash-"+.
#   5. App dir:         custom_apps/launchpad -> custom_apps/mydash.
#   6. chown www-data, occ app:enable mydash, occ upgrade, maintenance --off.
#
# Re-run any time the dev container is recreated (the mydash copy is NOT
# persistent — it is a generated transform of the bind-mounted source).
#
# Usage:  bash tools/deploy-to-mydash.sh            # full deploy (PHP+JS+info)
#         bash tools/deploy-to-mydash.sh --js-only  # fast: only redeploy bundles
#
# SPDX-FileCopyrightText: 2026 LaunchPad Contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
set -euo pipefail

SRC="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
JS_ONLY=0
[ "${1:-}" = "--js-only" ] && JS_ONLY=1

CID="$(docker ps -qf name=nextcloud | head -1)"
if [ -z "$CID" ]; then echo "ERROR: no running 'nextcloud' container" >&2; exit 1; fi
DEST=/var/www/html/custom_apps/mydash

# Transform a single file in-place (host staging copy only — never the source).
# Rules: (1) PHP namespace OCA\LaunchPad → OCA\MyDash; (2) DB table prefix
# launchpad_ → mydash_; (3) the app-id constant APP_ID 'launchpad' → 'mydash'
# (so routes register under the installed id `mydash`, else NC's nav requests
# `mydash.page.index` against a `launchpad.*`-registered route and 500s);
# (4) hardcoded quoted route-name literals 'launchpad.<ctrl>.<method>' →
# 'mydash.…' (the `[a-zA-Z.]*'` anchor avoids matching log strings like
# 'launchpad.cleanup.job_skipped reason=…').
transform_php() {
	sed -i \
		-e 's/OCA\\LaunchPad/OCA\\MyDash/g' \
		-e 's/launchpad_/mydash_/g' \
		-e "s/const APP_ID = 'launchpad'/const APP_ID = 'mydash'/" \
		-e "s/'launchpad\.\([a-zA-Z.]*\)'/'mydash.\1'/g" \
		-e "s/'launchpad-main'/'mydash-main'/g" \
		-e "s/'launchpad-admin'/'mydash-admin'/g" \
		"$1"
	# NOTE: the CSS handle stays 'launchpad' (css/launchpad.css is NOT renamed);
	# 'launchpad-legacy' / 'launchpad-adopt' are logic/config markers, not handles.
}

if [ "$JS_ONLY" -eq 0 ]; then
	[ -f "$SRC/js/launchpad-main.js" ] || { echo "ERROR: build first (npm run build)" >&2; exit 1; }
	STAGE="$(mktemp -d)/mydash"
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
	sed -i 's#<namespace>LaunchPad</namespace>#<namespace>MyDash</namespace>#' "$STAGE/appinfo/info.xml"

	echo "→ renaming + rewriting JS bundles (launchpad- → mydash-)…"
	for f in "$STAGE"/js/launchpad-*.js; do
		[ -e "$f" ] || continue
		new="$STAGE/js/$(basename "$f" | sed 's/^launchpad-/mydash-/')"
		sed 's/"launchpad-"+/"mydash-"+/g' "$f" > "$new"
		rm -f "$f"
		[ -f "$f.LICENSE.txt" ] && mv "$f.LICENSE.txt" "$new.LICENSE.txt"
	done

	echo "→ deploying to container $CID:$DEST…"
	# custom_apps is a tmpfs mount; `docker cp` into it silently no-ops. Ship a
	# tarball to the container's /tmp (normal overlay) and extract IN-container.
	tar -C "$STAGE" -czf /tmp/mydash-deploy.tar.gz .
	docker cp /tmp/mydash-deploy.tar.gz "$CID:/tmp/mydash-deploy.tar.gz"
	docker exec "$CID" sh -c "rm -rf '$DEST' && mkdir -p '$DEST' && tar -xzf /tmp/mydash-deploy.tar.gz -C '$DEST' && rm -f /tmp/mydash-deploy.tar.gz"
	docker exec "$CID" chown -R www-data:www-data "$DEST"
	rm -f /tmp/mydash-deploy.tar.gz
	rm -rf "$(dirname "$STAGE")"

	echo "→ enabling + upgrading…"
	docker exec -u www-data "$CID" php occ app:enable mydash 2>&1 | tail -1 || true
	docker exec -u www-data "$CID" php occ upgrade 2>&1 | tail -2 || true
	docker exec -u www-data "$CID" php occ maintenance:mode --off 2>&1 | tail -1 || true
else
	echo "→ --js-only: transforming + copying bundles (via /tmp, tmpfs-safe)…"
	for f in "$SRC"/js/launchpad-*.js; do
		[ -e "$f" ] || continue
		new="mydash-$(basename "$f" | sed 's/^launchpad-//')"
		tmp="$(mktemp)"
		sed 's/"launchpad-"+/"mydash-"+/g' "$f" > "$tmp"
		docker cp "$tmp" "$CID:/tmp/$new"
		docker exec "$CID" sh -c "cp /tmp/$new '$DEST/js/$new' && rm -f /tmp/$new"
		rm -f "$tmp"
	done
	docker exec "$CID" chown -R www-data:www-data "$DEST/js"
fi

docker exec -u www-data "$CID" php -r 'opcache_reset();' 2>/dev/null || true
echo "✓ mydash deploy complete"
