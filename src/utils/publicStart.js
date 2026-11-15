/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The public start page's addresses and its token source
 * (sharing-public-start-page). Pure, so the rules are pinned by Vitest.
 *
 * @spec openspec/changes/sharing-public-start-page/specs/dashboard-public-share/spec.md
 */

/**
 * The start page: `/apps/launchpad/start`, a fixed same-origin path.
 *
 * @param {function(string): string} generateUrl Nextcloud's URL builder.
 * @return {string} The start page URL.
 * @spec openspec/changes/sharing-public-start-page/specs/dashboard-public-share/spec.md#requirement-signed-out-visitors-land-on-the-start-page-req-psp-002
 */
export function startPageUrl(generateUrl) {
	return generateUrl('/apps/launchpad/start')
}

/**
 * The login form with `direct=1`, so the visitor is not sent back to the
 * start page and single sign-on apps skip their automatic redirect.
 *
 * @param {function(string): string} generateUrl Nextcloud's URL builder.
 * @return {string} The sign-in URL.
 * @spec openspec/changes/sharing-public-start-page/specs/dashboard-public-share/spec.md#requirement-the-start-page-offers-a-way-in-for-staff-req-psp-003
 */
export function signInUrl(generateUrl) {
	return `${generateUrl('/login')}?direct=1`
}

/**
 * Which share the public page shows: the start page's token when the server
 * rendered the start page, else the last path segment of `/s/{token}`.
 *
 * @param {string} pathname `window.location.pathname`.
 * @param {string} startToken The `publicStartToken` initial state, '' when absent.
 * @return {{token: string, startPage: boolean}} The token and whether this is the start page.
 * @spec openspec/changes/sharing-public-start-page/specs/dashboard-public-share/spec.md#requirement-signed-out-visitors-land-on-the-start-page-req-psp-002
 */
export function publicPageToken(pathname, startToken) {
	if (typeof startToken === 'string' && startToken !== '') {
		return { token: startToken, startPage: true }
	}
	const segments = String(pathname ?? '').replace(/\/+$/, '').split('/')
	return { token: segments[segments.length - 1] || '', startPage: false }
}
