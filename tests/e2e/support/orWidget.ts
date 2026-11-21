/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Shared fixture for the specs that watch LaunchPad consume OpenRegister at
 * runtime (`runtime-or-consumption`, `launchpad-adopt-or-abstractions`).
 *
 * THE OR-DATA WIDGET THESE SPECS DRIVE
 * ====================================
 * LaunchPad's dashboards render nc-vue's `object-list` widget
 * (`CnObjectListWidget`, registered in `src/main.js` and listed in
 * `lib/widget-types.json`). It is the one widget a person can place that reads
 * OpenRegister objects FROM THE BROWSER: `GET
 * /apps/openregister/api/objects/{register}/{schema}` on mount, and a `PATCH
 * .../{id}` when `content.editable` lets a row be edited. Its request is
 * therefore the one an e2e test can inspect (query string, headers) and the
 * one `page.route()` can fail on purpose to play a 5xx without touching the
 * shared OpenRegister install.
 *
 * The list reads `launchpad/dashboard`, LaunchPad's own register, which the
 * `ImportLaunchpadRegister` repair step provisions on every instance that has
 * OpenRegister. Specs that need deterministic rows fulfil the request
 * themselves; specs that need OpenRegister's real answer let it through.
 *
 * Also here: the two manifest readers, because both capabilities state the
 * same `dependencies: []` rule.
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'
import type { Account } from './filterBus.ts'

import { expect } from '@playwright/test'
import * as fs from 'fs'
import * as path from 'path'
import { API, apiAs } from './filterBus.ts'

/** The register the object-list reads: LaunchPad's own. */
export const OR_REGISTER = 'launchpad'

/** The schema the object-list reads. */
export const OR_SCHEMA = 'dashboard'

/** The OpenRegister collection path the object-list fetches. */
export const OR_LIST_PATH = `/apps/openregister/api/objects/${OR_REGISTER}/${OR_SCHEMA}`

/**
 * Whether a URL is the object-list's collection read (not a single object).
 *
 * @param url the request URL.
 * @return true for `GET .../objects/launchpad/dashboard[?...]`.
 */
export function isOrListUrl(url: URL): boolean {
	return url.pathname.endsWith(OR_LIST_PATH)
}

/**
 * Whether a URL addresses one object of the list's schema.
 *
 * @param url the request URL.
 * @param id the object id.
 * @return true for `.../objects/launchpad/dashboard/{id}`.
 */
export function isOrObjectUrl(url: URL, id: string): boolean {
	return url.pathname.endsWith(`${OR_LIST_PATH}/${encodeURIComponent(id)}`)
}

/**
 * Place an `object-list` widget on an account's dashboard.
 *
 * @param account the dashboard owner.
 * @param dashboardId the dashboard.
 * @param content extra widget content, merged over register/schema/columns.
 * @return the new placement's id.
 */
export async function addObjectList(
	account: Account,
	dashboardId: number,
	content: Record<string, unknown> = {},
): Promise<number> {
	const api = await apiAs(account)
	try {
		const res = await api.post(`${API}/dashboard/${dashboardId}/widgets`, {
			data: {
				widgetId: 'object-list',
				gridX: 0,
				gridY: 0,
				gridWidth: 8,
				gridHeight: 5,
				content: {
					register: OR_REGISTER,
					schema: OR_SCHEMA,
					columns: [{ key: 'title', label: 'Title' }],
					...content,
				},
			},
		})
		expect(res.status(), await res.text()).toBeLessThan(300)
		const body = await res.json()
		const placement = body.placement ?? body.data ?? body
		return Number(placement.id)
	} finally {
		await api.dispose()
	}
}

/**
 * Set an account's Nextcloud language (what `<html lang>` renders).
 *
 * @param account the account.
 * @param language a Nextcloud language code, e.g. `nl` or `en`.
 * @return nothing.
 */
export async function setLanguage(account: Account, language: string): Promise<void> {
	const admin = await apiAs()
	try {
		const res = await admin.put(
			`/ocs/v1.php/cloud/users/${encodeURIComponent(account.username)}`,
			{ form: { key: 'language', value: language } },
		)
		expect(res.ok(), `setting language answered ${res.status()}`).toBe(true)
	} finally {
		await admin.dispose()
	}
}

/**
 * Whether OpenRegister is enabled on the instance (the specs' GIVEN).
 *
 * @return true when `openregister` is in the enabled-apps list.
 */
export async function openRegisterEnabled(): Promise<boolean> {
	const admin = await apiAs()
	try {
		const res = await admin.get('/ocs/v1.php/cloud/apps?filter=enabled&format=json')
		if (!res.ok()) {
			return false
		}
		const apps = (await res.json())?.ocs?.data?.apps
		return Array.isArray(apps) && apps.includes('openregister')
	} finally {
		await admin.dispose()
	}
}

/**
 * The object-list widget on the page (the fixture dashboard holds one).
 *
 * @param page the page.
 * @return its locator.
 */
export function objectListWidget(page: Page): Locator {
	return page.locator('.cn-object-list-widget').first()
}

/**
 * What the widget shows instead of rows when its fetch failed: its quiet
 * error line, or an intended empty state (nc-vue's or Nextcloud's).
 *
 * @param widget the widget.
 * @return the label's locator.
 */
export function degradedLabel(widget: Locator): Locator {
	return widget
		.locator('.cn-object-list-widget__error, .cn-widget-empty-state, .empty-content')
		.first()
}

/**
 * Raw error text a degraded widget must never paint: axios' status message,
 * an HTTP status phrase, or a stack frame.
 */
export const RAW_ERROR = /Request failed|status code|Internal Server Error|Service Unavailable|Exception|\bat \S+ \(/

/**
 * The shipped `src/manifest.json`, read from the checkout the instance runs.
 *
 * Nextcloud's web server answers a static file only for a fixed list of
 * extensions (css, js, svg, images, ...), so a `.json` under the app's web
 * root is routed to index.php and comes back 404; the file cannot be fetched.
 *
 * @param _page kept for the callers' signature.
 * @return the parsed manifest.
 */
export async function readShippedManifest(_page: Page): Promise<Record<string, unknown>> {
	return JSON.parse(
		fs.readFileSync(path.resolve(__dirname, '../../../src/manifest.json'), 'utf8'),
	)
}

/**
 * The runtime manifest the shell replaces the bundled one with.
 *
 * @param api an authenticated request context.
 * @return the parsed manifest.
 */
export async function readServedManifest(
	api: APIRequestContext,
): Promise<Record<string, unknown>> {
	const res = await api.get(`${API}/manifest`)
	expect(res.status(), await res.text()).toBe(200)
	return res.json()
}
