/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The address the page was opened on.
 *
 * Read once, when the bundle is evaluated: the router's catch-all redirects a
 * slug address (`/apps/launchpad/<slug>`) to `/` before any route component
 * is created, so `window.location.pathname` read later no longer holds the
 * slug the server resolved.
 *
 * @spec openspec/specs/dashboard-deeplinking/spec.md
 */

import { generateUrl } from '@nextcloud/router'

const BOOT_PATH = typeof window === 'undefined' ? '' : window.location.pathname

/**
 * The pathname the page was loaded with, before any client-side navigation.
 *
 * @return {string} The boot pathname.
 */
export function bootPath() {
	return BOOT_PATH
}

/**
 * The app prefix the page was opened under: `/index.php/apps/launchpad` or
 * `/apps/launchpad`, whichever the address carried. Nextcloud serves both,
 * and the router is mounted on this one, so an address the app writes must
 * use it too: a path under the other form is outside the router's base, and
 * nothing that reads the location against that base (the menu widget's
 * active item) matches it. `generateUrl` is only the fallback.
 *
 * @param {string} [pathname] The pathname to read; the boot pathname by default.
 * @return {string} The app prefix, without a trailing slash.
 */
export function appBase(pathname = BOOT_PATH) {
	const match = String(pathname ?? '').match(/^(.*\/apps\/launchpad)(?:\/|$)/)
	return match ? match[1] : generateUrl('/apps/launchpad')
}
