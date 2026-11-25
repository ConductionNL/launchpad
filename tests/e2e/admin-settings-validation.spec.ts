/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * What PUT /api/admin/settings does with a value outside a setting's range
 * (admin-settings REQ-ASET-002: the three "Update with invalid ..." scenarios).
 *
 * 🔴 EXPECTED RED until the product is fixed. Until now these three scenarios
 * carried `@e2e exclude never built`. Excluding them was honest, but it also
 * meant nothing would turn red once someone did build the check, or noticed it
 * was missing. Read on development on 2026-11-25:
 * `AdminController::updateSettings()` binds `defaultPermLevel` as any string,
 * `defaultGridCols` as any int and `allowUserDash` as a bool that Nextcloud's
 * parameter casting turns "maybe" into, and
 * `AdminSettingsService::updateSettings()` writes all three through
 * `persistIfGiven()` without checking them. So today each refusal below gets
 * 200 `{"status": "ok"}` and the bad value is stored. The spec writes these as
 * SHOULD and notes the gap. The tests assert what the scenario says the
 * endpoint must do, so they go green when the check exists.
 *
 * 🔴 EACH REFUSAL HAS A CONTROL IN THE SAME TEST. The values the scenario
 * says are allowed (`view_only`, `add_only`, `full`; 1 and 24 columns; `true`
 * and `false`) are written first and must be stored. A broken endpoint that
 * refuses everything therefore cannot pass as validation. After each refusal
 * the setting is read again: a 400 that still stored the value is not a
 * refusal.
 *
 * Every setting a test changes is put back in `afterEach` through the short
 * names (see support/adminSettings.ts), because the instance is shared with
 * the rest of the suite.
 *
 * @spec openspec/specs/admin-settings/spec.md
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, test } from '@playwright/test'
import {
	readSettings,
	restoreSettings,
	SETTINGS,
	writeSettings,
} from './support/adminSettings.ts'
import { adminContext, assertActingAs } from './support/principals.ts'

let admin: APIRequestContext
let baseline: Record<string, unknown> | null = null

/**
 * Send a PUT that must be refused, and require a 400 that names the problem.
 *
 * @param api an admin context.
 * @param body the PUT body.
 * @param field the input name the message is expected to mention.
 * @return nothing.
 */
async function expectRefused(
	api: APIRequestContext,
	body: Record<string, unknown>,
	field: string,
): Promise<void> {
	const res = await api.put(SETTINGS, { data: body })
	const text = await res.text()
	expect(
		res.status(),
		`PUT ${JSON.stringify(body)} must be refused with 400, got ${res.status()}: ${text}`,
	).toBe(400)
	// A validation error says what was wrong. The endpoint's own refusals
	// (`RespondsWithErrors::failure()`) answer `{"error": "<message>"}`.
	const error = (JSON.parse(text) as { error?: unknown }).error
	expect(typeof error, `the 400 for ${field} carries no error message: ${text}`).toBe(
		'string',
	)
	expect((error as string).length).toBeGreaterThan(0)
}

test.describe.configure({ mode: 'serial' })
test.setTimeout(60_000)

test.beforeAll(async () => {
	admin = await adminContext()
	await assertActingAs(admin, process.env.NC_ADMIN_USER ?? 'admin')
	baseline = await readSettings(admin)
})

test.afterEach(async () => {
	await restoreSettings(admin, baseline)
})

test.afterAll(async () => {
	await restoreSettings(admin, baseline)
	await admin.dispose()
})

// @e2e admin-settings::update-with-invalid-permission-level-value
test('a default permission level outside view_only, add_only and full is refused with 400 and not stored', async () => {
	// Control: each of the three documented levels is accepted and stored.
	for (const level of ['view_only', 'add_only', 'full']) {
		await writeSettings(admin, { defaultPermLevel: level })
		expect((await readSettings(admin)).defaultPermissionLevel).toBe(level)
	}

	// The store now holds `full`. A rejected write must leave it there.
	await expectRefused(admin, { defaultPermLevel: 'super_admin' }, 'defaultPermLevel')
	expect(
		(await readSettings(admin)).defaultPermissionLevel,
		'"super_admin" was stored as the default permission level',
	).toBe('full')

	// The long spelling reaches the same setting and must be refused too.
	await expectRefused(
		admin,
		{ defaultPermissionLevel: 'super_admin' },
		'defaultPermLevel',
	)
	expect((await readSettings(admin)).defaultPermissionLevel).toBe('full')
})

// @e2e admin-settings::update-with-invalid-grid-columns-value
test('a default grid column count outside 1 to 24 is refused with 400 and not stored', async () => {
	// Control: both ends of the range are accepted and stored.
	for (const columns of [1, 24, 8]) {
		await writeSettings(admin, { defaultGridCols: columns })
		expect((await readSettings(admin)).defaultGridColumns).toBe(columns)
	}

	// 0 is what the scenario sends. -1 and 25 lie outside the same range.
	for (const columns of [0, -1, 25]) {
		await expectRefused(admin, { defaultGridCols: columns }, 'defaultGridCols')
		expect(
			(await readSettings(admin)).defaultGridColumns,
			`${columns} was stored as the default grid column count`,
		).toBe(8)
	}
})

// @e2e admin-settings::update-with-invalid-boolean-value
test('a boolean setting given "maybe" is refused with 400 and not stored', async () => {
	// Control: both booleans are accepted and stored. The store ends on
	// `false`, so a "maybe" cast to true shows up on the re-read.
	for (const value of [true, false]) {
		await writeSettings(admin, { allowUserDash: value })
		expect((await readSettings(admin)).allowUserDashboards).toBe(value)
	}

	await expectRefused(admin, { allowUserDash: 'maybe' }, 'allowUserDash')
	expect(
		(await readSettings(admin)).allowUserDashboards,
		'"maybe" was cast and stored for allowUserDash',
	).toBe(false)

	// The same holds for the second boolean the endpoint takes under a short name.
	await writeSettings(admin, { allowMultiDash: false })
	await expectRefused(admin, { allowMultiDash: 'maybe' }, 'allowMultiDash')
	expect((await readSettings(admin)).allowMultipleDashboards).toBe(false)
})
