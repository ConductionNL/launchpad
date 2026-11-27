/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The seven scenarios of the open change icon-only-app-tiles (REQ-ICT-001 to
 * REQ-ICT-004, a delta on `tiles`): an icon-only tile, its live count, its
 * outage dot and its colour from the theme. Gate-19 reads a delta scenario
 * as `tiles::<slug>`, so until this file the seven were uncited.
 *
 * THE FIXTURE
 * ===========
 * Pieter is a throwaway account that reads in Dutch (the scenarios quote
 * "Mijn apps", "Zaaksysteem, 14 open" and "storing sinds"). His one personal
 * dashboard holds a section heading "Mijn apps" over six one-cell tiles with
 * `content.iconOnly: true` and a white MDI glyph, placed through
 * `POST /api/dashboard/{id}/tile` and moved into one row with
 * `PUT /api/widgets/{id}` (support/appTiles.ts). Each test that needs more
 * adds its own tile on a row of its own.
 *
 * OpenRegister is read from the browser (REQ-ICT-002: "asked with the
 * viewer's own session"), so the count test answers that read itself, as
 * dashboard-period-and-download does: the stand-in returns 14 rows for the
 * `dossiq`/`case` query and records what it was asked. The count is
 * OpenRegister's job; LaunchPad's is to ask with `@me` replaced by Pieter
 * and to show the answer, and that is what a red here means. If the count
 * resolver ends up reading OpenRegister server-side, the stand-in must make
 * way for a seeded register.
 *
 * The outage test gives its tile a health ping on a host that never resolves
 * (RFC 2606), allowed on the health-ping allow-list, with the offline
 * threshold at one failure, so the first check fails and the tile is down
 * from that minute. Both settings are put back afterwards.
 *
 * "The administrator switches the instance theme": no LaunchPad or
 * Nextcloud setting defines the `--sc-*` tokens on development, so a theme
 * is what a theme is to the page, a stylesheet that defines them. The test
 * defines `--sc-accent` once, reads the tile, redefines it and reads the tile
 * again, without touching the placement.
 *
 * Not serial on purpose: several tests are expected red, and a serial file
 * would skip every test after the first.
 *
 * EXPECTED RED (the change's tasks.md has the open items)
 * ============
 * open-cases-on-the-zaaksysteem-tile: no count resolver yet; the pill reads
 *   the static `content.badge`.
 * raadsinformatie-is-down: the dot reads the static `content.status`, not
 *   the health ping, and the accessible name says "outage" without a time.
 * pieter-colours-zaaksysteem, the-theme-changes: the tile form offers a free
 *   hex colour, and nothing stores or renders `content.colourToken`.
 * six-app-tiles-under-mijn-apps, four-tiles-per-row-on-a-phone and
 * the-source-app-is-missing are expected green: the icon-only render, the
 * four-column phone grid and the absence of a pill without a source exist.
 *
 * Written in the cloud lane without a live instance; not yet run.
 *
 * Gate-19 @e2e traceability:
 *   @e2e tiles::six-app-tiles-under-mijn-apps
 *   @e2e tiles::four-tiles-per-row-on-a-phone
 *   @e2e tiles::open-cases-on-the-zaaksysteem-tile
 *   @e2e tiles::the-source-app-is-missing
 *   @e2e tiles::raadsinformatie-is-down
 *   @e2e tiles::pieter-colours-zaaksysteem
 *   @e2e tiles::the-theme-changes
 *
 * @spec openspec/changes/archive/2026-11-25-icon-only-app-tiles/specs/tiles/spec.md
 */

import type { APIRequestContext, Browser, BrowserContext, Page, Route } from '@playwright/test'
import type { OwnDashboard } from './support/ownDashboard.ts'

import { expect, test } from '@playwright/test'
import { computedColour, placeOneCellTile, rootToken, storedContent, tileOf } from './support/appTiles.ts'
import { enterEditMode } from './support/dashboardSwitcher.ts'
import { setLanguage } from './support/orWidget.ts'
import { openOwnDashboard, setUpOwnDashboard, tearDownOwnDashboard } from './support/ownDashboard.ts'
import { adminContext, API } from './support/principals.ts'

const STAMP = `${Date.now()}`
const CONFIG = '/ocs/v2.php/apps/provisioning_api/api/v1/config/apps/launchpad'
const WHITE = 'rgb(255, 255, 255)'

/** The six apps under "Mijn apps", with a glyph each. */
const APPS: Array<[string, string]> = [
	['Zaaksysteem', 'ViewDashboardOutline'],
	['Agenda', 'CalendarClock'],
	['Documenten', 'FileExportOutline'],
	['Mail', 'SendOutline'],
	['Raadsinformatie', 'History'],
	['Intranet', 'ViewDashboardOutline'],
]

/** Never resolves (RFC 2606), so every health check of it fails. */
const DOWN_HOST = 'raadsinformatie.e2e.invalid'

let own: OwnDashboard
let admin: APIRequestContext
let headingId = 0
const appIds: number[] = []
/** The next free row for a test's own tile. */
let nextRow = 3

test.setTimeout(150_000)

/**
 * Place a section heading as Pieter.
 *
 * @param title The heading text.
 * @return The placement id.
 */
async function placeHeading(title: string): Promise<number> {
	const res = await own.owner.api.post(`${API}/dashboard/${own.id}/widgets`, {
		data: {
			widgetId: 'section-heading',
			gridX: 0,
			gridY: 0,
			gridWidth: 8,
			gridHeight: 1,
			showTitle: 0,
			content: { title, description: '' },
		},
	})
	expect(res.status(), await res.text()).toBe(201)
	const body = await res.json()
	return Number(body.id ?? body.placement?.id ?? body.data?.id)
}

/**
 * Place one more icon-only tile on a row of its own.
 *
 * @param title The tile title.
 * @param content Extra content keys.
 * @return The placement id.
 */
async function extraTile(title: string, content: Record<string, unknown> = {}): Promise<number> {
	const row = nextRow
	nextRow += 1
	return placeOneCellTile(own.owner.api, own.id, {
		title,
		x: 0,
		y: row,
		icon: 'ViewDashboardOutline',
		iconType: 'mdi',
		content: { iconOnly: true, ...content },
	})
}

/**
 * An app value of LaunchPad, or null when unset.
 *
 * @param key The app config key.
 * @return The stored string, or null.
 */
async function readAppValue(key: string): Promise<string | null> {
	const res = await admin.get(`${CONFIG}/${key}?format=json`)
	if (!res.ok()) {
		return null
	}
	const data = (await res.json())?.ocs?.data
	return typeof data?.data === 'string' && data.data !== '' ? data.data : null
}

/**
 * Write an app value of LaunchPad, or delete it when `value` is null.
 *
 * @param key The app config key.
 * @param value The value, or null to delete.
 * @return nothing.
 */
async function writeAppValue(key: string, value: string | null): Promise<void> {
	const res = value === null
		? await admin.delete(`${CONFIG}/${key}?format=json`)
		: await admin.post(`${CONFIG}/${key}?format=json`, { form: { value } })
	expect(res.ok(), `set ${key} -> ${res.status()}`).toBeTruthy()
}

/**
 * Open Pieter's dashboard on a desktop.
 *
 * @param browser The browser.
 * @return The context and page.
 */
async function open(browser: Browser): Promise<{ context: BrowserContext; page: Page }> {
	const items = await own.owner.api.get(`${API}/dashboard/${own.id}`)
	const count = ((await items.json()).placements ?? []).length
	return openOwnDashboard(browser, own, count)
}

test.beforeAll(async () => {
	admin = await adminContext()
	own = await setUpOwnDashboard('e2e-ict-pieter', `Mijn werkplek ${STAMP}`)
	await setLanguage(own.owner, 'nl')
	headingId = await placeHeading('Mijn apps')
	for (const [index, [title, icon]] of APPS.entries()) {
		appIds.push(await placeOneCellTile(own.owner.api, own.id, {
			title,
			x: index,
			y: 1,
			icon,
			iconType: 'mdi',
			bgColor: '#1f6feb',
			content: { iconOnly: true },
		}))
	}
})

test.afterAll(async () => {
	await tearDownOwnDashboard(own)
	await admin?.dispose()
})

// @e2e tiles::six-app-tiles-under-mijn-apps
test('six icon-only tiles under "Mijn apps" are coloured squares with a white icon, no visible name, a tooltip and an accessible name', async ({
	browser,
}) => {
	const { context, page } = await open(browser)
	try {
		await expect(page.locator(`.grid-stack-item[gs-id="${headingId}"]`)).toContainText('Mijn apps')
		for (const [index, id] of appIds.entries()) {
			const title = APPS[index][0]
			const tile = tileOf(page, id)
			await expect(tile).toBeVisible()

			// A square: as wide as it is tall, give or take a pixel of rounding.
			const box = await tile.boundingBox()
			expect(box, `${title} has no box`).not.toBeNull()
			expect(Math.abs(box!.width - box!.height), `${title} is not square`).toBeLessThanOrEqual(2)

			// In a solid colour, not transparent.
			const background = await tile.evaluate((el) => getComputedStyle(el).backgroundColor)
			expect(background, `${title} has no tile colour`).not.toMatch(/rgba\(0, 0, 0, 0\)|transparent/)

			// A white icon, and no printed name anywhere in the tile.
			const icon = tile.locator('.tile-widget__icon')
			await expect(icon).toBeVisible()
			const iconColour = await icon.evaluate((el) => {
				const glyph = el.querySelector('svg') ?? el
				return getComputedStyle(glyph).color
			})
			expect(iconColour, `${title}'s icon is not white`).toBe(WHITE)
			expect((await tile.innerText()).trim(), `${title} prints a name`).toBe('')
		}

		// Hovering Zaaksysteem shows the tooltip "Zaaksysteem", and a screen
		// reader reads its name.
		const zaak = tileOf(page, appIds[0])
		const link = zaak.getByRole('link', { name: 'Zaaksysteem', exact: true })
		await expect(link).toBeVisible()
		await link.hover()
		const tooltip = page.getByRole('tooltip', { name: 'Zaaksysteem' })
		if (await tooltip.count() > 0) {
			await expect(tooltip).toBeVisible()
		} else {
			// A native tooltip is the element's title.
			await expect(link).toHaveAttribute('title', 'Zaaksysteem')
		}
	} finally {
		await context.close()
	}
})

// @e2e tiles::four-tiles-per-row-on-a-phone
test('at 390 px the six app tiles stand four to a row', async ({ browser }) => {
	const { context, page } = await open(browser)
	try {
		await page.setViewportSize({ width: 390, height: 844 })
		await page.reload()
		await expect(tileOf(page, appIds[5])).toBeVisible({ timeout: 30_000 })
		await expect
			.poll(async () => {
				const boxes = await Promise.all(appIds.map((id) => tileOf(page, id).boundingBox()))
				const tops = boxes.map((b) => Math.round(b?.y ?? -1))
				return { firstRow: new Set(tops.slice(0, 4)).size, fifthBelow: tops[4] > tops[0], sixthWithFifth: tops[5] === tops[4] }
			}, { timeout: 10_000 })
			.toEqual({ firstRow: 1, fifthBelow: true, sixthWithFifth: true })
		const first = (await tileOf(page, appIds[0]).boundingBox())!
		const fourth = (await tileOf(page, appIds[3]).boundingBox())!
		const fifth = (await tileOf(page, appIds[4]).boundingBox())!
		expect(fourth.x).toBeGreaterThan(first.x + first.width * 2)
		expect(Math.abs(fifth.x - first.x)).toBeLessThan(2)
	} finally {
		await context.close()
	}
})

// @e2e tiles::open-cases-on-the-zaaksysteem-tile
test('a register count source shows the pill "14" for the 14 open cases assigned to Pieter', async ({
	browser,
}) => {
	const id = await extraTile(`Zaaksysteem telling ${STAMP}`, {
		countSource: {
			type: 'register',
			register: 'dossiq',
			schema: 'case',
			filter: { assignee: '@me', isFinalStatus: false },
		},
	})
	const asked: string[] = []
	const { context, page } = await open(browser)
	try {
		await context.route('**/apps/openregister/**', async (route: Route) => {
			const url = decodeURIComponent(route.request().url())
			if (!url.includes('dossiq')) {
				await route.fallback()
				return
			}
			asked.push(url)
			const results = Array.from({ length: 14 }, (_, i) => ({ id: `case-${i}`, assignee: own.owner.username, isFinalStatus: false }))
			await route.fulfill({
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify({ results, total: 14, count: 14, page: 1, pages: 1, limit: 14 }),
			})
		})
		await page.reload()
		const tile = tileOf(page, id)
		await expect(tile).toBeVisible({ timeout: 30_000 })
		await expect(tile.locator('[data-testid="tile-badge"]')).toHaveText('14', { timeout: 15_000 })
		await expect(tile.getByRole('link', { name: /, 14 open$/ })).toBeVisible()

		// Asked as Pieter: `@me` is replaced by his account, the filter is kept.
		expect(asked.length, 'the tile never asked OpenRegister for its count').toBeGreaterThan(0)
		expect(asked.some((url) => url.includes(own.owner.username)), asked.join('\n')).toBe(true)
		expect(asked.some((url) => url.includes('@me')), 'the viewer placeholder reached OpenRegister').toBe(false)
		expect(asked.some((url) => /isFinalStatus\]?=false/.test(url)), asked.join('\n')).toBe(true)
	} finally {
		await context.close()
	}
})

// @e2e tiles::the-source-app-is-missing
test('a count source whose register does not exist shows no pill and no error', async ({
	browser,
}) => {
	const title = `Zaaksysteem zonder register ${STAMP}`
	const id = await extraTile(title, {
		countSource: {
			type: 'register',
			register: `e2e-missing-${STAMP}`,
			schema: 'case',
			filter: { assignee: '@me', isFinalStatus: false },
		},
	})
	const { context, page } = await open(browser)
	try {
		const tile = tileOf(page, id)
		await expect(tile).toBeVisible()
		// Long enough for a count read and its failure to land.
		await page.waitForTimeout(3_000)
		await expect(tile.locator('[data-testid="tile-badge"]')).toHaveCount(0)
		await expect(tile.locator('[data-testid="tile-outage"]')).toHaveCount(0)
		await expect(tile.getByRole('link', { name: title, exact: true })).toBeVisible()
		await expect(tile.getByRole('alert')).toHaveCount(0)
		await expect(page.locator('.toast-error')).toHaveCount(0)
		expect((await tile.innerText()).trim()).toBe('')
	} finally {
		await context.close()
	}
})

test.describe('outage', () => {
	let hostsBefore: string | null = null
	let offlineAfterBefore: string | null = null

	test.beforeAll(async () => {
		hostsBefore = await readAppValue('healthping_allowed_hosts')
		offlineAfterBefore = await readAppValue('healthping_offline_after_failures')
		const hosts = hostsBefore ? (JSON.parse(hostsBefore) as string[]) : []
		await writeAppValue('healthping_allowed_hosts', JSON.stringify([...new Set([...hosts, DOWN_HOST])]))
		await writeAppValue('healthping_offline_after_failures', '1')
	})

	test.afterAll(async () => {
		await writeAppValue('healthping_allowed_hosts', hostsBefore)
		await writeAppValue('healthping_offline_after_failures', offlineAfterBefore)
	})

	// @e2e tiles::raadsinformatie-is-down
	test('a tile whose health ping fails shows the red dot, no count, and "Raadsinformatie, storing sinds <time>"', async ({
		browser,
	}) => {
		const id = await extraTile('Raadsinformatie', {
			// A count that would show if the outage did not replace it.
			countSource: { type: 'nc-widget', widgetId: 'activity' },
			healthPingEnabled: true,
			healthUrl: `https://${DOWN_HOST}/health`,
			expectedStatus: 200,
			pingInterval: 60,
		})
		const { context, page } = await open(browser)
		try {
			// The minutes the first failed check can carry, in the browser's clock.
			const minutes = await page.evaluate(() => {
				const now = Date.now()
				return [-2, -1, 0, 1, 2].map((offset) => new Date(now + offset * 60_000)
					.toLocaleTimeString('nl-NL', { hour: '2-digit', minute: '2-digit' }))
			})
			await page.reload()
			const tile = tileOf(page, id)
			await expect(tile).toBeVisible({ timeout: 30_000 })
			await expect(tile.locator('[data-testid="tile-outage"]')).toBeVisible({ timeout: 20_000 })
			await expect(tile.locator('[data-testid="tile-badge"]')).toHaveCount(0)
			const link = tile.getByRole('link', { name: /^Raadsinformatie, storing sinds \d{1,2}:\d{2}$/ })
			await expect(link).toBeVisible()
			const name = (await link.getAttribute('aria-label')) ?? (await link.innerText())
			const since = name.replace(/^Raadsinformatie, storing sinds /, '')
			expect(minutes, `"${name}" names no time of this run's first failed check`).toContain(since.padStart(5, '0'))
		} finally {
			await context.close()
		}
	})
})

// @e2e tiles::pieter-colours-zaaksysteem
test('choosing the primary swatch in the tile form stores colourToken "--sc-primary" and renders the primary colour with a white icon', async ({
	browser,
}) => {
	const id = await extraTile(`Zaaksysteem kleur ${STAMP}`)
	const { context, page } = await open(browser)
	try {
		await enterEditMode(page)
		await tileOf(page, id).locator('.tile-widget__edit button').first().click()
		await page.getByRole('menuitem', { name: /tegel bewerken|edit tile/i }).click()
		const form = page.getByRole('dialog').filter({ has: page.getByRole('radiogroup') }).last()
		await expect(form).toBeVisible()
		// The tile colour, not the text colour group beside it (app-link-tile-widget
		// REQ-APPTILE-005 added Text colour to the same form).
		const swatches = form.getByRole('radiogroup', { name: /^(tegelkleur|tile colou?r)$/i })
		// Eight theme swatches; the board's "Own colour" choice is a ninth
		// radio in the same group that keeps the colour pickers.
		const themeSwatches = swatches.getByRole('radio').and(page.locator('.tile-editor__swatch-input'))
		await expect(themeSwatches).toHaveCount(8)
		await expect(swatches.getByRole('radio', { name: /eigen kleur|own colou?r/i })).toHaveCount(1)
		// Every swatch has a name for a screen reader.
		for (const radio of await themeSwatches.all()) {
			expect((await radio.getAttribute('aria-label')) ?? (await radio.evaluate((el) => (el as HTMLInputElement).labels?.[0]?.textContent ?? '')))
				.not.toBe('')
		}
		await swatches.getByRole('radio', { name: /primair|primary|hoofdkleur/i }).first().check()
		const saved = page.waitForResponse((res) => res.request().method() === 'PUT' && res.url().includes(`/api/widgets/${id}`))
		await form.getByRole('button', { name: /^(opslaan|save)$/i }).click()
		expect((await saved).status()).toBeLessThan(300)

		expect((await storedContent(own.owner.api, own.id, id)).colourToken).toBe('--sc-primary')

		await page.getByRole('button', { name: /^(klaar|done)$/i }).click().catch(() => undefined)
		const tile = tileOf(page, id)
		// The tile renders through var(--sc-primary, #3669a5): on a theme that
		// defines the token that value, on an unthemed page the fallback
		// (src/utils/tileColour.js).
		const primary = (await rootToken(page, '--sc-primary')) || '#3669a5'
		await expect
			.poll(() => tile.evaluate((el) => getComputedStyle(el).backgroundColor))
			.toBe(await computedColour(page, primary))
		const iconColour = await tile.locator('.tile-widget__icon').evaluate((el) => getComputedStyle(el.querySelector('svg') ?? el).color)
		expect(iconColour).toBe(WHITE)
	} finally {
		await context.close()
	}
})

// @e2e tiles::the-theme-changes
test('a tile with colourToken "--sc-accent" takes the new accent colour when the theme changes, without being edited', async ({
	browser,
}) => {
	const id = await extraTile(`Accent ${STAMP}`, { colourToken: '--sc-accent' })
	const before = await storedContent(own.owner.api, own.id, id)
	const { context, page } = await open(browser)
	try {
		const tile = tileOf(page, id)
		await expect(tile).toBeVisible()
		const theme = async (accent: string): Promise<void> => {
			await page.evaluate((value) => {
				let style = document.getElementById('e2e-theme') as HTMLStyleElement | null
				if (style === null) {
					style = document.createElement('style')
					style.id = 'e2e-theme'
					document.head.appendChild(style)
				}
				style.textContent = `:root { --sc-accent: ${value} !important; }`
			}, accent)
		}

		await theme('#b45309')
		await expect.poll(() => tile.evaluate((el) => getComputedStyle(el).backgroundColor)).toBe('rgb(180, 83, 9)')

		await theme('#7c3aed')
		await expect.poll(() => tile.evaluate((el) => getComputedStyle(el).backgroundColor)).toBe('rgb(124, 58, 237)')

		// Nothing was edited: the placement still stores the token, not a colour.
		expect(await storedContent(own.owner.api, own.id, id)).toEqual(before)
	} finally {
		await context.close()
	}
})
