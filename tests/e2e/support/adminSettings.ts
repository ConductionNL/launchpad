/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Helpers for the admin-settings specs: read and write the settings object,
 * put it back the way it was, read the group order, and read a LaunchPad
 * initial-state value out of a rendered page.
 *
 * 🔴 WHY A RESTORE THAT GOES THROUGH THE SHORT NAMES. `PUT /api/admin/settings`
 * binds five settings under two spellings; the short one (`allowUserDash`) is
 * what the admin page sends and the one that has always worked. A cleanup must
 * not depend on the code under test being right, so `restoreSettings` writes
 * the short spelling for those five.
 *
 * 🔴 WHY EVERY SPEC RESTORES IN `afterEach`. These settings are global to the
 * instance the whole suite shares. A test that fails halfway and leaves
 * personal dashboards off, or the group order emptied, reddens specs that run
 * later for a reason their output never shows.
 */

import type { APIRequestContext, Page } from '@playwright/test'

import { expect } from '@playwright/test'
import { API } from './principals.ts'

/** The settings endpoint, relative to the base URL. */
export const SETTINGS = `${API}/admin/settings`

/** The group-order endpoint, relative to the base URL. */
export const GROUPS = `${API}/admin/groups`

/**
 * The thirteen keys REQ-ASET-001 lists under "Defined Settings", in the order
 * the spec gives them.
 */
export const DOCUMENTED_KEYS = [
	'defaultPermissionLevel',
	'allowUserDashboards',
	'allowMultipleDashboards',
	'defaultGridColumns',
	'linkCreateFileExtensions',
	'defaultSharePermissionLevel',
	'forcedShareGroups',
	'sharingAllowedGroups',
	'sharingRecipientScope',
	'legacyWidgetBridgeEnabled',
	'maxDashboardsPerUser',
	'maxWidgetsPerDashboard',
	'quicksearchFallbackTarget',
] as const

/**
 * The settings these specs change, by the key GET answers with, mapped to the
 * name the restore writes.
 */
const RESTORABLE: Record<string, string> = {
	allowUserDashboards: 'allowUserDash',
	allowMultipleDashboards: 'allowMultiDash',
	defaultPermissionLevel: 'defaultPermLevel',
	defaultGridColumns: 'defaultGridCols',
	linkCreateFileExtensions: 'linkCreateFileExts',
	maxDashboardsPerUser: 'maxDashboardsPerUser',
	tileAllowedSchemes: 'tileAllowedSchemes',
	ssoLaunchTemplates: 'ssoLaunchTemplates',
}

/**
 * Read the whole settings object as an admin, failing on anything but 200.
 *
 * @param api an admin context.
 * @return the settings object.
 */
export async function readSettings(
	api: APIRequestContext,
): Promise<Record<string, unknown>> {
	const res = await api.get(SETTINGS)
	expect(res.status(), await res.text()).toBe(200)
	return (await res.json()) as Record<string, unknown>
}

/**
 * Write settings as an admin and require HTTP 200 `{"status": "ok"}`.
 *
 * A 200 is not evidence the value landed; callers re-read.
 *
 * @param api an admin context.
 * @param body the PUT body.
 * @return nothing.
 */
export async function writeSettings(
	api: APIRequestContext,
	body: Record<string, unknown>,
): Promise<void> {
	const res = await api.put(SETTINGS, { data: body })
	expect(res.status(), await res.text()).toBe(200)
	expect(await res.json()).toEqual({ status: 'ok' })
}

/**
 * Put back every setting these specs touch, from a snapshot `readSettings`
 * took before they ran. Never throws: it runs in cleanup.
 *
 * @param api an admin context.
 * @param baseline the snapshot.
 * @return nothing.
 */
export async function restoreSettings(
	api: APIRequestContext,
	baseline: Record<string, unknown> | null,
): Promise<void> {
	if (baseline === null) {
		return
	}
	const body: Record<string, unknown> = {}
	for (const [readKey, writeKey] of Object.entries(RESTORABLE)) {
		if (baseline[readKey] !== undefined) {
			body[writeKey] = baseline[readKey]
		}
	}
	await api.put(SETTINGS, { data: body }).catch(() => undefined)
}

/** The answer of `GET /api/admin/groups`. */
export interface GroupLists {
	active: string[]
	inactive: string[]
	allKnown: Array<{ id: string; displayName: string }>
}

/**
 * Read the group lists as an admin, failing on anything but 200.
 *
 * @param api an admin context.
 * @return the three lists.
 */
export async function readGroups(api: APIRequestContext): Promise<GroupLists> {
	const res = await api.get(GROUPS)
	expect(res.status(), await res.text()).toBe(200)
	return (await res.json()) as GroupLists
}

/**
 * Store a group order as an admin and require HTTP 200.
 *
 * @param api an admin context.
 * @param groups the ordered group ids.
 * @return nothing.
 */
export async function writeGroupOrder(
	api: APIRequestContext,
	groups: unknown[],
): Promise<void> {
	const res = await api.post(GROUPS, { data: { groups } })
	expect(res.status(), await res.text()).toBe(200)
}

/**
 * Decode one LaunchPad initial-state value from a page's HTML.
 *
 * Nextcloud renders each provided value as
 * `<input type="hidden" id="initial-state-<app>-<key>" value="<base64 JSON>">`.
 *
 * @param html the page source.
 * @param key the initial-state key.
 * @return the decoded value; throws when the page carries no such key.
 */
export function initialStateFromHtml(html: string, key: string): unknown {
	const match = html.match(
		new RegExp(`id="initial-state-launchpad-${key}"[^>]*value="([^"]*)"`),
	)
	if (match === null) {
		throw new Error(`the page carries no initial state "${key}"`)
	}
	return JSON.parse(Buffer.from(match[1], 'base64').toString('utf8'))
}

/**
 * Decode one LaunchPad initial-state value from a rendered page.
 *
 * @param page the page.
 * @param key the initial-state key.
 * @return the decoded value.
 */
export async function initialStateFromPage(
	page: Page,
	key: string,
): Promise<unknown> {
	const input = page.locator(`#initial-state-launchpad-${key}`)
	await expect(input, `the page carries no initial state "${key}"`).toHaveCount(1)
	const value = (await input.getAttribute('value')) ?? ''
	return JSON.parse(Buffer.from(value, 'base64').toString('utf8'))
}

/**
 * The items of a dashboard listing, whichever shape it answers in
 * (`{items, quota}` or the bare array).
 *
 * @param body the parsed listing.
 * @return the dashboards.
 */
export function listItems(body: unknown): Array<Record<string, unknown>> {
	if (Array.isArray(body)) {
		return body as Array<Record<string, unknown>>
	}
	const items = (body as { items?: unknown })?.items
	return Array.isArray(items) ? (items as Array<Record<string, unknown>>) : []
}
