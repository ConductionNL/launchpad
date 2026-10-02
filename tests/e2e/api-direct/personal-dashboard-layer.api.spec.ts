/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * API-direct HTTP-contract coverage for the personal layer over a shared
 * dashboard (dashboards-and-who-may-see-them REQ-DWMS-001, REQ-DWMS-002).
 *
 * These assertions are on raw /api responses, not the rendered grid, so per
 * the gate-19 programme they live under api-direct/ and are excluded from the
 * Playwright UI gate. The `@e2e` annotations keep the traceability check
 * registering the scenarios as covered.
 *
 * Scenarios covered:
 *   @e2e dashboards-and-who-may-see-them::a-handler-rearranges-the-team-dashboard-for-themselves
 *   @e2e dashboards-and-who-may-see-them::a-compulsory-widget-cannot-be-hidden
 *   @e2e dashboards-and-who-may-see-them::reset-to-the-organisations-arrangement
 *
 * The landing read is asserted as well as the layer store. A layer that
 * round-trips through /personal-layer but never reaches the placements the
 * grid draws is a feature that stores and shows nothing, which is exactly
 * what the reader-side PHPUnit coverage was added for.
 *
 * NOT anchored here, covered by PHPUnit and named so anyone can check:
 *   tests/Unit/Service/PersonalLayerServiceTest.php
 *   (testOneMembersArrangementIsInvisibleToTheOthers,
 *   testACompulsoryPlacementCannotBeHiddenAndTheRefusalNamesIt,
 *   testResetDeletesTheWholeLayerAndReturnsTheOwnersArrangement,
 *   testOrphanedEntriesGoWhenAPlacementDisappears)
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
 */

import type { APIRequestContext, request } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { BASE_URL as BASE } from '../support/baseUrl.ts'

const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}

const API = `${BASE}/index.php/apps/launchpad/api`

function LAYER_URL(dashboardId: number): string {
	return `${API}/dashboards/${dashboardId}/personal-layer`
}

/** An authenticated request context using HTTP Basic auth. */
async function adminApi(playwright: {
	request: { newContext: typeof request.newContext }
}): Promise<APIRequestContext> {
	return playwright.request.newContext({
		baseURL: BASE,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})
}

/** The first dashboard the caller can see, with its placements. */
async function anyVisibleDashboard(api: APIRequestContext): Promise<{
	id: number
	ownerId: string | null
	placements: Array<Record<string, unknown>>
}> {
	const res = await api.get(`${API}/dashboards/visible`)
	expect(res.ok(), 'the visible-dashboards route must answer').toBeTruthy()
	const body = (await res.json()) as Record<string, unknown>
	const list = (body.data ?? body.dashboards ?? body) as Array<
		Record<string, unknown>
	>
	expect(
		Array.isArray(list) && list.length > 0,
		'the seeded instance has at least one dashboard',
	).toBeTruthy()

	const id = Number(
		list[0].id ?? (list[0].dashboard as Record<string, unknown>)?.id,
	)
	expect(Number.isFinite(id) && id > 0).toBeTruthy()

	const one = await api.get(`${API}/dashboards/${id}`)
	expect(one.ok()).toBeTruthy()
	const envelope = (await one.json()) as Record<string, unknown>
	const payload = (envelope.data ?? envelope) as Record<string, unknown>

	const dashboard = (payload.dashboard ?? {}) as Record<string, unknown>

	return {
		id,
		ownerId: (dashboard.userId ?? null) as string | null,
		placements: (payload.placements ?? []) as Array<Record<string, unknown>>,
	}
}

test.describe('a personal layer over a dashboard somebody else owns', () => {
	let api: APIRequestContext

	test.beforeAll(async ({ playwright }) => {
		api = await adminApi({ request: playwright.request })
	})

	test.afterAll(async () => {
		await api.dispose()
	})

	// @e2e dashboards-and-who-may-see-them::a-handler-rearranges-the-team-dashboard-for-themselves
	// @e2e dashboards-and-who-may-see-them::reset-to-the-organisations-arrangement
	test('a saved arrangement comes back, and the reset removes all of it', async () => {
		const { id, placements } = await anyVisibleDashboard(api)
		test.skip(
			placements.length === 0,
			'the seeded dashboard carries no placements',
		)

		const first = Number(placements[0].id)

		// Nothing yet: the caller sees what the owner composed.
		const before = await api.get(LAYER_URL(id))
		expect(before.ok()).toBeTruthy()
		expect(((await before.json()) as Record<string, unknown>).hasLayer).toBe(
			false,
		)

		const saved = await api.put(LAYER_URL(id), {
			data: {
				overrides: { [first]: { sortOrder: 99, gridWidth: 4 } },
				hidden: [],
			},
		})
		expect(
			saved.ok(),
			'a person may arrange a dashboard they can see',
		).toBeTruthy()

		const after = (await (await api.get(LAYER_URL(id))).json()) as Record<
			string,
			unknown
		>
		expect(after.hasLayer).toBe(true)
		expect(
			(after.overrides as Record<string, Record<string, number>>)[
				String(first)
			].sortOrder,
		).toBe(99)

		const reset = await api.delete(LAYER_URL(id))
		expect(reset.ok()).toBeTruthy()

		const cleared = (await (await api.get(LAYER_URL(id))).json()) as Record<
			string,
			unknown
		>
		expect(cleared.hasLayer, 'the reset removes the whole layer').toBe(false)
		expect(cleared.overrides).toEqual({})
		expect(cleared.hidden).toEqual([])
	})

	// @e2e dashboards-and-who-may-see-them::a-handler-rearranges-the-team-dashboard-for-themselves
	test('the saved arrangement reaches the placements the grid reads', async () => {
		const { id, ownerId, placements } = await anyVisibleDashboard(api)
		test.skip(
			placements.length < 2,
			'this dashboard has too few placements to reorder',
		)
		test.skip(
			ownerId === ADMIN.user,
			'the caller owns this dashboard, where the arrangement IS the dashboard',
		)

		const last = Number(placements[placements.length - 1].id)

		await api.delete(LAYER_URL(id))
		const saved = await api.put(LAYER_URL(id), {
			data: { overrides: { [last]: { sortOrder: -1 } }, hidden: [] },
		})
		expect(saved.ok(), 'the layer must save').toBeTruthy()

		try {
			// The read the grid does, not the layer store.
			const read = await api.get(`${API}/dashboard`)
			expect(read.ok(), 'the landing read must answer').toBeTruthy()
			const envelope = (await read.json()) as Record<string, unknown>
			const payload = (envelope.data ?? envelope) as Record<string, unknown>
			const drawn = (payload.placements ?? []) as Array<
				Record<string, unknown>
			>
			test.skip(
				Number((payload.dashboard as Record<string, unknown>)?.id) !== id,
				'the landing read resolved a different dashboard than the one arranged',
			)

			expect(
				Number(drawn[0]?.id),
				'the placement moved to the front must be first in what the grid reads; '
					+ 'the owner order here means the layer is stored and never shown',
			).toBe(last)
		} finally {
			await api.delete(LAYER_URL(id))
		}
	})

	// @e2e dashboards-and-who-may-see-them::a-compulsory-widget-cannot-be-hidden
	test('hiding a compulsory placement is refused and names it', async () => {
		const { id, placements } = await anyVisibleDashboard(api)
		const compulsory = placements.find((p) => Number(p.isCompulsory) === 1)
		test.skip(
			compulsory === undefined,
			'the seeded dashboard carries no compulsory placement',
		)

		const refused = await api.put(LAYER_URL(id), {
			data: { overrides: {}, hidden: [Number(compulsory?.id)] },
		})

		expect(refused.status()).toBe(403)
		const body = (await refused.json()) as Record<string, unknown>
		const payload = (body.data ?? body) as Record<string, unknown>
		expect(payload.error).toBe('placement_compulsory')
		expect(Number(payload.placementId)).toBe(Number(compulsory?.id))

		// Nothing landed.
		expect(
			(
				(await (await api.get(LAYER_URL(id))).json()) as Record<
					string,
					unknown
				>
			).hasLayer,
		).toBe(false)
	})

	// The least privileged principal that should be refused: nobody at all.
	test('an unauthenticated caller reads and writes no layer', async ({
		playwright,
	}) => {
		const anonymous = await playwright.request.newContext({ baseURL: BASE })
		const read = await anonymous.get(LAYER_URL(1))
		expect([401, 403, 412].includes(read.status())).toBeTruthy()

		const write = await anonymous.put(LAYER_URL(1), {
			data: { overrides: {}, hidden: [1] },
		})
		expect([401, 403, 412].includes(write.status())).toBeTruthy()
		await anonymous.dispose()
	})
})
