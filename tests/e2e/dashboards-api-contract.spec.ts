/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The parts of the dashboard request contract that a client can observe
 * (openspec/specs/dashboards/spec.md, REQ-DASH-038..039): the create guard
 * that refuses a second dashboard when the administrator allows only one,
 * the two shapes a create body may take, and a partial update leaving the
 * fields it does not name alone.
 *
 * The other REQ-DASH-038..041 scenarios name a private helper and the
 * arguments it is called with; they are excluded in spec.md with the PHPUnit
 * test that pins each.
 *
 * Gate-19 @e2e traceability:
 *   @e2e dashboards::multiple-dashboard-creation-blocked-when-admin-setting-is-off
 *   @e2e dashboards::resolve-create-params-from-json-body
 *   @e2e dashboards::resolve-create-params-from-scalar-inputs
 *   @e2e dashboards::partial-update-preserves-untouched-fields
 *
 * @spec openspec/specs/dashboards/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { DashboardRow } from './support/dashboardHierarchy.ts'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import {
	allowManyPersonalDashboards,
	createDashboard,
	postDashboard,
	putDashboard,
	readDashboard,
	removeTree,
	visibleRows,
} from './support/dashboardHierarchy.ts'
import {
	adminContext,
	API,
	assertActingAs,
	createPrincipal,
	removePrincipal,
} from './support/principals.ts'

const T = Date.now()

let admin: APIRequestContext
let restoreSettings: (() => Promise<void>) | null = null
const principals: Principal[] = []
const owned: Array<{ api: APIRequestContext; id: number }> = []

/**
 * A fresh throwaway principal, removed in afterAll.
 *
 * @param prefix the account prefix.
 * @return the principal.
 */
async function fresh(prefix: string): Promise<Principal> {
	const principal = await createPrincipal(admin, prefix)
	principals.push(principal)
	await assertActingAs(principal.api, principal.username)
	return principal
}

/**
 * The dashboards a principal owns.
 *
 * @param who the principal.
 * @return the owned rows.
 */
async function ownRows(who: Principal): Promise<DashboardRow[]> {
	return (await visibleRows(who.api)).filter((row) => row.userId === who.username)
}

test.describe('dashboards — observable request contract (REQ-DASH-038..039)', () => {
	test.describe.configure({ mode: 'serial' })
	test.setTimeout(120_000)

	test.beforeAll(async () => {
		admin = await adminContext()
		restoreSettings = await allowManyPersonalDashboards(admin)
	})

	test.afterAll(async () => {
		await restoreSettings?.()
		for (const { api, id } of owned) {
			await removeTree(api, id)
		}
		for (const principal of principals) {
			await removePrincipal(admin, principal)
		}
		await admin?.dispose()
	})

	// @e2e dashboards::multiple-dashboard-creation-blocked-when-admin-setting-is-off
	test('with multiple dashboards switched off, a user who owns one is refused a second with 403', async () => {
		const holder = await fresh('e2e-contract-one')
		const newcomer = await fresh('e2e-contract-new')
		const first = await createDashboard(holder.api, { name: `Only one ${T}` })
		owned.push({ api: holder.api, id: first.id })

		const off = await admin.put(`${API}/admin/settings`, {
			data: { allowMultiDash: false },
		})
		expect(off.status(), await off.text()).toBe(200)
		try {
			const refused = await postDashboard(holder.api, { name: `Second ${T}` })
			expect(refused.status).toBe(403)
			expect(refused.body).toEqual({
				error: 'Multiple dashboards not allowed',
			})
			expect((await ownRows(holder)).map((row) => row.uuid)).toEqual([
				first.uuid,
			])

			// Control: someone without a dashboard may still make their first.
			const allowed = await postDashboard(newcomer.api, { name: `First ${T}` })
			expect(allowed.status, JSON.stringify(allowed.body)).toBe(201)
			owned.push({ api: newcomer.api, id: allowed.dashboard!.id })
		} finally {
			await admin.put(`${API}/admin/settings`, {
				data: { allowMultiDash: true },
			})
		}

		// And with the setting back on, the holder may make a second one.
		const second = await postDashboard(holder.api, { name: `Second ${T}` })
		expect(second.status).toBe(201)
		owned.push({ api: holder.api, id: second.dashboard!.id })
	})

	// @e2e dashboards::resolve-create-params-from-json-body
	test('a create body that nests name and description under "name" is read as a structured body', async () => {
		const who = await fresh('e2e-contract-body')
		const created = await postDashboard(who.api, {
			name: { name: 'Sales', description: 'Q1 dashboard' },
		})
		expect(created.status, JSON.stringify(created.body)).toBe(201)
		owned.push({ api: who.api, id: created.dashboard!.id })

		expect(created.dashboard?.name).toBe('Sales')
		expect(created.dashboard?.description).toBe('Q1 dashboard')
		const stored = (await readDashboard(who.api, created.dashboard!.id))
			.dashboard!
		expect(stored.name).toBe('Sales')
		expect(stored.description).toBe('Q1 dashboard')
	})

	// @e2e dashboards::resolve-create-params-from-scalar-inputs
	test('a create body without name or description gets "My Dashboard" and no description', async () => {
		const who = await fresh('e2e-contract-scalar')
		const created = await postDashboard(who.api, {})
		expect(created.status, JSON.stringify(created.body)).toBe(201)
		owned.push({ api: who.api, id: created.dashboard!.id })

		expect(created.dashboard?.name).toBe('My Dashboard')
		expect(created.dashboard?.description).toBeNull()
		expect(
			(await readDashboard(who.api, created.dashboard!.id)).dashboard?.name,
		).toBe('My Dashboard')
	})

	// @e2e dashboards::partial-update-preserves-untouched-fields
	test('an update naming only the name keeps the description and the placements', async () => {
		const who = await fresh('e2e-contract-patch')
		const dash = await createDashboard(who.api, {
			name: `A ${T}`,
			description: 'B',
		})
		owned.push({ api: who.api, id: dash.id })
		const widget = await who.api.post(`${API}/dashboard/${dash.id}/widgets`, {
			data: {
				widgetId: 'text',
				gridX: 0,
				gridY: 0,
				gridWidth: 4,
				gridHeight: 2,
				content: { text: 'kept' },
			},
		})
		expect(widget.status(), await widget.text()).toBeLessThan(300)
		const before = await readDashboard(who.api, dash.id)
		const placementsBefore = (before.body.placements as Array<{ id: number }>)
			.map((p) => p.id)
			.sort()
		expect(placementsBefore.length).toBeGreaterThan(0)

		const renamed = await putDashboard(who.api, dash.id, {
			name: `Renamed ${T}`,
		})
		expect(renamed.status, JSON.stringify(renamed.body)).toBe(200)

		const after = await readDashboard(who.api, dash.id)
		expect(after.dashboard?.name).toBe(`Renamed ${T}`)
		expect(after.dashboard?.description).toBe('B')
		expect(
			(after.body.placements as Array<{ id: number }>).map((p) => p.id).sort(),
		).toEqual(placementsBefore)
	})
})
