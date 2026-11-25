/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The three scenarios of the open change tile-form-board-sync (REQ-TILE-040
 * to REQ-TILE-042, a delta on `tiles`): the tile form as board
 * LpTegelBewerken draws it, a group's sort order set from that form, and the
 * last availability check shown under its switch. Gate-19 reads a delta
 * scenario as `tiles::<slug>`, so until this file the three were uncited.
 *
 * THE FIXTURE
 * ===========
 * Pieter is a throwaway account (English, the scenarios' language) with one
 * personal dashboard, "My workspace":
 *   - "Case system", a single sign-on tile (`linkType: sso`, on a launch
 *     template this spec adds and removes) that opens in a new tab
 *     (`content.linkTarget: new-tab`);
 *   - "Work apps", a group of three tiles (Case system, Mail, Calendar). A
 *     group of tiles is a container on development, made here through the
 *     bookmark import, the one path that writes a container with children
 *     (as container-tile-sorting does). Its `content.sortBy` is the group's
 *     order; `last-used` is the stored name of "Last used first"
 *     (src/utils/tileSort.js).
 *
 * "For every reader": a tile's use is counted in the reader's own browser
 * (REQ-TSO), so the test opens the group in two browsers with different use
 * and requires each to put its own last-opened tile first.
 *
 * The availability test gives Case system a health ping on a small HTTP
 * server this spec starts (as service-health-ping does): Nextcloud must reach
 * it on HEALTH_PING_FIXTURE_HOST (then LIVE_TILE_FIXTURE_HOST, then
 * 127.0.0.1). When it cannot, the test skips and says so. The scenario's
 * "10:40" is the time of the last check, so the test reads that time from
 * `GET /api/health-ping/{id}` and requires the form to show it.
 *
 * Not serial on purpose: all three are expected red, and a serial file would
 * skip every test after the first.
 *
 * EXPECTED RED (the change's tasks.md has every item open)
 * ============
 * edit-the-case-system-tile: the form is "Edit Tile" with Title, Icon,
 *   colours and an "Opens" select (src/modals/TileEditor.vue); it has no
 *   group, no choice cards and no open-in cards.
 * sort-a-group-by-use: a tile inside a container has no form of its own, and
 *   the sort order is a field of the container form.
 * a-reachable-tile: the form shows no result of the last check.
 *
 * Written in the cloud lane without a live instance; not yet run.
 *
 * Gate-19 @e2e traceability:
 *   @e2e tiles::edit-the-case-system-tile
 *   @e2e tiles::sort-a-group-by-use
 *   @e2e tiles::a-reachable-tile
 *
 * @spec openspec/changes/tile-form-board-sync/specs/tiles/spec.md
 */

import type { APIRequestContext, Browser, BrowserContext, Locator, Page } from '@playwright/test'
import type { IncomingMessage, Server, ServerResponse } from 'node:http'
import type { AddressInfo } from 'node:net'
import type { OwnDashboard } from './support/ownDashboard.ts'

import { expect, test } from '@playwright/test'
import { createServer } from 'node:http'
import { readSettings, writeSettings } from './support/adminSettings.ts'
import { storedContent, tileOf } from './support/appTiles.ts'
import { enterEditMode } from './support/dashboardSwitcher.ts'
import { setLanguage } from './support/orWidget.ts'
import { openOwnDashboard, readPlacements, setUpOwnDashboard, tearDownOwnDashboard } from './support/ownDashboard.ts'
import { adminContext, API } from './support/principals.ts'

const STAMP = `${Date.now()}`
const DASHBOARD = `My workspace ${STAMP}`
const GROUP = `Work apps ${STAMP}`
const CASE_SYSTEM = 'Case system'
const GROUP_TILES = [CASE_SYSTEM, 'Mail', 'Calendar']
const SSO_KEY = `e2e-idp-${STAMP}`
const CONFIG = '/ocs/v2.php/apps/provisioning_api/api/v1/config/apps/launchpad'
const FIXTURE_HOST = process.env.HEALTH_PING_FIXTURE_HOST || process.env.LIVE_TILE_FIXTURE_HOST || '127.0.0.1'

/** The form's fields, in the order REQ-TILE-040 names them. */
const FIELD_ORDER = [
	/^Name$/,
	/^Group$/,
	/^Tile type$/,
	/^Web address$/,
	/^Internal address/,
	/^Open in$/,
	/^Icon$/,
	/^Colou?r$/,
	/^Sort order in this group$/,
	/^Check availability$/,
]

let own: OwnDashboard
let admin: APIRequestContext
let templatesBefore: unknown = []
let caseSystemId = 0
let groupId = 0
let groupContent: Record<string, any> = {}

test.setTimeout(150_000)

/**
 * Open Pieter's dashboard.
 *
 * @param browser The browser.
 * @return The context and page.
 */
async function open(browser: Browser): Promise<{ context: BrowserContext; page: Page }> {
	const count = (await readPlacements(own.owner.api, own.id)).length
	return openOwnDashboard(browser, own, count)
}

/**
 * Open Edit tile on a tile, in edit mode.
 *
 * @param page The page.
 * @param tile The tile element.
 * @param name The tile name.
 * @return The form dialog.
 */
async function openTileForm(page: Page, tile: Locator, name: string): Promise<Locator> {
	await enterEditMode(page)
	await tile.getByRole('button', { name: /tile menu|edit tile/i }).first().click()
	const item = page.getByRole('menuitem', { name: /edit tile/i })
	if (await item.count() > 0) {
		await item.click()
	}
	const form = page.getByRole('dialog', { name: `Edit tile: ${name}` })
	await expect(form).toBeVisible({ timeout: 10_000 })
	return form
}

/**
 * The top of each named field's label in the form, in document order.
 *
 * @param form The form dialog.
 * @param label The field label.
 * @return The label's box.
 */
async function labelBox(form: Locator, label: RegExp): Promise<{ x: number; y: number }> {
	const field = form.getByText(label).first()
	await expect(field, `the form has no field ${label}`).toBeVisible()
	const box = await field.boundingBox()
	return { x: box!.x, y: box!.y }
}

test.beforeAll(async () => {
	admin = await adminContext()
	const settings = await readSettings(admin)
	templatesBefore = settings.ssoLaunchTemplates ?? []
	await writeSettings(admin, {
		ssoLaunchTemplates: [
			...(templatesBefore as unknown[]),
			{ key: SSO_KEY, name: 'E2E IdP', urlTemplate: 'https://idp.e2e.invalid/start/{appId}' },
		],
	})

	own = await setUpOwnDashboard('e2e-tile-form', DASHBOARD)
	await setLanguage(own.owner, 'en')

	const created = await own.owner.api.post(`${API}/dashboard/${own.id}/tile`, {
		data: {
			title: CASE_SYSTEM,
			icon: 'ViewDashboardOutline',
			iconType: 'mdi',
			bgColor: '#1f6feb',
			textColor: '#ffffff',
			linkType: 'sso',
			linkValue: '',
			gridX: 0,
			gridY: 0,
			content: { sso: { template: SSO_KEY, appId: 'zaaksysteem' }, linkTarget: 'new-tab' },
		},
	})
	expect(created.status(), await created.text()).toBe(201)
	caseSystemId = Number((await created.json()).id)

	const imported = await own.owner.api.post(`${API}/dashboard/${own.id}/tiles/import`, {
		data: {
			folders: [{
				name: GROUP,
				bookmarks: GROUP_TILES.map((title) => ({ title, url: `https://example.invalid/${title.toLowerCase().replace(' ', '-')}` })),
			}],
			bookmarks: [],
		},
	})
	expect(imported.status(), await imported.text()).toBe(201)
	const container = (await imported.json()).placements[0]
	groupId = Number(container.id)
	groupContent = container.content
	expect(groupContent.placements).toHaveLength(GROUP_TILES.length)
	// The group starts as placed, so a last-used order can only come from the form.
	const manual = await own.owner.api.put(`${API}/widgets/${groupId}`, { data: { content: { ...groupContent, sortBy: 'manual' } } })
	expect(manual.status(), await manual.text()).toBeLessThan(300)
})

test.afterAll(async () => {
	await tearDownOwnDashboard(own)
	if (admin) {
		await writeSettings(admin, { ssoLaunchTemplates: templatesBefore })
		await admin.dispose()
	}
})

// @e2e tiles::edit-the-case-system-tile
test('Edit tile on Case system shows the fields in board order, with App with single sign-on and New tab chosen', async ({
	browser,
}) => {
	const { context, page } = await open(browser)
	try {
		const form = await openTileForm(page, tileOf(page, caseSystemId), CASE_SYSTEM)

		// Under the title: "<group> · <dashboard>".
		await expect(form.getByText(new RegExp(`· ${DASHBOARD.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`))).toBeVisible()

		// The fields in order: top to bottom, and left to right on a shared row.
		const boxes = []
		for (const label of FIELD_ORDER) {
			boxes.push({ label, ...(await labelBox(form, label)) })
		}
		for (let i = 1; i < boxes.length; i++) {
			const [a, b] = [boxes[i - 1], boxes[i]]
			const sameRow = Math.abs(a.y - b.y) < 4
			expect(sameRow ? b.x > a.x : b.y > a.y, `${b.label} comes before ${a.label}`).toBe(true)
		}
		// Name and Group side by side; Web address and Internal address too.
		expect(Math.abs(boxes[0].y - boxes[1].y)).toBeLessThan(4)
		expect(Math.abs(boxes[3].y - boxes[4].y)).toBeLessThan(4)

		// Tile type: four choice cards, App with single sign-on chosen.
		const type = form.getByRole('radiogroup', { name: 'Tile type' })
		for (const card of ['Web address', 'App with single sign-on', 'Legacy Windows program', 'Program on this computer']) {
			await expect(type.getByRole('radio', { name: new RegExp(`^${card}`) })).toBeVisible()
		}
		await expect(type.getByRole('radio', { name: /^App with single sign-on/ })).toBeChecked()

		// Open in: three choice cards, New tab chosen.
		const openIn = form.getByRole('radiogroup', { name: 'Open in' })
		for (const card of ['Same tab', 'New tab', 'In Launchpad, next to the tiles']) {
			await expect(openIn.getByRole('radio', { name: new RegExp(`^${card}`) })).toBeVisible()
		}
		await expect(openIn.getByRole('radio', { name: /^New tab/ })).toBeChecked()

		await expect(form.getByRole('button', { name: 'Upload another icon' })).toBeVisible()

		// Delete tile at the bottom left, Cancel and Save at the bottom right.
		const del = (await form.getByRole('button', { name: 'Delete tile' }).boundingBox())!
		const cancel = (await form.getByRole('button', { name: 'Cancel' }).boundingBox())!
		const save = (await form.getByRole('button', { name: 'Save' }).boundingBox())!
		expect(del.x).toBeLessThan(cancel.x)
		expect(cancel.x).toBeLessThan(save.x)
		expect(Math.abs(del.y - save.y)).toBeLessThan(8)
	} finally {
		await context.close()
	}
})

// @e2e tiles::sort-a-group-by-use
test('setting Sort order in this group to Last used first orders Work apps by each reader\'s last use', async ({
	browser,
}) => {
	const group = (page: Page): Locator => page.locator(`.grid-stack-item[gs-id="${groupId}"] .container-widget`)
	const drawnOrder = async (page: Page): Promise<string[]> => {
		const tiles = group(page).locator('.container-widget__child')
		await expect(tiles).toHaveCount(GROUP_TILES.length)
		const drawn: Array<{ label: string; top: number; left: number }> = []
		for (const tile of await tiles.all()) {
			const box = (await tile.boundingBox())!
			drawn.push({ label: (await tile.innerText()).trim(), top: Math.round(box.y), left: Math.round(box.x) })
		}
		drawn.sort((a, b) => a.top - b.top || a.left - b.left)
		return drawn.map((entry) => entry.label)
	}

	const { context, page } = await open(browser)
	try {
		const caseTile = group(page).locator('.container-widget__child').filter({ hasText: CASE_SYSTEM })
		const form = await openTileForm(page, caseTile, CASE_SYSTEM)
		await expect(form.getByLabel('Group')).toHaveValue(GROUP)
		const sort = form.getByLabel('Sort order in this group')
		await sort.click()
		await page.getByRole('option', { name: 'Last used first' }).click()
		const saved = page.waitForResponse((res) => res.request().method() === 'PUT' && res.url().includes('/api/widgets/'))
		await form.getByRole('button', { name: 'Save' }).click()
		expect((await saved).status()).toBeLessThan(300)
		expect((await storedContent(own.owner.api, own.id, groupId)).sortBy).toBe('last-used')
	} finally {
		await context.close()
	}

	// Two readers, two browsers: each opens a different tile last.
	for (const last of ['Calendar', 'Mail']) {
		const reader = await open(browser)
		try {
			reader.context.on('page', (popup) => {
				popup.close().catch(() => undefined)
			})
			const other = GROUP_TILES.find((title) => title !== last && title !== CASE_SYSTEM)!
			await group(reader.page).getByText(other, { exact: true }).click()
			await reader.page.waitForTimeout(50)
			await group(reader.page).getByText(last, { exact: true }).click()
			// The imported links open in a new tab (closed above); go back to
			// the workspace either way, in case one navigated this tab.
			await reader.page.goto('/index.php/apps/launchpad/')
			const order = await drawnOrder(reader.page)
			expect(order[0], `the reader who opened ${last} last`).toBe(last)
			expect(order[1]).toBe(other)
		} finally {
			await reader.context.close()
		}
	}
})

test.describe('availability', () => {
	let server: Server | undefined
	let base = ''
	let hostsBefore: string | null = null

	test.beforeAll(async () => {
		server = createServer((_req: IncomingMessage, res: ServerResponse) => {
			res.writeHead(200, { 'Content-Type': 'text/plain' })
			res.end('ok')
		})
		await new Promise<void>((resolve) => server!.listen(0, '0.0.0.0', () => resolve()))
		base = `http://${FIXTURE_HOST}:${(server.address() as AddressInfo).port}`
		const res = await admin.get(`${CONFIG}/healthping_allowed_hosts?format=json`)
		const data = res.ok() ? (await res.json())?.ocs?.data?.data : null
		hostsBefore = typeof data === 'string' && data !== '' ? data : null
		const hosts = hostsBefore ? (JSON.parse(hostsBefore) as string[]) : []
		const write = await admin.post(`${CONFIG}/healthping_allowed_hosts?format=json`, {
			form: { value: JSON.stringify([...new Set([...hosts, FIXTURE_HOST])]) },
		})
		expect(write.ok(), `allow-list -> ${write.status()}`).toBeTruthy()
	})

	test.afterAll(async () => {
		if (hostsBefore === null) {
			await admin.delete(`${CONFIG}/healthping_allowed_hosts?format=json`)
		} else {
			await admin.post(`${CONFIG}/healthping_allowed_hosts?format=json`, { form: { value: hostsBefore } })
		}
		if (server?.listening) {
			server.closeAllConnections()
			await new Promise<void>((resolve) => server!.close(() => resolve()))
		}
	})

	// @e2e tiles::a-reachable-tile
	test('with Check availability on, the form reads "Reachable at the last check, today <time of that check>"', async ({
		browser,
	}) => {
		const content = await storedContent(own.owner.api, own.id, caseSystemId)
		const ping = await own.owner.api.put(`${API}/widgets/${caseSystemId}`, {
			data: { content: { ...content, healthPingEnabled: true, healthUrl: `${base}/ok`, expectedStatus: 200, pingInterval: 60 } },
		})
		expect(ping.status(), await ping.text()).toBeLessThan(300)

		// The last check, as the server holds it.
		let reading: { state?: string; checkedAt?: string } = {}
		await expect.poll(async () => {
			const res = await own.owner.api.get(`${API}/health-ping/${caseSystemId}`)
			reading = res.ok() ? await res.json() : {}
			return reading.state
		}, { timeout: 20_000 }).toBeDefined()
		test.skip(reading.state !== 'online', `Nextcloud never reached the fixture server on ${FIXTURE_HOST}; set HEALTH_PING_FIXTURE_HOST to an address it reaches`)
		expect(reading.checkedAt, 'the reading has no time').toBeTruthy()

		const { context, page } = await open(browser)
		try {
			const time = await page.evaluate((iso) => new Date(iso).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }), reading.checkedAt!)
			const form = await openTileForm(page, tileOf(page, caseSystemId), CASE_SYSTEM)
			const check = form.getByRole('switch', { name: 'Check availability' })
				.or(form.getByRole('checkbox', { name: 'Check availability' }))
			await expect(check.first()).toBeChecked()
			await expect(form.getByText(`Reachable at the last check, today ${time}`)).toBeVisible()
		} finally {
			await context.close()
		}
	})
})
