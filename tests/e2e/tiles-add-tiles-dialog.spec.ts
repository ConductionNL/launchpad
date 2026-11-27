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
 * `database` and `version`, a login page titled Grafana). The server must
 * see it on the runner's address; on a runner where port 3000 is taken, or
 * where Nextcloud runs elsewhere and cannot reach it, the test skips and
 * says so. "An administrator allowed for discovery" has no setting yet; the
 * change names none. When it lands, the GIVEN belongs in beforeAll here, and
 * until then the test can only be red.
 *
 * Not serial on purpose: both are expected red, and a serial file would
 * skip the second.
 *
 * EXPECTED RED (the change's tasks.md has every item open)
 * ============
 * add-office-tiles: green since the Add tiles dialog was built (27 Nov 2026,
 *   sandbox Nextcloud 34.0.6), with the office pack answered by the test
 *   (OFFICE_PACK below), since no CI instance runs an office server.
 * grafana-found: there is no discovery of services on the server.
 *
 * Written in the cloud lane without a live instance; not yet run.
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

const STAMP = `${Date.now()}`
const DASHBOARD = `My workspace ${STAMP}`
const GRAFANA_PORT = 3000

/*
 * The office pack as a server with an office suite answers it. Word, Excel and
 * PowerPoint are the suite's file creators (TemplateManager::listCreators(),
 * which only an installed office server such as Collabora registers) and
 * Outlook web a link tile; a CI or sandbox instance has no office server, so
 * its pack holds only Files. The pack is answered here so the scenario proves
 * the dialog: ticking, the button count and the three tiles stored. What the
 * server puts in the pack is pinned by tests/Unit/Service/OfficeTilesServiceTest.php.
 */
const OFFICE_PACK = [
	{ title: 'Word', linkType: 'create', linkValue: 'richdocuments:.docx', icon: 'icon-filetype-file', iconType: 'class' },
	{ title: 'Excel', linkType: 'create', linkValue: 'richdocuments:.xlsx', icon: 'icon-filetype-file', iconType: 'class' },
	{ title: 'PowerPoint', linkType: 'create', linkValue: 'richdocuments:.pptx', icon: 'icon-filetype-file', iconType: 'class' },
	{ title: 'Outlook web', linkType: 'url', linkValue: 'https://outlook.office.com/mail/', icon: '', iconType: 'class' },
]

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
 * @param officePack The office tile pack the server answers, when the test sets it.
 * @return The context, page and dialog.
 */
async function openAddTiles(
	browser: Browser,
	officePack?: Array<Record<string, string>>,
): Promise<{ context: BrowserContext; page: Page; dialog: Locator }> {
	const count = (await readPlacements(own.owner.api, own.id)).length
	const { context, page } = await openOwnDashboard(browser, own, count)
	if (officePack !== undefined) {
		await page.route('**/apps/launchpad/api/office-tiles', (route) => route.fulfill({
			status: 200,
			contentType: 'application/json',
			body: JSON.stringify({ tiles: officePack }),
		}))
	}
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
	const { context, page, dialog } = await openAddTiles(browser, OFFICE_PACK)
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
	})

	test.afterAll(async () => {
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

		const { context, dialog } = await openAddTiles(browser)
		try {
			const found = dialog.locator('section, [role="group"]')
				.filter({ has: dialog.getByRole('heading', { name: 'Found on this server' }) })
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
