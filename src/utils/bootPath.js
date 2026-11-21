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

const BOOT_PATH = typeof window === 'undefined' ? '' : window.location.pathname

/**
 * The pathname the page was loaded with, before any client-side navigation.
 *
 * @return {string} The boot pathname.
 */
export function bootPath() {
	return BOOT_PATH
}
