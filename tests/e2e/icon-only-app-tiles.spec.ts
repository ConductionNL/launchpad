/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright e2e for icon-only-app-tiles: a dashboard with app tiles shows a
 * live count and the outage dot, and choosing a swatch in the tile form
 * changes the tile colour.
 *
 * Two icon-only tiles are seeded through the API: Zaaksysteem with a
 * register count source and Raadsinformatie with a health ping. The browser
 * stubs the register answer (14 open cases) and the health-ping answer
 * (offline since 09:12), so the spec needs neither the register app nor a
 * failing service. Health pings only save for an allowed website with the
 * purpose Fetch (REQ-HPING-001, fail-closed), so the spec adds
 * raad.example.org to the allowed websites and puts the earlier list back.
 *
 * Gate-19 @e2e traceability:
 *   @e2e tiles::open-cases-on-the-zaaksysteem-tile
 *   @e2e tiles::raadsinformatie-is-down
 *   @e2e tiles::pieter-colours-zaaksysteem
 *
 * @spec openspec/changes/archive/2026-11-25-icon-only-app-tiles/specs/tiles/spec.md
 */
import type { APIRequestContext } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, request, test } from '@playwright/test'
import { gotoLaunchPad } from './fixtures/widget-flow.ts'
import { BASE_URL } from './support/baseUrl.ts'
import {
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'
import { enterEditMode } from './support/dashboardSwitcher.ts'

const API = '/index.php/apps/launchpad/api'
const ADMIN = {
	user: process.env.NEXTCLOUD_USER || 'admin',
	pass: process.env.NEXTCLOUD_PASS || 'admin',
}

let api: APIRequestContext
let seeded: SeededDashboard | null = null
let zaakId = 0
let raadId = 0
let priorSites: unknown[] | null = null

const SITES = `${API}/admin/allowed-sites`
const RAAD_HOST = 'raad.example.org'

/**
 * Create an icon-only tile and store its content.
 *
 * @param title the tile title.
 * @param gridX the column.
 * @param content the content blob.
 * @return the placement id.
 */
async function iconTile(title: string, gridX: number, content: Record<string, unknown>): Promise<number> {
	const res = await api.post(`${API}/dashboard/${seeded!.id}/tile`, {
		data: { title, linkType: 'url', linkValue: 'https://example.org/', gridX, gridY: 0, gridWidth: 1, gridHeight: 1 },
	})
	expect(res.status(), await res.text()).toBe(201)
	const id = Number((await res.json()).id)
	const put = await api.put(`${API}/widgets/${id}`, { data: { content: { iconOnly: true, ...content } } })
	expect(put.ok(), await put.text()).toBeTruthy()
	return id
}

test.describe.configure({ mode: 'serial' })

test.beforeAll(async () => {
	api = await request.newContext({
		baseURL: BASE_URL,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})
	const sites = await api.get(SITES)
	expect(sites.ok(), await sites.text()).toBeTruthy()
	priorSites = (await sites.json()).sites ?? []
	const others = (priorSites as Array<{ host?: string }>).filter((site) => site.host !== RAAD_HOST)
	const allowed = await api.put(SITES, { data: { sites: [...others, { host: RAAD_HOST, embed: false, fetch: true }] } })
	expect(allowed.ok(), await allowed.text()).toBeTruthy()
	seeded = await seedActiveDashboard(api, `E2E App tiles ${Date.now()}`)
	zaakId = await iconTile('Zaaksysteem', 0, {
		countSource: { type: 'register', register: 'cases', schema: 'case', filter: { assignee: '@me', isFinalStatus: false } },
	})
	raadId = await iconTile('Raadsinformatie', 1, {
		healthPingEnabled: true,
		healthUrl: 'https://raad.example.org/health',
		expectedStatus: 200,
		pingInterval: 60,
	})
})

test.afterAll(async () => {
	await removeSeededDashboard(api, seeded)
	if (priorSites !== null) {
		await api?.put(SITES, { data: { sites: priorSites } })
	}
	await api?.dispose()
})

test('app tiles show a live count and the outage dot', async ({ page }) => {
	await page.route('**/apps/openregister/api/objects/cases/case**', (route) =>
		route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ total: 14, results: [] }) }),
	)
	await page.route(`**/apps/launchpad/api/health-ping/${raadId}`, (route) =>
		route.fulfill({
			status: 200,
			contentType: 'application/json',
			body: JSON.stringify({ state: 'offline', checkedAt: '2026-11-25T09:20:00Z', latencyMs: null, stale: false, downSince: '2026-11-25T09:12:00Z' }),
		}),
	)

	await gotoLaunchPad(page)
	const zaak = page.locator(`.tile-widget[data-tile-id="${zaakId}"]`)
	await expect(zaak.getByTestId('tile-badge')).toHaveText('14')
	await expect(zaak.locator('a')).toHaveAttribute('aria-label', 'Zaaksysteem, 14 open')
	await expect(zaak.locator('a')).toHaveAttribute('title', 'Zaaksysteem')

	const raad = page.locator(`.tile-widget[data-tile-id="${raadId}"]`)
	await expect(raad.getByTestId('tile-outage')).toBeVisible()
	await expect(raad.getByTestId('tile-badge')).toHaveCount(0)
	await expect(raad.locator('a')).toHaveAttribute('aria-label', /^Raadsinformatie, outage since /)
})

test('choosing a swatch in the tile form changes the tile colour', async ({ page }) => {
	await gotoLaunchPad(page)
	await enterEditMode(page)
	const zaak = page.locator(`.tile-widget[data-tile-id="${zaakId}"]`)
	await zaak.getByRole('button', { name: 'Tile menu' }).click()
	await page.getByRole('menuitem', { name: 'Edit tile' }).click()

	await page.getByRole('radio', { name: 'Primary', exact: true }).check()
	await page.getByRole('button', { name: /^save$/i }).click()

	await expect(zaak).toHaveAttribute('style', /--tile-bg-color: var\(--sc-primary/)
	const placement = await (await api.get(`${API}/widgets/${zaakId}`)).json().catch(() => null)
	if (placement?.content) {
		expect(placement.content.colourToken).toBe('--sc-primary')
		expect(placement.content.iconOnly).toBe(true)
	}
})
