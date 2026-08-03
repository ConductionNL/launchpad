/*
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * The single source of truth for which Nextcloud the e2e suite talks to.
 *
 * Every spec, fixture, the global setup and the Playwright config itself must
 * resolve the target instance through this module. Two rules, both learned the
 * hard way:
 *
 *  1. There is NO hardcoded default. The config and fifteen call sites used to
 *     read `process.env.NC_BASE_URL ?? 'http://localhost:8080'` — and on a
 *     developer box `http://localhost:8080` is the *shared* dev container. This
 *     suite writes: it creates dashboards, flips the allow-personal-dashboards
 *     admin flag, adds and removes group memberships, and posts
 *     acknowledgements. A run that silently falls back to :8080 does all of
 *     that in an environment other sessions are using, and reports
 *     measurements taken somewhere nobody intended. Failing loudly on an unset
 *     variable is strictly better than defaulting to someone else's instance.
 *
 *  2. Be strict about never inventing a target, permissive about which
 *     variable names it. `PLAYWRIGHT_BASE_URL` wins when set, but `BASE_URL` is
 *     accepted too: that is the name the shared `ConductionNL/.github` quality
 *     workflow exports. A sibling repo shipped a `PLAYWRIGHT_BASE_URL`-only
 *     resolver and its "E2E Tests (Playwright)" job hard-failed on every CI run
 *     with `Error: PLAYWRIGHT_BASE_URL is not set.` — locally correct, and dead
 *     everywhere it mattered. `NEXTCLOUD_URL` and `NC_BASE_URL` are the names
 *     this repo's own docs and docker-compose helpers have always used, so they
 *     stay honoured; only the fallback value is gone.
 */

const RAW = process.env.PLAYWRIGHT_BASE_URL?.trim()
	|| process.env.BASE_URL?.trim()
	|| process.env.NEXTCLOUD_URL?.trim()
	|| process.env.NC_BASE_URL?.trim()
	|| ''

if (!RAW) {
	throw new Error(
		'None of PLAYWRIGHT_BASE_URL, BASE_URL, NEXTCLOUD_URL or NC_BASE_URL is set.\n\n'
		+ 'The e2e suite deliberately has no default: it used to fall back to\n'
		+ 'http://localhost:8080, which is the SHARED dev container, and these\n'
		+ 'tests write — dashboards, admin flags, group membership. Runs then\n'
		+ 'mutated an environment other sessions were using.\n\n'
		+ 'Point it at your own isolated instance, e.g.\n'
		+ '  NC_BASE_URL=http://localhost:8097 npm run test:e2e\n\n'
		+ 'In CI the shared quality workflow exports BASE_URL, which is also\n'
		+ 'accepted; if you are seeing this in CI, that export is missing.\n',
	)
}

/**
 * The base URL of the Nextcloud under test, without a trailing slash.
 */
export const BASE_URL: string = RAW.replace(/\/+$/, '')

/**
 * Build an absolute URL against the instance under test.
 *
 * Use this anywhere a bare string URL is unavoidable (a raw `request.get`, an
 * `apiRequest.newContext({ baseURL })`, a printed diagnostic). Prefer plain
 * relative paths with `page.goto('/index.php/apps/...')` where Playwright
 * applies `use.baseURL` for you.
 *
 * @param pathname Absolute path beginning with `/`, e.g. `/status.php`.
 * @return The path resolved against BASE_URL.
 */
export function absoluteUrl(pathname: string): string {
	return `${BASE_URL}${pathname.startsWith('/') ? pathname : `/${pathname}`}`
}
