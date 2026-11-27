/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The two scenarios of the open change add-tiles-dialog (REQ-TILE-043 and
 * REQ-TILE-044, a delta on `tiles`): the Add tiles dialog with its Office
 * section and its count on the button, and a service found on the server.
 * Gate-19 reads a delta scenario as `tiles::<slug>`, so until this file the
 * two were uncited.
 *
 * THE FIXTURE
 * ===========
 * Pieter is a throwaway account (English) whose one personal dashboard is
 * "My workspace", empty. The dialog is reached from the edit bar, as the
 * change's proposal says ("reached from the edit bar and the picker's App
 * tiles section").
 *
 * "Grafana answers on port 3000": the test starts a stand-in on port 3000
 * of this runner that answers as Grafana does (`/api/health` with a
 * `database` and `version`, a login page titled Grafana). "An administrator
 * allowed for discovery": beforeAll puts Grafana on the administrator's
 * Services to discover list (`PUT /api/admin/discoverable-services`) at
 * `E2E_GRAFANA_URL`, by default `http://localhost:3000`, the address at which
 * Nextcloud reaches this runner when both run on one machine; afterAll puts
 * the earlier list back. On a runner where port 3000 is taken the test skips
 * and says so.
 *
 * Not serial on purpose: a red in the first would skip the second.
 *
 * Gate-19 @e2e traceability:
 *   @e2e tiles::add-office-tiles
 *   @e2e tiles::grafana-found
 *
 * @spec openspec/changes/add-tiles-dialog/specs/tiles/spec.md
 */

import type { Browser, BrowserContext, Locator, Page } from '@playwright/test'
import type { IncomingMessage, Server, ServerResponse } from 'node:http'
import type { OwnDashboard } from './support/ownDashboard.ts'

import { expect, test } from '@playwright/test'
import { createServer } from 'node:http'
import { enterEditMode } from './support/dashboardSwitcher.ts'
import { setLanguage } from './support/orWidget.ts'
import { openOwnDashboard, readPlacements, setUpOwnDashboard, tearDownOwnDashboard } from './support/ownDashboard.ts'
import { adminContext, API } from './support/principals.ts'

const STAMP = `${Date.now()}`
const DASHBOARD = `My workspace ${STAMP}`
const GRAFANA_PORT = 3000
/** Where Nextcloud reaches the Grafana stand-in. */
const GRAFANA_URL = process.env.E2E_GRAFANA_URL?.trim() || `http://localhost:${GRAFANA_PORT}`
/** The administrator's list of services to discover (REQ-TILE-044). */
const DISCOVERABLE = `${API}/admin/discoverable-services`

let own: OwnDashboard

test.setTimeout(120_000)

/**
 * The titles of every tile on the dashboard, from the server.
 *
 * @return The titles.
 */
async function tileTitles(): Promise<string[]> {
	return (await readPlacements(own.owner.api, own.id))
		.map((p) => String(p.tileTitle ?? p.customTitle ?? p.content?.title ?? ''))
		.filter((title) => title !== '')
}

/**
 * Open the dashboard, enter edit mode and open Add tiles.
 *
 * @param browser The browser.
 * @return The context, page and dialog.
 */
async function openAddTiles(browser: Browser): Promise<{ context: BrowserContext; page: Page; dialog: Locator }> {
	const count = (await readPlacements(own.owner.api, own.id)).length
	const { context, page } = await openOwnDashboard(browser, own, count)
	await enterEditMode(page)
	await page.getByRole('button', { name: 'Add tiles' }).click()
	const dialog = page.getByRole('dialog', { name: /add tiles/i })
	await expect(dialog).toBeVisible({ timeout: 10_000 })
	return { context, page, dialog }
}

test.beforeAll(async () => {
	own = await setUpOwnDashboard('e2e-add-tiles', DASHBOARD)
	await setLanguage(own.owner, 'en')
})

test.afterAll(async () => {
	await tearDownOwnDashboard(own)
})

// @e2e tiles::add-office-tiles
test('ticking Word, Excel and Outlook web makes the button read "Add 3 tiles", and it adds three tiles to My workspace', async ({
	browser,
}) => {
	const before = await readPlacements(own.owner.api, own.id)
	const { context, page, dialog } = await openAddTiles(browser)
	try {
		// The four sections, in this order.
		const headings = ['App catalogue', 'Found on this server', 'Ready-made tiles for Office', 'Import bookmarks']
		const tops: number[] = []
		for (const heading of headings) {
			// Office is a group of choice boxes, so its title is the legend of a
			// fieldset; the other sections carry a heading.
			const h = dialog.getByRole('heading', { name: heading })
				.or(dialog.getByRole('group', { name: heading }).locator('legend'))
				.first()
			await expect(h, `no section ${heading}`).toBeVisible()
			tops.push((await h.boundingBox())!.y)
		}
		expect([...tops].sort((a, b) => a - b)).toEqual(tops)

		const office = dialog.getByRole('group', { name: 'Ready-made tiles for Office' })
			.or(dialog.locator('section').filter({ has: page.getByRole('heading', { name: 'Ready-made tiles for Office' }) }))
			.first()
		for (const app of ['Word', 'Excel', 'Outlook web']) {
			await office.getByRole('checkbox', { name: app, exact: true }).check()
		}
		await expect(office.getByRole('checkbox', { name: 'PowerPoint', exact: true })).not.toBeChecked()

		const add = dialog.getByRole('button', { name: 'Add 3 tiles', exact: true })
		await expect(add).toBeEnabled()
		await add.click()
		await expect(dialog).toBeHidden({ timeout: 15_000 })

		await expect.poll(async () => (await readPlacements(own.owner.api, own.id)).length, { timeout: 15_000 })
			.toBe(before.length + 3)
		const titles = await tileTitles()
		for (const app of [/^Word$/, /^Excel$/, /^Outlook( web)?$/]) {
			expect(titles.some((title) => app.test(title)), `no tile ${app} in ${titles.join(', ')}`).toBe(true)
		}
		expect(titles.some((title) => /^PowerPoint$/.test(title)), 'PowerPoint was not ticked').toBe(false)
	} finally {
		await context.close()
	}
})

test.describe('services found on the server', () => {
	let server: Server | undefined
	let listening = false
	let earlier: unknown[] = []

	test.beforeAll(async () => {
		server = createServer((req: IncomingMessage, res: ServerResponse) => {
			if ((req.url ?? '').startsWith('/api/health')) {
				res.writeHead(200, { 'Content-Type': 'application/json' })
				res.end(JSON.stringify({ commit: 'e2e', database: 'ok', version: '10.4.0' }))
				return
			}
			res.writeHead(200, { 'Content-Type': 'text/html' })
			res.end('<!doctype html><html><head><title>Grafana</title></head><body>Grafana</body></html>')
		})
		listening = await new Promise<boolean>((resolve) => {
			server!.once('error', () => resolve(false))
			server!.listen(GRAFANA_PORT, '0.0.0.0', () => resolve(true))
		})

		// GIVEN an administrator allowed Grafana for discovery.
		const admin = await adminContext()
		try {
			const read = await admin.get(DISCOVERABLE)
			expect(read.status(), await read.text()).toBe(200)
			earlier = (await read.json()).services ?? []
			const put = await admin.put(DISCOVERABLE, { data: { services: [{ name: 'Grafana', url: GRAFANA_URL }] } })
			expect(put.status(), await put.text()).toBe(200)
		} finally {
			await admin.dispose()
		}
	})

	test.afterAll(async () => {
		const admin = await adminContext()
		try {
			await admin.put(DISCOVERABLE, { data: { services: earlier } })
		} finally {
			await admin.dispose()
		}
		if (server?.listening) {
			server.closeAllConnections()
			await new Promise<void>((resolve) => server!.close(() => resolve()))
		}
	})

	// @e2e tiles::grafana-found
	test('Grafana answering on port 3000 without a tile is listed as Running with "Add as tile"', async ({
		browser,
	}) => {
		test.skip(!listening, `port ${GRAFANA_PORT} is taken on this runner, so the Grafana stand-in cannot answer there`)
		expect(await tileTitles(), 'Grafana already has a tile').not.toContain('Grafana')

		const { context, page, dialog } = await openAddTiles(browser)
		try {
			// `has` is matched inside each section, so it is rooted at the page.
			const found = dialog.locator('section, [role="group"]')
				.filter({ has: page.getByRole('heading', { name: 'Found on this server' }) })
				.first()
			await expect(found).toBeVisible()
			const row = found.getByRole('listitem').filter({ hasText: 'Grafana' })
				.or(found.locator('tr, li, [data-testid]').filter({ hasText: 'Grafana' }))
				.first()
			await expect(row, 'Grafana is not listed').toBeVisible({ timeout: 15_000 })
			await expect(row).toContainText('Running')
			await expect(row).toContainText(String(GRAFANA_PORT))
			await expect(row.getByRole('button', { name: 'Add as tile' })).toBeVisible()
		} finally {
			await context.close()
		}
	})
})
