/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The last step of the effective-level chain (permissions REQ-PERM-008): a
 * dashboard with no template and no level of its own takes the admin default.
 * Written for e2e-coverage-programme task 1.4.
 *
 * It was excluded because no API writes a dashboard with an empty
 * permissionLevel. The suite empties it in the row
 * (`support/emptyLevelDashboard.ts`, through `support/instanceDb.ts`); the
 * level the owner then gets is judged by what the server lets her do, not by
 * a field in a response:
 *   - add_only keeps a compulsory widget (full would let it go) and allows an
 *     add (view_only would refuse it);
 *   - the control moves the admin default to view_only and the same add is
 *     refused, so the level demonstrably comes from the admin default and not
 *     from a hard-coded fallback.
 *
 * Gate-19 @e2e traceability:
 *   @e2e permissions::no-template-no-dashboard-level-fallback-to-admin-default
 *
 * @spec openspec/specs/permissions/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { readSettings, restoreSettings, writeSettings } from './support/adminSettings.ts'
import { emptyLevelDashboard, placementIds } from './support/emptyLevelDashboard.ts'
import {
	adminContext,
	API,
	assertActingAs,
	createPrincipal,
	removePrincipal,
} from './support/principals.ts'

let admin: APIRequestContext
let alice: Principal | null = null
let baseline: Record<string, unknown> | null = null

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

test.beforeAll(async () => {
	admin = await adminContext()
	await assertActingAs(admin, process.env.NC_ADMIN_USER ?? 'admin')
	baseline = await readSettings(admin)
	alice = await createPrincipal(admin, 'e2e-perm-default')
	await assertActingAs(alice.api, alice.username)
})

test.afterAll(async () => {
	await restoreSettings(admin, baseline)
	await removePrincipal(admin, alice)
	await admin.dispose()
})

// @e2e permissions::no-template-no-dashboard-level-fallback-to-admin-default
test('no template and no own level: the owner gets the admin default add_only', async () => {
	const { id, locked } = await emptyLevelDashboard(admin, alice!)
	const addText = (y: number) =>
		alice!.api.post(`${API}/dashboard/${id}/widgets`, {
			data: { widgetId: 'text', gridX: 0, gridY: y, gridWidth: 2, gridHeight: 2 },
		})
	try {
		await writeSettings(admin, { defaultPermLevel: 'add_only' })
		expect((await readSettings(admin)).defaultPermissionLevel).toBe('add_only')

		const removal = await alice!.api.delete(`${API}/widgets/${locked}`)
		const text = await removal.text()
		expect(
			removal.status(),
			`the admin default add_only must keep a compulsory widget: ${text}`,
		).toBe(403)
		expect.soft(String(JSON.parse(text).error ?? '')).toMatch(/compulsory/i)
		expect(await placementIds(alice!, id)).toContain(locked)

		const added = await addText(8)
		expect(added.status(), `add_only allows an add: ${await added.text()}`).toBe(201)

		// Control: the level follows the admin default when it moves.
		await writeSettings(admin, { defaultPermLevel: 'view_only' })
		expect((await readSettings(admin)).defaultPermissionLevel).toBe('view_only')
		const refused = await addText(12)
		expect(
			refused.status(),
			`with the admin default at view_only the add must be refused: ${await refused.text()}`,
		).toBe(403)
	} finally {
		await restoreSettings(admin, baseline)
		await alice!.api.delete(`${API}/dashboard/${id}`).catch(() => undefined)
	}
})
