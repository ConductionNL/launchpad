/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The admin settings API itself: reading it, writing it, who may do either,
 * and what a write leaves alone (admin-settings REQ-ASET-001, REQ-ASET-002,
 * REQ-ASET-007, REQ-ASET-010, REQ-ASET-011).
 *
 * 🔴 A REFUSAL IS PROBED WITH AN ORDINARY LOGGED-IN ACCOUNT. An anonymous
 * request is turned away by Nextcloud before LaunchPad is reached and proves
 * nothing about the admin guard. Every refusal below is made by a fresh,
 * non-admin account over basic auth, asserts the exact status, re-reads as the
 * admin to show nothing was written, and has the admin succeed at the same
 * call in the same test, so a broken endpoint cannot pass as a refusal.
 *
 * 🔴 THE RE-READ IS THE ASSERTION, NOT THE STATUS. A PUT that bound nothing
 * still answers `{"status": "ok"}`; see admin-settings-key-roundtrip.spec.ts
 * for the defect that taught this. Every write here is followed by a GET.
 *
 * Every setting a test changes is put back in `afterEach`, because the
 * instance is shared with the rest of the suite.
 *
 * @spec openspec/specs/admin-settings/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import {
	DOCUMENTED_KEYS,
	readSettings,
	restoreSettings,
	SETTINGS,
	writeSettings,
} from './support/adminSettings.ts'
import {
	addToGroup,
	adminContext,
	API,
	assertActingAs,
	createPrincipal,
	removePrincipal,
} from './support/principals.ts'

let admin: APIRequestContext
let alice: Principal | null = null
let baseline: Record<string, unknown> | null = null

/**
 * The thirteen documented settings out of a settings object.
 *
 * @param settings a settings object.
 * @return only the documented keys.
 */
function documented(settings: Record<string, unknown>): Record<string, unknown> {
	const picked: Record<string, unknown> = {}
	for (const key of DOCUMENTED_KEYS) {
		picked[key] = settings[key]
	}
	return picked
}

/**
 * Delete a user's dashboard with personal dashboards switched on, which a
 * delete does not need but keeps cleanup independent of the flag.
 *
 * @param api the owner's context.
 * @param id the dashboard id.
 * @return nothing.
 */
async function dropDashboard(api: APIRequestContext, id: number): Promise<void> {
	await api.delete(`${API}/dashboard/${id}`).catch(() => undefined)
}

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

test.beforeAll(async () => {
	admin = await adminContext()
	await assertActingAs(admin, process.env.NC_ADMIN_USER ?? 'admin')
	baseline = await readSettings(admin)
	alice = await createPrincipal(admin, 'e2e-aset-user')
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

// @e2e admin-settings::get-settings-after-modification
test('after a change, GET carries the new value and every other key', async () => {
	await writeSettings(admin, { allowUserDash: true })
	expect((await readSettings(admin)).allowUserDashboards).toBe(true)

	await writeSettings(admin, { allowUserDash: false })

	const res = await admin.get(SETTINGS)
	expect(res.status()).toBe(200)
	const settings = (await res.json()) as Record<string, unknown>
	expect(settings.allowUserDashboards).toBe(false)
	for (const key of DOCUMENTED_KEYS) {
		expect(
			settings,
			`GET lost "${key}" after an unrelated write`,
		).toHaveProperty(key)
	}
})

// @e2e admin-settings::non-admin-user-retrieves-settings
test('an ordinary user is refused the settings with 403 and sees none of them', async () => {
	const refused = await alice!.api.get(SETTINGS)
	expect(refused.status()).toBe(403)
	const text = await refused.text()
	for (const key of DOCUMENTED_KEYS) {
		expect(text, `the refusal leaked the setting key "${key}"`).not.toContain(
			key,
		)
	}

	// The same call as the admin works, so the 403 is the guard and not a broken route.
	const allowed = await admin.get(SETTINGS)
	expect(allowed.status()).toBe(200)
	expect(await allowed.json()).toHaveProperty('allowUserDashboards')
})

// @e2e admin-settings::settings-used-by-non-admin-endpoints
test('a user meets the personal-dashboard setting through POST /api/dashboard without reading it', async () => {
	await writeSettings(admin, { allowUserDash: false, allowMultiDash: true })

	// The user cannot read the setting, so any effect they see is applied server-side.
	expect((await alice!.api.get(SETTINGS)).status()).toBe(403)

	const refused = await alice!.api.post(`${API}/dashboard`, {
		data: { name: `E2E gated ${Date.now()}` },
	})
	expect(refused.status()).toBe(403)
	expect(await refused.json()).toMatchObject({
		status: 'error',
		error: 'personal_dashboards_disabled',
	})

	// Flip it on as the admin: the same user, still without reading the
	// setting, is now let through by the same endpoint.
	await writeSettings(admin, { allowUserDash: true })
	const created = await alice!.api.post(`${API}/dashboard`, {
		data: { name: `E2E allowed ${Date.now()}` },
	})
	expect(created.status(), await created.text()).toBe(201)
	await dropDashboard(alice!.api, Number((await created.json()).dashboard.id))
})

/** Settings GET answers beyond the Defined Settings table, added by other capabilities. */
const LATER_KEYS = [
	'filterTelemetryEnabled',
	'tileAllowedSchemes',
	'ssoLaunchTemplates',
	'announcementEditorGroups',
	'officeNetworks',
	'currentAddress',
	'currentAddressInOfficeNetworks',
	'pinnedCommunities',
	'defaultAppEnabled',
]

// @e2e admin-settings::settings-response-format-consistency
test('GET answers exactly the documented keys, flat', async () => {
	await writeSettings(admin, { allowUserDash: true, defaultGridCols: 8 })
	await writeSettings(admin, { allowMultiDash: true })

	const settings = await readSettings(admin)

	// Flat: no value is a nested object. Lists are values, not nesting.
	for (const [key, value] of Object.entries(settings)) {
		const nested =
			value !== null && typeof value === 'object' && !Array.isArray(value)
		expect(nested, `"${key}" is a nested object`).toBe(false)
	}

	// The thirteen keys of the Defined Settings table, plus the nine settings
	// added since by other capabilities, which AdminSettingsService::getSettings()
	// answers and AdminSettingsServiceTest pins
	// (testGetSettingsReturnsExactlyTheDocumentedKeysAtTheirDefaults).
	expect(Object.keys(settings).sort()).toEqual([...DOCUMENTED_KEYS, ...LATER_KEYS].sort())
})

// @e2e admin-settings::the-retired-start-page-key-is-not-returned
test('the retired startPageWithoutNavigation is neither stored nor returned', async () => {
	const res = await admin.put(SETTINGS, { data: { startPageWithoutNavigation: true } })
	expect(res.status()).toBe(200)

	expect(await readSettings(admin)).not.toHaveProperty('startPageWithoutNavigation')
})

// @e2e admin-settings::update-a-single-boolean-setting
test('PUT {"allowUserDash": false} answers 200 ok and turns the setting off', async () => {
	await writeSettings(admin, { allowUserDash: true })
	expect((await readSettings(admin)).allowUserDashboards).toBe(true)

	const res = await admin.put(SETTINGS, { data: { allowUserDash: false } })
	expect(res.status()).toBe(200)
	expect(await res.json()).toEqual({ status: 'ok' })

	expect((await readSettings(admin)).allowUserDashboards).toBe(false)
})

// @e2e admin-settings::update-multiple-settings-at-once
test('one PUT changes three settings and leaves the rest as they were', async () => {
	await writeSettings(admin, {
		allowUserDash: false,
		allowMultiDash: true,
		defaultGridCols: 12,
		defaultPermLevel: 'view_only',
		linkCreateFileExts: ['txt', 'md'],
	})
	const before = await readSettings(admin)

	await writeSettings(admin, {
		allowUserDash: true,
		allowMultiDash: false,
		defaultGridCols: 8,
	})

	const after = await readSettings(admin)
	expect(after.allowUserDashboards).toBe(true)
	expect(after.allowMultipleDashboards).toBe(false)
	expect(after.defaultGridColumns).toBe(8)

	const untouched = DOCUMENTED_KEYS.filter(
		(key) =>
			![
				'allowUserDashboards',
				'allowMultipleDashboards',
				'defaultGridColumns',
			].includes(key),
	)
	for (const key of untouched) {
		expect(
			after[key],
			`"${key}" changed although the PUT did not name it`,
		).toEqual(before[key])
	}
})

// @e2e admin-settings::non-admin-cannot-update-settings
test('an ordinary user is refused PUT with 403 and nothing changes', async () => {
	await writeSettings(admin, { allowUserDash: false, defaultGridCols: 12 })
	const before = documented(await readSettings(admin))

	const refused = await alice!.api.put(SETTINGS, {
		data: { allowUserDash: true, defaultGridCols: 6, allowUserDashboards: true },
	})
	expect(refused.status()).toBe(403)

	expect(
		documented(await readSettings(admin)),
		'a refused PUT still wrote',
	).toEqual(before)

	// The admin sending the same body succeeds, so the endpoint works.
	await writeSettings(admin, { allowUserDash: true, defaultGridCols: 6 })
	const after = await readSettings(admin)
	expect(after.allowUserDashboards).toBe(true)
	expect(after.defaultGridColumns).toBe(6)
})

// @e2e admin-settings::update-with-unknown-setting-key
test('an unknown key is ignored and no known setting moves', async () => {
	const before = documented(await readSettings(admin))

	const res = await admin.put(SETTINGS, { data: { unknownSetting: 'value' } })
	expect(res.status()).toBe(200)
	expect(await res.json()).toEqual({ status: 'ok' })

	const after = await readSettings(admin)
	expect(after).not.toHaveProperty('unknownSetting')
	expect(documented(after)).toEqual(before)
})

// @e2e admin-settings::factory-reset-behavior
test('a PUT of the listed default values stores them and confirms', async () => {
	// Move every one away from the value the reset sends first, so a reset
	// that wrote nothing cannot read back as a success.
	await writeSettings(admin, {
		allowUserDash: false,
		allowMultiDash: false,
		defaultPermLevel: 'view_only',
		defaultGridCols: 8,
	})

	const res = await admin.put(SETTINGS, {
		data: {
			allowUserDash: true,
			allowMultiDash: true,
			defaultPermLevel: 'add_only',
			defaultGridCols: 12,
		},
	})
	expect(res.status()).toBe(200)
	expect(await res.json()).toEqual({ status: 'ok' })

	const after = await readSettings(admin)
	expect(after.allowUserDashboards).toBe(true)
	expect(after.allowMultipleDashboards).toBe(true)
	expect(after.defaultPermissionLevel).toBe('add_only')
	expect(after.defaultGridColumns).toBe(12)
})

// @e2e admin-settings::simultaneous-updates-from-two-admin-sessions
test('two admins writing different settings at once both land', async () => {
	const second = await createPrincipal(admin, 'e2e-aset-admin')
	try {
		await addToGroup(admin, second.username, 'admin')
		await assertActingAs(second.api, second.username)
		await writeSettings(admin, { allowUserDash: true, allowMultiDash: true })

		const [fromA, fromB] = await Promise.all([
			admin.put(SETTINGS, { data: { allowUserDash: false } }),
			second.api.put(SETTINGS, { data: { allowMultiDash: false } }),
		])
		expect(fromA.status(), await fromA.text()).toBe(200)
		expect(fromB.status(), await fromB.text()).toBe(200)

		const after = await readSettings(admin)
		expect(after.allowUserDashboards).toBe(false)
		expect(after.allowMultipleDashboards).toBe(false)
	} finally {
		await removePrincipal(admin, second)
	}
})

// @e2e admin-settings::rapid-successive-updates
test('saves sent one after another end on the last one', async () => {
	const sequence = [6, 8, 12, 6, 8]
	for (const columns of sequence) {
		const res = await admin.put(SETTINGS, { data: { defaultGridCols: columns } })
		expect(res.status()).toBe(200)
	}
	expect((await readSettings(admin)).defaultGridColumns).toBe(
		sequence[sequence.length - 1],
	)
})

// @e2e admin-settings::empty-request-body-for-update
test('PUT {} answers 200 ok and changes nothing', async () => {
	await writeSettings(admin, { allowUserDash: false, defaultGridCols: 8 })
	const before = documented(await readSettings(admin))

	const res = await admin.put(SETTINGS, { data: {} })
	expect(res.status()).toBe(200)
	expect(await res.json()).toEqual({ status: 'ok' })

	expect(documented(await readSettings(admin))).toEqual(before)
})
