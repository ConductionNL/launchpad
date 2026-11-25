/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The admin-settings scenarios whose GIVEN is a stored row no endpoint can
 * write or remove (admin-settings REQ-ASET-001, REQ-ASET-003, REQ-ASET-005,
 * REQ-ASET-012), written for e2e-coverage-programme task 1.1.
 *
 * They were excluded as "needs a fresh installation", "needs the row absent",
 * "needs a hand-written row". The suite can make that edit: it runs PHP on
 * the instance with Nextcloud booted (`support/instanceDb.ts`), and
 * `support/adminSettingRows.ts` removes, writes and puts back rows of
 * `launchpad_admin_settings` through the app's own database connection. What
 * is judged is still the product's answer, read through its endpoints:
 * `GET /api/admin/settings`, `GET /api/admin/groups`, `POST /api/dashboard`
 * and the widget endpoints.
 *
 * 🔴 EVERY TEST PUTS THE ROWS BACK EXACTLY. A snapshot of the rows it touches
 * (the whole table for the fresh-install case) is taken first and restored in
 * `finally`, ids included, before the API-level `restoreSettings` runs.
 *
 * 🔴 EACH GIVEN IS PROVEN BEFORE IT IS USED. A "row absent" test first stores
 * a value that would answer differently, reads it back through the API, and
 * only then removes the row, so a test that removed nothing cannot pass.
 *
 * Gate-19 @e2e traceability:
 *   @e2e admin-settings::get-all-settings-with-defaults
 *   @e2e admin-settings::the-retired-content-storage-key-is-not-returned
 *   @e2e admin-settings::default-value-when-setting-is-missing
 *   @e2e admin-settings::factory-default-permission-addonly
 *   @e2e admin-settings::corrupt-db-json-falls-back-to-empty-array
 *   @e2e admin-settings::default-when-setting-absent
 *
 * @spec openspec/specs/admin-settings/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import {
	dropSettingRows,
	putBackSettingRows,
	settingRows,
	storeRawSetting,
} from './support/adminSettingRows.ts'
import {
	DOCUMENTED_KEYS,
	GROUPS,
	readGroups,
	readSettings,
	restoreSettings,
	SETTINGS,
	writeGroupOrder,
	writeSettings,
} from './support/adminSettings.ts'
import { emptyLevelDashboard, placementIds } from './support/emptyLevelDashboard.ts'
import {
	adminContext,
	API,
	assertActingAs,
	createPrincipal,
	removePrincipal,
} from './support/principals.ts'

/** The thirteen documented settings at their documented defaults (REQ-ASET-001). */
const FACTORY_DEFAULTS: Record<(typeof DOCUMENTED_KEYS)[number], unknown> = {
	defaultPermissionLevel: 'add_only',
	allowUserDashboards: false,
	allowMultipleDashboards: true,
	defaultGridColumns: 12,
	linkCreateFileExtensions: ['txt', 'md', 'docx', 'xlsx', 'csv', 'odt'],
	defaultSharePermissionLevel: 'add_only',
	forcedShareGroups: [],
	sharingAllowedGroups: [],
	sharingRecipientScope: 'everyone',
	legacyWidgetBridgeEnabled: false,
	maxDashboardsPerUser: 0,
	maxWidgetsPerDashboard: 0,
	quicksearchFallbackTarget: 'none',
}

let admin: APIRequestContext
let alice: Principal | null = null
let baseline: Record<string, unknown> | null = null

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

test.beforeAll(async () => {
	admin = await adminContext()
	await assertActingAs(admin, process.env.NC_ADMIN_USER ?? 'admin')
	baseline = await readSettings(admin)
	alice = await createPrincipal(admin, 'e2e-aset-rows')
	await assertActingAs(alice.api, alice.username)
})

test.afterEach(async () => {
	await restoreSettings(admin, baseline)
})

test.afterAll(async () => {
	await restoreSettings(admin, baseline)
	await removePrincipal(admin, alice)
	await admin.dispose()
})

// ── REQ-ASET-001 ────────────────────────────────────────────────────────────

// @e2e admin-settings::get-all-settings-with-defaults
test('with no setting stored, GET answers the thirteen documented defaults', async () => {
	// Make the stored state differ from every default it can, so a GET that
	// still read the rows could not match.
	await writeSettings(admin, {
		allowUserDash: true,
		allowMultiDash: false,
		defaultPermLevel: 'view_only',
		defaultGridCols: 8,
		linkCreateFileExts: ['txt'],
	})
	const changed = await readSettings(admin)
	expect(changed.defaultGridColumns).toBe(8)
	expect(changed.defaultPermissionLevel).toBe('view_only')

	const snapshot = await settingRows()
	expect(snapshot.length, 'the instance must hold settings rows to remove').toBeGreaterThan(0)
	try {
		await dropSettingRows()
		expect(await settingRows(), 'the table must be empty, as on a fresh install').toEqual([])

		const res = await admin.get(SETTINGS)
		expect(res.status(), await res.text()).toBe(200)
		const settings = (await res.json()) as Record<string, unknown>
		const documented: Record<string, unknown> = {}
		for (const key of DOCUMENTED_KEYS) {
			documented[key] = settings[key]
		}
		expect(documented).toEqual(FACTORY_DEFAULTS)
	} finally {
		await putBackSettingRows(snapshot)
	}
	expect((await readSettings(admin)).defaultGridColumns, 'the rows must be back').toBe(8)
})

// @e2e admin-settings::the-retired-content-storage-key-is-not-returned
test('a content_storage row left by the retired wizard is neither returned nor touched', async () => {
	const snapshot = await settingRows('content_storage')
	try {
		await storeRawSetting('content_storage', '"openregister"')
		const [stored] = await settingRows('content_storage')
		expect(stored.setting_value).toBe('"openregister"')

		const res = await admin.get(SETTINGS)
		expect(res.status(), await res.text()).toBe(200)
		const text = await res.text()
		const settings = JSON.parse(text) as Record<string, unknown>
		expect(settings).not.toHaveProperty('launchpad.content_storage')
		expect(settings).not.toHaveProperty('contentStorage')
		expect(settings).not.toHaveProperty('content_storage')
		expect(text, 'the stored value must not surface under any key').not.toContain('openregister')

		// Stays unread: the GET neither removed nor rewrote the row.
		const after = await settingRows('content_storage')
		expect(after).toEqual([stored])
	} finally {
		await putBackSettingRows(snapshot, 'content_storage')
	}
})

// ── REQ-ASET-003 ────────────────────────────────────────────────────────────

// @e2e admin-settings::default-value-when-setting-is-missing
test('with no allow_user_dashboards row the setting reads false and a user may not create', async () => {
	await writeSettings(admin, { allowUserDash: true, allowMultiDash: true })
	expect((await readSettings(admin)).allowUserDashboards).toBe(true)

	const snapshot = await settingRows('allow_user_dashboards')
	expect(snapshot.length, 'the PUT must have stored the row').toBe(1)
	try {
		expect(await dropSettingRows('allow_user_dashboards')).toBe(1)

		// AdminSettingsService::getSettings()
		expect((await readSettings(admin)).allowUserDashboards).toBe(false)

		// DashboardService / PermissionService::canCreateDashboard(): the
		// secure default blocks creation.
		const refused = await alice!.api.post(`${API}/dashboard`, {
			data: { name: `E2E no row ${Date.now()}` },
		})
		expect(refused.status(), await refused.text()).toBe(403)
		expect(await refused.json()).toMatchObject({
			status: 'error',
			error: 'personal_dashboards_disabled',
		})
	} finally {
		await putBackSettingRows(snapshot, 'allow_user_dashboards')
	}
})

// ── REQ-ASET-005 ────────────────────────────────────────────────────────────

// @e2e admin-settings::factory-default-permission-addonly
test('with the default level never stored, an empty-level dashboard is add_only', async () => {
	const { id, locked, loose } = await emptyLevelDashboard(admin, alice!)
	const snapshot = await settingRows('default_permission_level')
	try {
		await dropSettingRows('default_permission_level')
		expect(await settingRows('default_permission_level')).toEqual([])
		expect(
			(await readSettings(admin)).defaultPermissionLevel,
			'the factory default the settings report',
		).toBe('add_only')

		// add_only, enforced: a compulsory widget may not be removed (full
		// would allow it), and adding is allowed (view_only would refuse it).
		const removal = await alice!.api.delete(`${API}/widgets/${locked}`)
		expect(
			removal.status(),
			`an empty level must resolve to the factory default add_only, which keeps a compulsory widget: ${await removal.text()}`,
		).toBe(403)
		expect(await placementIds(alice!, id)).toContain(locked)

		const add = await alice!.api.post(`${API}/dashboard/${id}/widgets`, {
			data: { widgetId: 'text', gridX: 0, gridY: 8, gridWidth: 2, gridHeight: 2 },
		})
		expect(add.status(), await add.text()).toBe(201)

		// Control on the same dashboard: an ordinary widget may go under add_only.
		const ordinary = await alice!.api.delete(`${API}/widgets/${loose}`)
		expect(ordinary.status(), await ordinary.text()).toBe(200)
	} finally {
		await putBackSettingRows(snapshot, 'default_permission_level')
		await alice!.api.delete(`${API}/dashboard/${id}`).catch(() => undefined)
	}
})

// ── REQ-ASET-012 ────────────────────────────────────────────────────────────

// @e2e admin-settings::corrupt-db-json-falls-back-to-empty-array
test('a group_order row that is not JSON reads as no active groups, without an error', async () => {
	const snapshot = await settingRows('group_order')
	try {
		await writeGroupOrder(admin, ['admin'])
		expect((await readGroups(admin)).active).toEqual(['admin'])

		await storeRawSetting('group_order', '{not-json')
		const [row] = await settingRows('group_order')
		expect(row.setting_value).toBe('{not-json')

		const res = await admin.get(GROUPS)
		expect(res.status(), await res.text()).toBe(200)
		const groups = await res.json()
		expect(groups.active).toEqual([])
		expect(groups.inactive, 'with nothing active, admin is listed as inactive').toContain('admin')
	} finally {
		await putBackSettingRows(snapshot, 'group_order')
	}
})

// @e2e admin-settings::default-when-setting-absent
test('with no group_order row the active groups are empty', async () => {
	const snapshot = await settingRows('group_order')
	try {
		await writeGroupOrder(admin, ['admin'])
		expect((await readGroups(admin)).active).toEqual(['admin'])

		expect(await dropSettingRows('group_order')).toBe(1)
		expect(await settingRows('group_order')).toEqual([])

		expect((await readGroups(admin)).active).toEqual([])
	} finally {
		await putBackSettingRows(snapshot, 'group_order')
	}
})
