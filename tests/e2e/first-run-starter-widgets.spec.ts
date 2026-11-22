/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The first-run empty state's two buttons (board LpEersteKeer), proved against
 * the real create endpoint: "Create my first dashboard (with starter widgets)"
 * stores the six starter widgets, "Start empty" stores none, and a create
 * without a choice keeps the default bundle.
 *
 * The empty state itself does not render on the shared instance: every account
 * resolves to the seeded `default`-group dashboard first (see REQ-SHELL-005).
 * The buttons send `widgets: 'starters'` and `widgets: 'none'` (pinned by
 * src/views/__tests__/WorkspaceApp.behaviour.spec.js); this spec sends the
 * same bodies as a fresh account and reads back what was stored.
 *
 * Gate-19 @e2e traceability:
 *   @e2e runtime-shell::a-first-dashboard-with-starter-widgets
 *   @e2e runtime-shell::a-first-dashboard-started-empty
 *
 * @spec openspec/specs/runtime-shell/spec.md#req-shell-005
 */

import type { APIRequestContext } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { readSettings, restoreSettings, writeSettings } from './support/adminSettings.ts'
import { readPlacements } from './support/ownDashboard.ts'
import {
	adminContext,
	API,
	assertActingAs,
	createPrincipal,
	removePrincipal,
} from './support/principals.ts'

const STARTERS = ['app-tiles', 'calendar', 'nc-widget', 'files', 'weather', 'announcements']

test.describe('REQ-SHELL-005: the first dashboard starts with the starter widgets, or empty', () => {
	let admin: APIRequestContext
	let baseline: Record<string, unknown> | null = null
	let owner: Principal | null = null

	test.beforeEach(async () => {
		admin = await adminContext()
		baseline = await readSettings(admin)
		await writeSettings(admin, { allowUserDash: true, allowMultiDash: true })
		owner = await createPrincipal(admin, 'e2e-starters')
		await assertActingAs(owner.api, owner.username)
	})

	test.afterEach(async () => {
		await removePrincipal(admin, owner)
		owner = null
		await restoreSettings(admin, baseline)
		await admin.dispose()
	})

	/**
	 * Create a dashboard as the owner and return its id.
	 *
	 * @param body The create body.
	 * @return The new dashboard's id.
	 */
	async function create(body: Record<string, unknown>): Promise<number> {
		const res = await owner!.api.post(`${API}/dashboard`, { data: body })
		expect(res.status(), await res.text()).toBe(201)
		const json = await res.json()
		const id = Number((json.dashboard ?? json.data?.dashboard)?.id)
		expect(id, JSON.stringify(json)).toBeGreaterThan(0)
		return id
	}

	// @e2e runtime-shell::a-first-dashboard-with-starter-widgets
	test('with starter widgets stores the six starters, and no choice keeps the default bundle', async () => {
		const starters = await create({ name: 'My dashboard', widgets: 'starters' })
		const stored = await readPlacements(owner!.api, starters)
		expect(stored.map((p) => p.widgetId)).toEqual(STARTERS)

		// CONTROL: the same request without a choice is still the default bundle.
		const plain = await create({ name: 'Second' })
		const bundle = (await readPlacements(owner!.api, plain)).map((p) => p.widgetId)
		expect(bundle).toEqual(['tile', 'tile', 'tile', 'files'])
	})

	// @e2e runtime-shell::a-first-dashboard-started-empty
	test('start empty stores a dashboard without widgets', async () => {
		const empty = await create({ name: 'My dashboard', widgets: 'none' })
		expect(await readPlacements(owner!.api, empty)).toEqual([])
	})
})
