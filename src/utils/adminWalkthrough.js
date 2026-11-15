/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The admin settings page's own first-visit tour (runtime-shell REQ-SHELL-011).
 *
 * The tour is declared in `src/manifest.json` as `launchpad:admin-getting-started`
 * with `trigger: "manual"`, so the Workspace shell never starts it there. The
 * admin page is its own bundle (src/admin.js) without the shell, and runs the
 * tour itself: as a first-visit tour, with its own per-user completion
 * preference and its own browser mirror, so finishing the Workspace tour does
 * not close the admin tour and finishing the admin tour does not close the
 * Workspace tour. The admin page is only served to administrators, so nobody
 * else ever sees it.
 */

import { loadWalkthroughSeenVersion, persistWalkthroughSeenVersion } from '@conduction/nextcloud-vue'

/**
 * The admin tour's id in `manifest.walkthrough.tours`.
 */
export const ADMIN_TOUR_ID = 'launchpad:admin-getting-started'

/**
 * The per-user preference that records the admin tour as seen, next to the
 * Workspace tour's `walkthrough_completed_version`.
 */
export const ADMIN_TOUR_COMPLETION_KEY = 'admin-walkthrough-completed-version'

/**
 * Appended to the library's browser-mirror key, so the admin tour's mirror is
 * not the Workspace tour's.
 */
export const ADMIN_TOUR_STORAGE_SUFFIX = ':admin-tour'

/**
 * The manifest the admin page hands the walkthrough: only the admin tour, as a
 * first-visit tour, with the admin completion key. Null when the manifest
 * declares no admin tour.
 *
 * @param {object} manifest The app manifest.
 * @return {object|null} `{ version, walkthrough }` or null.
 * @spec openspec/changes/record-parity-requirements/specs/runtime-shell/spec.md#requirement-the-admin-area-has-its-own-first-visit-tour-req-shell-011
 */
export function adminTourManifest(manifest) {
	const walkthrough = manifest?.walkthrough
	const tours = Array.isArray(walkthrough?.tours) ? walkthrough.tours : []
	const tour = tours.find((candidate) => candidate && candidate.id === ADMIN_TOUR_ID)
	if (!tour || walkthrough.enabled === false) {
		return null
	}
	return {
		version: manifest.version,
		walkthrough: {
			enabled: true,
			version: walkthrough.version,
			completionConfigKey: ADMIN_TOUR_COMPLETION_KEY,
			tours: [{ ...tour, trigger: 'first-visit' }],
		},
	}
}

/**
 * A storage that keeps the admin tour's browser mirror under its own keys.
 * Without a usable base storage it keeps nothing, rather than falling back to
 * the shared mirror.
 *
 * @param {Storage|null} [base] The storage to wrap; `window.localStorage` when omitted.
 * @return {{getItem: (key: string) => (string|null), setItem: (key: string, value: string) => void, removeItem: (key: string) => void}} The scoped storage.
 * @spec openspec/changes/record-parity-requirements/specs/runtime-shell/spec.md#requirement-the-admin-area-has-its-own-first-visit-tour-req-shell-011
 */
export function adminTourStorage(base) {
	const storage = base === undefined ? browserStorage() : base
	if (!storage) {
		return { getItem: () => null, setItem: () => {}, removeItem: () => {} }
	}
	return {
		getItem: (key) => storage.getItem(key + ADMIN_TOUR_STORAGE_SUFFIX),
		setItem: (key, value) => storage.setItem(key + ADMIN_TOUR_STORAGE_SUFFIX, value),
		removeItem: (key) => storage.removeItem(key + ADMIN_TOUR_STORAGE_SUFFIX),
	}
}

/**
 * The version the user last finished the admin tour at, or '' when never.
 *
 * @param {string} appId The app id.
 * @param {object} [options] `{ http, storage }` injection points for tests.
 * @return {Promise<string>} The seen version.
 * @spec openspec/changes/record-parity-requirements/specs/runtime-shell/spec.md#requirement-the-admin-area-has-its-own-first-visit-tour-req-shell-011
 */
export function loadAdminTourSeen(appId, options = {}) {
	return loadWalkthroughSeenVersion(appId, ADMIN_TOUR_COMPLETION_KEY, {
		http: options.http,
		storage: adminTourStorage(options.storage),
	})
}

/**
 * Record the admin tour as finished at a version, for this user.
 *
 * @param {string} appId The app id.
 * @param {string} version The app version.
 * @param {object} [options] `{ http, storage }` injection points for tests.
 * @return {Promise<boolean>} True when the server preference was written.
 * @spec openspec/changes/record-parity-requirements/specs/runtime-shell/spec.md#requirement-the-admin-area-has-its-own-first-visit-tour-req-shell-011
 */
export function persistAdminTourSeen(appId, version, options = {}) {
	return persistWalkthroughSeenVersion(appId, ADMIN_TOUR_COMPLETION_KEY, version, {
		http: options.http,
		storage: adminTourStorage(options.storage),
	})
}

/**
 * The browser's localStorage, or null where it cannot be used.
 *
 * @return {Storage|null}
 */
function browserStorage() {
	try {
		return typeof window === 'undefined' ? null : window.localStorage
	} catch {
		return null
	}
}
