/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end test for the withdrawn GroupFolder storage backend
 * (openspec/specs/groupfolder-storage-backend), written for
 * e2e-coverage-programme task 5.14.
 *
 * The capability is withdrawn; its one remaining requirement (REQ-GFSB-011)
 * says a dashboard's layout lives in its `WidgetPlacement` rows only and the
 * dashboard response carries no `content` key. This spec seeds a throwaway
 * account with one dashboard and one text widget, then reads that dashboard
 * through every read path the shell uses (`GET /api/dashboards`,
 * `GET /api/dashboard/{id}`, `GET /api/dashboard`) and asserts:
 *   - the widget is there, as a placement with the id the server returned;
 *   - no serialised dashboard carries a `content` key (the entity property
 *     and its `jsonSerialize()` key were removed with the backend tree).
 *
 * The "No storage commands" scenario lists occ commands, which Playwright
 * cannot run; it carries an `@e2e exclude` in the spec.
 *
 * Gate-19 @e2e traceability:
 *   @e2e groupfolder-storage-backend::a-dashboard-has-no-content-blob
 *
 * @spec openspec/specs/groupfolder-storage-backend/spec.md
 */

import type { OwnDashboard } from './support/ownDashboard.ts'

import { expect, test } from '@playwright/test'
import {
	readPlacements,
	seedTextWidget,
	setUpOwnDashboard,
	tearDownOwnDashboard,
} from './support/ownDashboard.ts'
import { API } from './support/principals.ts'

test.describe.configure({ mode: 'serial' })

/**
 * Unwrap the `{data: …}` envelope some responses carry.
 *
 * @param body A decoded response body.
 * @return The payload.
 */
function payload(body: any): any {
	return body && typeof body === 'object' && 'data' in body ? body.data : body
}

/**
 * The dashboard items of a list response, bare array or `{items, quota}`.
 *
 * @param body A decoded list response body.
 * @return The items.
 */
function listItems(body: any): Array<Record<string, unknown>> {
	const data = payload(body)
	const items = Array.isArray(data) ? data : data?.items
	expect(Array.isArray(items), `no dashboard list in ${JSON.stringify(body)}`).toBe(true)
	return items as Array<Record<string, unknown>>
}

test.describe('groupfolder-storage-backend: dashboards live in the database only', () => {
	let own: OwnDashboard | undefined
	let placementId = 0
	const text = `gfsb-${Date.now()}`

	test.beforeAll(async () => {
		own = await setUpOwnDashboard('gfsb', 'GFSB no content blob')
		placementId = await seedTextWidget(
			own.owner.api,
			own.id,
			{ x: 0, y: 0, w: 4, h: 2 },
			text,
		)
	})

	test.afterAll(async () => {
		await tearDownOwnDashboard(own)
	})

	test('A dashboard has no content blob: widgets come from placements, no content key', async () => {
		const api = own!.owner.api

		// GET /api/dashboards — the scenario's read path.
		const listed = await api.get(`${API}/dashboards`)
		expect(listed.status(), await listed.text()).toBe(200)
		const items = listItems(await listed.json())
		const mine = items.find((d) => Number(d.id) === own!.id)
		expect(mine, `dashboard ${own!.id} missing from ${JSON.stringify(items)}`).toBeTruthy()
		expect(Object.keys(mine!)).not.toContain('content')
		for (const item of items) {
			expect(Object.keys(item), `dashboard ${item.id} carries a content key`).not.toContain('content')
		}

		// GET /api/dashboard/{id} — where the widgets are served, next to the dashboard.
		const shown = await api.get(`${API}/dashboard/${own!.id}`)
		expect(shown.status(), await shown.text()).toBe(200)
		const body = payload(await shown.json())
		expect(body.dashboard, JSON.stringify(body)).toBeTruthy()
		expect(Number(body.dashboard.id)).toBe(own!.id)
		expect(Object.keys(body.dashboard)).not.toContain('content')

		const placements = await readPlacements(api, own!.id)
		const placed = placements.find((p) => Number(p.id) === placementId)
		expect(placed, `placement ${placementId} missing from ${JSON.stringify(placements)}`).toBeTruthy()
		expect(placed!.widgetId).toBe('text')
		expect(JSON.stringify(placed!.content ?? placed!.config ?? placed)).toContain(text)

		// GET /api/dashboard — the active dashboard the shell boots with.
		const active = await api.get(`${API}/dashboard`)
		expect(active.status(), await active.text()).toBe(200)
		const activeBody = payload(await active.json())
		const activeDash = activeBody?.dashboard ?? activeBody
		expect(Number(activeDash?.id), JSON.stringify(activeBody)).toBe(own!.id)
		expect(Object.keys(activeDash)).not.toContain('content')
	})
})
