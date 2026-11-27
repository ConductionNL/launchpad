/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The two scenarios of the former Add tiles dialog (REQ-TILE-043 and
 * REQ-TILE-044, a delta on `tiles`), now on the App or link tile form that
 * replaced the dialog (app-link-tile-widget REQ-APPTILE-004, board
 * LpTegelToevoegen): the "Choose the app" list with the ready-made Office
 * tiles and the count on the Save button, and a service found on the server.
 *
 * THE FIXTURE
 * ===========
 * Pieter is a throwaway account (English) whose one personal dashboard is
 * "My workspace", empty. The form is reached from the edit bar: Add widget,
 * the "App or link tile" card, then "An app".
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
 * add-office-tiles: the office pack is answered by the test (OFFICE_PACK
 *   below), since no CI instance runs an office server.
 *
 * Gate-19 @e2e traceability:
 *   @e2e tiles::add-office-tiles
 *   @e2e tiles::grafana-found
 *   @e2e tiles::an-address-that-is-not-a-web-address-is-refused
 *
 * @spec openspec/changes/archive/2026-11-27-add-tiles-dialog/specs/tiles/spec.md
 * @spec openspec/changes/app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-the-tile-form-starts-with-app-or-link-req-apptile-004
 * @spec openspec/changes/app-link-tile-widget/specs/tiles/spec.md#requirement-tiles-for-services-found-on-the-server-req-tile-044
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
 * Open the dashboard, enter edit mode, open Add widget, choose the App or
 * link tile and then "An app", which shows the "Choose the app" list.
 *
 * @param browser The browser.
 * @param officePack The office tile pack the server answers, when the test sets it.
 * @return The context, page and the "Choose the app" list.
 */
async function openAddTiles(
	browser: Browser,
	officePack?: Array<Record<string, string>>,
): Promise<{ context: BrowserContext; page: Page; chooser: Locator }> {
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
	await page.getByTestId('launchpad-edit-bar-add-widget').click()
	const card = page.getByTestId('widget-picker-type-tile')
	await expect(card).toContainText('App or link tile')
	await card.click()
	await page.getByTestId('widget-picker-add').click()
	await page.getByTestId('tile-mode').getByText('An app', { exact: true }).click()
	const chooser = page.getByTestId('tile-app-chooser')
	await expect(chooser).toBeVisible({ timeout: 10_000 })
	await expect(chooser).toContainText('Choose the app')
	return { context, page, chooser }
}

test.beforeAll(async () => {
	own = await setUpOwnDashboard('e2e-add-tiles', DASHBOARD)
	await setLanguage(own.owner, 'en')
})

test.afterAll(async () => {
	await tearDownOwnDashboard(own)
})

// @e2e tiles::add-office-tiles
test('ticking Word, Excel and Outlook web makes Save read "Add 3 tiles", and it adds three tiles to My workspace', async ({
	browser,
}) => {
	const before = await readPlacements(own.owner.api, own.id)
	const { context, page, chooser } = await openAddTiles(browser, OFFICE_PACK)
	try {
		// The Office tiles are rows of the one list, marked Office.
		const office = chooser.getByTestId('tile-app-row-office')
		await expect(office).toHaveCount(OFFICE_PACK.length, { timeout: 10_000 })
		for (const row of await office.all()) {
			await expect(row.getByTestId('tile-app-meta')).toHaveText('Office')
		}
		for (const app of ['Word', 'Excel', 'Outlook web']) {
			await office.filter({ hasText: app }).getByRole('checkbox', { name: app, exact: true }).check()
		}
		await expect(office.filter({ hasText: 'PowerPoint' }).getByRole('checkbox')).not.toBeChecked()

		const add = page.getByTestId('tile-editor-save')
		await expect(add).toHaveText('Add 3 tiles')
		await expect(add).toBeEnabled()
		await add.click()
		await expect(chooser).toBeHidden({ timeout: 15_000 })

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
	test('Grafana answering on port 3000 without a tile is listed as Running, port 3000, ready to tick', async ({
		browser,
	}) => {
		test.skip(!listening, `port ${GRAFANA_PORT} is taken on this runner, so the Grafana stand-in cannot answer there`)
		expect(await tileTitles(), 'Grafana already has a tile').not.toContain('Grafana')

		const { context, chooser } = await openAddTiles(browser)
		try {
			const row = chooser.getByTestId('tile-app-row-found').filter({ hasText: 'Grafana' })
			await expect(row, 'Grafana is not listed').toBeVisible({ timeout: 15_000 })
			const meta = row.getByTestId('tile-app-meta')
			await expect(meta).toContainText('Running')
			await expect(meta).toContainText(`port ${GRAFANA_PORT}`)
			const tick = row.getByRole('checkbox', { name: 'Grafana', exact: true })
			await expect(tick).toBeEnabled()
			await expect(tick).not.toBeChecked()
		} finally {
			await context.close()
		}
	})
})

// @e2e tiles::an-address-that-is-not-a-web-address-is-refused
test('saving a service whose address is ftp://files.example.org answers 400 with the reason and leaves the list as it was', async () => {
	const admin = await adminContext()
	try {
		const before = await admin.get(DISCOVERABLE)
		expect(before.status(), await before.text()).toBe(200)
		const list = (await before.json()).services

		const put = await admin.put(DISCOVERABLE, { data: { services: [{ name: 'Files', url: 'ftp://files.example.org' }] } })
		expect(put.status()).toBe(400)
		expect((await put.json()).message).toBe('"ftp://files.example.org" is not a web address starting with http:// or https://.')

		const after = await admin.get(DISCOVERABLE)
		expect((await after.json()).services).toEqual(list)
	} finally {
		await admin.dispose()
	}
})
