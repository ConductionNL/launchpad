/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * View-count debouncing of a public link (dashboard-public-share
 * REQ-PSHR-007, the same-IP scenario).
 *
 * This scenario used to be excluded because "viewCount and lastViewedAt are
 * DB columns that no anonymous response echoes back". The anonymous response
 * indeed does not, but the OWNER's share list does: `GET
 * /api/dashboards/{uuid}/public-shares` serializes every live link with
 * `viewCount` and `lastViewedAt` (`PublicShare::jsonSerialize()`). So the
 * test renders the link anonymously, as a visitor would, and reads the
 * counter back as the owner.
 *
 * All renders come from this one runner, which is the one IP the scenario
 * needs. Its 0s / 15s / 45s timings only stand for "inside one 60-second
 * window"; the renders here are a couple of seconds apart, far inside it,
 * and far enough apart that `lastViewedAt` (stored to the second) can show
 * it moved to the latest one.
 *
 * @spec openspec/specs/dashboard-public-share/spec.md
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, request, test } from '@playwright/test'
import { BASE_URL } from '../support/baseUrl.ts'
import { adminContext, API } from '../support/principals.ts'

let admin: APIRequestContext
let anonymous: APIRequestContext
let dashboardId = 0
let priorAllowUserDash = true

test.beforeAll(async () => {
	admin = await adminContext()
	// No credentials and an explicitly empty jar: a real anonymous visitor,
	// not one carrying the admin session from global-setup.
	anonymous = await request.newContext({
		baseURL: BASE_URL,
		storageState: { cookies: [], origins: [] },
	})
	const settings = await admin.get(`${API}/admin/settings`)
	priorAllowUserDash = (await settings.json()).allowUserDashboards === true
	const enable = await admin.put(`${API}/admin/settings`, {
		data: { allowUserDash: true },
	})
	expect(enable.status(), await enable.text()).toBe(200)
})

test.afterAll(async () => {
	if (dashboardId !== 0) {
		await admin.delete(`${API}/dashboard/${dashboardId}`)
	}
	if (!priorAllowUserDash) {
		await admin.put(`${API}/admin/settings`, { data: { allowUserDash: false } })
	}
	await anonymous.dispose()
	await admin.dispose()
})

/** The owner's view of one link: its counter and last view. */
async function counters(
	uuid: string,
	id: number,
): Promise<{ viewCount: number; lastViewedAt: string | null }> {
	const res = await admin.get(`${API}/dashboards/${uuid}/public-shares`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	const rows: Array<Record<string, unknown>> = Array.isArray(body)
		? body
		: (body.data ?? [])
	const row = rows.find((r) => Number(r.id) === id)
	expect(row, "the link must be in its owner's list").toBeTruthy()
	return {
		viewCount: Number(row!.viewCount),
		lastViewedAt: (row!.lastViewedAt as string | null) ?? null,
	}
}

/** Render the link the way the public page does, anonymously. */
async function render(token: string): Promise<void> {
	const res = await anonymous.get(`/index.php/apps/launchpad/s/${token}/data`)
	expect(res.status(), await res.text()).toBe(200)
}

async function pause(ms: number): Promise<void> {
	await new Promise((resolve) => setTimeout(resolve, ms))
}

// @e2e dashboard-public-share::repeated-renders-from-same-ip-in-one-minute
test('three renders from one IP inside a minute count once, and lastViewedAt follows the latest', async () => {
	const created = await admin.post(`${API}/dashboard`, {
		data: { name: `E2E view count ${Date.now()}` },
	})
	expect(created.status(), await created.text()).toBeLessThan(300)
	const dash = (await created.json()).dashboard
	dashboardId = Number(dash.id)
	const link = await admin.post(`${API}/dashboards/${dash.uuid}/public-share`, {
		data: {},
	})
	expect(link.status(), await link.text()).toBe(201)
	const { id, token } = await link.json()

	const start = await counters(dash.uuid, id)
	expect(start.viewCount, 'CONTROL: a new link has not been viewed').toBe(0)

	await render(token)
	const first = await counters(dash.uuid, id)
	expect(first.viewCount, 'the first render counts').toBe(start.viewCount + 1)
	expect(first.lastViewedAt, 'the first render is stamped').toBeTruthy()

	await pause(2_100)
	await render(token)
	await pause(2_100)
	await render(token)

	const last = await counters(dash.uuid, id)
	expect(last.viewCount, 'renders inside the window must not count again').toBe(
		start.viewCount + 1,
	)
	expect(
		Date.parse(String(last.lastViewedAt).replace(' ', 'T')),
		'lastViewedAt must move to the latest render',
	).toBeGreaterThan(Date.parse(String(first.lastViewedAt).replace(' ', 'T')))
})
