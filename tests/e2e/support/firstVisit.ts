/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * What a person meets on their first visit, and how a spec sets it.
 *
 * Three things open on a first visit, and each remembers per USER that it has
 * been seen, through LaunchPad's generic preferences endpoint
 * (`PreferencesController`, `GET`/`PUT /api/preferences/{key}`, stored as the
 * Nextcloud user value `pref_<key>`):
 *
 *  - the Workspace tour (`src/manifest.json` walkthrough,
 *    `completionConfigKey: walkthrough_completed_version`);
 *  - the admin settings page's own tour (`src/utils/adminWalkthrough.js`,
 *    `admin-walkthrough-completed-version`);
 *  - the first-open support note (`useSupportDialog` in nextcloud-vue,
 *    `support-dialog-seen`, value `'1'`).
 *
 * The library also mirrors each in the browser's localStorage, so a value read
 * back from the SERVER is the per-user proof; a fresh browser context is the
 * per-user proof in the UI.
 *
 * A spec that logs in a brand-new account for another purpose calls
 * `quietFirstVisit`, so a tour's full-page dim cannot sit over the controls it
 * drives. A spec ABOUT the first visit does not.
 */

import type { APIRequestContext } from '@playwright/test'

import { expect } from '@playwright/test'
import { API } from './principals.ts'

/** The Workspace tour's completion key (src/manifest.json). */
export const WORKSPACE_TOUR_KEY = 'walkthrough_completed_version'

/** The admin page tour's completion key (src/utils/adminWalkthrough.js). */
export const ADMIN_TOUR_KEY = 'admin-walkthrough-completed-version'

/** The support note's "seen" key (nextcloud-vue useSupportDialog). */
export const SUPPORT_NOTE_KEY = 'support-dialog-seen'

/**
 * Read one of the calling user's preferences.
 *
 * @param api a context acting as the user (principals.contextFor).
 * @param key the preference key.
 * @return the stored value, or null when nothing is stored.
 */
export async function readPreference(
	api: APIRequestContext,
	key: string,
): Promise<string | null> {
	const res = await api.get(`${API}/preferences/${key}`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	return body.value ?? null
}

/**
 * Write one of the calling user's preferences.
 *
 * @param api a context acting as the user.
 * @param key the preference key.
 * @param value the value; an empty string clears it.
 * @return nothing; throws when the write is refused.
 */
export async function writePreference(
	api: APIRequestContext,
	key: string,
	value: string,
): Promise<void> {
	const res = await api.put(`${API}/preferences/${key}`, { data: { value } })
	expect(res.status(), await res.text()).toBe(200)
}

/**
 * Record both tours and the support note as seen for the calling user, so
 * none of them opens over a spec that is about something else.
 *
 * The tour value sorts above any real app version, the same sentinel
 * `global-setup.ts` uses for the admin's browser mirror.
 *
 * @param api a context acting as the user.
 * @return nothing.
 */
export async function quietFirstVisit(api: APIRequestContext): Promise<void> {
	await writePreference(api, WORKSPACE_TOUR_KEY, '999.0.0')
	await writePreference(api, ADMIN_TOUR_KEY, '999.0.0')
	await writePreference(api, SUPPORT_NOTE_KEY, '1')
}
