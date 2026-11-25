/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The settings API when its storage is gone (admin-settings REQ-ASET-011, the
 * two database-failure scenarios). Written for e2e-coverage-programme task 1.1.
 *
 * Both were excluded as "needs the database taken down under a running
 * instance shared by the suite". Taking the whole database down would take the
 * login and the runner's own occ with it, but the scenarios are about the
 * settings API, and what that API needs from the database is one table,
 * `launchpad_admin_settings`. The suite already runs PHP on the instance with
 * the app's own `IDBConnection` (`support/instanceDb.ts`), so for the length of
 * one test that table is renamed away and every query the API makes against it
 * fails in the database, exactly as it would with the server unreachable. The
 * login, the session and every other table stay up, so the failure is the
 * settings store's and nothing else's. The table is renamed back in a
 * `finally`, and `beforeAll` puts back a table a crashed run left renamed.
 *
 * What is judged is the HTTP answer the admin gets (500, a message, no
 * database internals in it) and, for the update, that nothing of the PUT is
 * stored once the table is back.
 *
 * Gate-19 @e2e traceability:
 *   @e2e admin-settings::database-connection-failure-during-settings-retrieval
 *   @e2e admin-settings::database-connection-failure-during-settings-update
 *
 * @spec openspec/specs/admin-settings/spec.md
 */

import type { APIRequestContext, APIResponse } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { SETTINGS_TABLE } from './support/adminSettingRows.ts'
import { readSettings, restoreSettings, SETTINGS } from './support/adminSettings.ts'
import { instancePhp } from './support/instanceDb.ts'
import { adminContext } from './support/principals.ts'

const AWAY = `${SETTINGS_TABLE}_e2e_away`

/** Text that would tell a caller about the database behind the API. */
const DATABASE_DETAILS = [
	/SQLSTATE/i,
	/Doctrine/,
	/\bPDO/,
	new RegExp(SETTINGS_TABLE),
	/no such table/i,
	/doesn't exist/i,
	/does not exist/i,
	/Base table or view/i,
]

let admin: APIRequestContext
let baseline: Record<string, unknown> | null = null

/**
 * Rename a table on the instance, when `from` exists and `to` does not.
 *
 * @param from the table name without prefix.
 * @param to the new name without prefix.
 * @return whether it was renamed.
 */
async function renameTable(from: string, to: string): Promise<boolean> {
	return instancePhp<boolean>(
		`
$out = false;
if ($db->tableExists($in['from']) && !$db->tableExists($in['to'])) {
	$db->executeStatement('ALTER TABLE ' . $prefix . $in['from'] . ' RENAME TO ' . $prefix . $in['to']);
	$out = true;
}
`,
		{ from, to },
	)
}

/**
 * Run `body` with the settings table renamed away, and put it back whatever
 * happens.
 *
 * @param body the requests to make meanwhile.
 * @return nothing.
 */
async function withoutSettingsTable(body: () => Promise<void>): Promise<void> {
	expect(await renameTable(SETTINGS_TABLE, AWAY), 'the settings table must be taken away').toBe(true)
	try {
		await body()
	} finally {
		expect(await renameTable(AWAY, SETTINGS_TABLE), 'the settings table must be put back').toBe(true)
	}
}

/**
 * Assert an answer is a 500 that carries a message and no database internals.
 *
 * @param res the answer.
 * @param what which request it answered.
 * @return nothing.
 */
async function expectOpaque500(res: APIResponse, what: string): Promise<void> {
	const text = await res.text()
	expect(res.status(), `${what} with the store gone: ${text.slice(0, 500)}`).toBe(500)
	expect(text.trim().length, `${what} must carry an error message`).toBeGreaterThan(0)
	for (const pattern of DATABASE_DETAILS) {
		expect(text, `${what} must not expose database details (${pattern})`).not.toMatch(pattern)
	}
}

test.beforeAll(async () => {
	admin = await adminContext()
	// A run that died between the two renames left the table away.
	await renameTable(AWAY, SETTINGS_TABLE)
	baseline = await readSettings(admin)
})

test.afterAll(async () => {
	await renameTable(AWAY, SETTINGS_TABLE)
	await restoreSettings(admin, baseline)
	await admin.dispose()
})

// @e2e admin-settings::database-connection-failure-during-settings-retrieval
test('GET settings with the store unavailable answers 500 with a message and no database details', async () => {
	await withoutSettingsTable(async () => {
		await expectOpaque500(await admin.get(SETTINGS), 'GET /api/admin/settings')
	})

	const after = await admin.get(SETTINGS)
	expect(after.status(), 'CONTROL: with the table back the same GET answers 200').toBe(200)
})

// @e2e admin-settings::database-connection-failure-during-settings-update
test('PUT settings with the store unavailable answers 500 and stores none of it', async () => {
	const before = await readSettings(admin)
	const gridBefore = Number(before.defaultGridColumns)
	const change = {
		allowMultiDash: before.allowMultipleDashboards !== true,
		defaultGridCols: gridBefore === 12 ? 8 : 12,
	}

	await withoutSettingsTable(async () => {
		await expectOpaque500(
			await admin.put(SETTINGS, { data: change }),
			'PUT /api/admin/settings',
		)
	})

	const after = await readSettings(admin)
	expect(
		after.allowMultipleDashboards,
		'no part of the failed PUT may be stored',
	).toBe(before.allowMultipleDashboards)
	expect(after.defaultGridColumns, 'no part of the failed PUT may be stored').toBe(
		before.defaultGridColumns,
	)
})
