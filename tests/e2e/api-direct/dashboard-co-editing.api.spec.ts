/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * API-direct HTTP-contract coverage for editing one dashboard together
 * (building-edit-a-dashboard-together, capability dashboard-co-editing).
 *
 * Two editors are two request contexts: the administrator who owns the
 * dashboard and a throwaway account it is shared with at full permission.
 * The assertions are on raw /api responses, so the file lives under
 * api-direct/ next to the other contract specs.
 *
 * Scenarios covered:
 *   @e2e dashboard-co-editing::a-second-editor-edits-instead-of-waiting
 *   @e2e dashboard-co-editing::changes-to-different-widgets-both-survive
 *   @e2e dashboard-co-editing::the-later-change-to-one-widget-is-refused-not-lost-silently
 *   @e2e dashboard-co-editing::a-widget-added-during-a-colleagues-edit-takes-a-free-spot
 *
 * The same rules are unit-tested in tests/Unit/Service/DashboardPresenceServiceTest.php
 * and tests/Unit/Service/PlacementServiceTest.php.
 *
 * @spec openspec/specs/dashboard-co-editing/spec.md
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, request, test } from '@playwright/test'
import { deprovisionUser, provisionThrowawayUser } from '../fixtures/secondary-user.ts'
import { BASE_URL } from '../support/baseUrl.ts'

const ADMIN = {
	user: process.env.ADMIN_USER ?? process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.ADMIN_PASSWORD ?? process.env.NC_ADMIN_PASS ?? 'admin',
}

const API = '/index.php/apps/launchpad/api'

type Account = { username: string, password: string }
type Placement = { id: number, gridX: number, gridY: number, gridWidth: number, gridHeight: number, version: number }

let colleague: Account | null = null
let owner: APIRequestContext
let other: APIRequestContext
let dashboard: { id: number, uuid: string }

/**
 * An API context for an account.
 *
 * @param username The user name.
 * @param password The password.
 * @return The context; the caller disposes it.
 */
async function apiAs(username: string, password: string): Promise<APIRequestContext> {
	return request.newContext({
		baseURL: BASE_URL,
		httpCredentials: { username, password },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})
}

/**
 * The object a LaunchPad answer carries, wrapped or not.
 *
 * @param body The parsed answer.
 * @return The object.
 */
function unwrap(body: Record<string, unknown>): Record<string, unknown> {
	return (body.data ?? body.dashboard ?? body.placement ?? body) as Record<string, unknown>
}

/**
 * Add a text widget at the asked spot as the given editor.
 *
 * @param api The editor.
 * @param spot The spot and size asked for.
 * @return The saved placement.
 */
async function addWidget(api: APIRequestContext, spot: Omit<Placement, 'id' | 'version'>): Promise<Placement> {
	const res = await api.post(`${API}/dashboard/${dashboard.id}/widgets`, {
		data: { widgetId: 'launchpad_text', ...spot },
	})
	expect(res.ok(), `adding a widget answers ${res.status()}`).toBeTruthy()
	return unwrap(await res.json()) as unknown as Placement
}

/**
 * The saved placements of the dashboard, as the owner reads them.
 *
 * @return The placements.
 */
async function savedPlacements(): Promise<Placement[]> {
	const res = await owner.get(`${API}/dashboard/${dashboard.id}`)
	expect(res.ok()).toBeTruthy()
	const body = (await res.json()) as Record<string, unknown>
	const data = (body.data ?? body) as Record<string, unknown>
	return (data.placements ?? []) as Placement[]
}

/**
 * Whether two spots overlap.
 *
 * @param a One spot.
 * @param b The other.
 * @return True when they share a cell.
 */
function overlaps(a: Placement, b: Placement): boolean {
	return a.gridX < b.gridX + b.gridWidth && b.gridX < a.gridX + a.gridWidth
		&& a.gridY < b.gridY + b.gridHeight && b.gridY < a.gridY + a.gridHeight
}

test.describe('dashboard-co-editing (API)', () => {
	test.beforeAll(async () => {
		colleague = await provisionThrowawayUser('e2e-coedit')
		owner = await apiAs(ADMIN.user, ADMIN.pass)
		other = await apiAs(colleague.username, colleague.password)

		const created = await owner.post(`${API}/dashboard`, { data: { name: `E2E co-editing ${Date.now()}` } })
		expect(created.ok(), 'the owner creates the dashboard').toBeTruthy()
		const body = unwrap(await created.json())
		dashboard = { id: Number(body.id), uuid: String(body.uuid) }

		const shared = await owner.post(`${API}/dashboard/${dashboard.id}/shares`, {
			data: { shareType: 'user', shareWith: colleague.username, permissionLevel: 'full' },
		})
		expect(shared.ok(), 'the owner shares the dashboard at full permission').toBeTruthy()
	})

	test.afterAll(async () => {
		await owner?.delete(`${API}/dashboard/${dashboard?.id}`)
		await owner?.dispose()
		await other?.dispose()
		if (colleague !== null) {
			await deprovisionUser(colleague.username)
		}
	})

	test('a second editor edits instead of waiting, and sees who else edits since when', async () => {
		const first = await owner.post(`${API}/dashboards/${dashboard.uuid}/presence`)
		expect(first.ok()).toBeTruthy()

		const second = await other.post(`${API}/dashboards/${dashboard.uuid}/presence`)
		expect(second.status(), 'the second editor is let in, not refused').toBe(200)
		const editors = ((await second.json()) as { editors: Array<{ displayName: string, since: string }> }).editors
		expect(editors.length, 'the colleague editing is listed').toBeGreaterThan(0)
		expect(Number.isNaN(Date.parse(editors[0].since)), 'with the time they started').toBe(false)

		await owner.delete(`${API}/dashboards/${dashboard.uuid}/presence`)
		await other.delete(`${API}/dashboards/${dashboard.uuid}/presence`)
	})

	test('changes to different widgets both survive', async () => {
		const calendar = await addWidget(owner, { gridX: 0, gridY: 0, gridWidth: 4, gridHeight: 2 })
		const news = await addWidget(owner, { gridX: 4, gridY: 0, gridWidth: 4, gridHeight: 2 })

		const resized = await owner.put(`${API}/widgets/${calendar.id}`, { data: { gridWidth: 3, version: calendar.version } })
		const moved = await other.put(`${API}/widgets/${news.id}`, { data: { gridY: 3, version: news.version } })
		expect(resized.ok() && moved.ok(), 'both saves are accepted').toBeTruthy()

		const saved = await savedPlacements()
		expect(saved.find((p) => Number(p.id) === Number(calendar.id))?.gridWidth).toBe(3)
		expect(saved.find((p) => Number(p.id) === Number(news.id))?.gridY).toBe(3)
	})

	test('the later change to one widget is refused, not lost silently', async () => {
		const widget = await addWidget(owner, { gridX: 0, gridY: 6, gridWidth: 4, gridHeight: 2 })

		const firstSave = await owner.put(`${API}/widgets/${widget.id}`, { data: { gridWidth: 5, version: widget.version } })
		expect(firstSave.ok(), 'the first save is kept').toBeTruthy()

		const laterSave = await other.put(`${API}/widgets/${widget.id}`, { data: { gridWidth: 2, version: widget.version } })
		expect(laterSave.status(), 'the save on the older version is refused').toBe(409)
		const refusal = (await laterSave.json()) as { error: string, placement: Placement }
		expect(refusal.error).toBe('stale')
		expect(refusal.placement.gridWidth, 'the refused editor sees the colleague\'s version').toBe(5)

		const again = await other.put(`${API}/widgets/${widget.id}`, { data: { gridWidth: 2, version: refusal.placement.version } })
		expect(again.ok(), 'and can make the change again on top of it').toBeTruthy()
	})

	test('a widget added during a colleague\'s edit takes a free spot', async () => {
		const taken = await addWidget(owner, { gridX: 0, gridY: 10, gridWidth: 4, gridHeight: 2 })

		const added = await addWidget(other, { gridX: taken.gridX, gridY: taken.gridY, gridWidth: 4, gridHeight: 2 })
		const saved = await savedPlacements()
		const others = saved.filter((p) => Number(p.id) !== Number(added.id))
		expect(others.some((p) => overlaps(p, added)), 'the new widget covers no other widget').toBe(false)
	})
})
