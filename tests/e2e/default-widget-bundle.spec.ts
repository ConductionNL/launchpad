/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the default widget bundle
 * (openspec/specs/default-widget-bundle), written for
 * e2e-coverage-programme task 3.18.
 *
 * A throwaway non-admin account creates a personal dashboard through
 * `POST /api/dashboard`, the request the spec names, and then opens it the
 * way a person does: the grid must show the three seeded tiles and the files
 * widget. The loading shim is observed by holding the active-dashboard read
 * back until the shim has been asserted.
 *
 * Gate-19 @e2e traceability:
 *   @e2e default-widget-bundle::new-dashboard-ships-four-seeded-placements
 *   @e2e default-widget-bundle::preset-tiles-serialize-flat-tile-columns
 *   @e2e default-widget-bundle::create-response-includes-placements-envelope
 *   @e2e default-widget-bundle::loading-shim-shown-during-initial-fetch
 *
 * @spec openspec/specs/default-widget-bundle/spec.md
 */

import type { APIRequestContext, Browser, Page } from '@playwright/test'

import { expect, test } from '@playwright/test'
import {
	deprovisionUser,
	loginAs,
	provisionThrowawayUser,
} from './fixtures/secondary-user.ts'
import { adminContext, contextFor } from './support/principals.ts'

const API = '/index.php/apps/launchpad/api'
const RUN = Date.now()
const NAME = `E2E Bundle ${RUN}`

/** The four seeded placements of REQ-DWB-001, in seed order. */
const BUNDLE = [
	{
		widgetId: 'tile',
		tileTitle: 'Conduction',
		link: 'https://conduction.nl',
		iconType: 'url',
		grid: [0, 0, 4, 3],
	},
	{
		widgetId: 'tile',
		tileTitle: 'Sendent',
		link: 'https://sendent.com',
		iconType: 'url',
		grid: [4, 0, 4, 3],
	},
	{
		widgetId: 'tile',
		tileTitle: 'Nextcloud',
		link: 'https://nextcloud.com',
		iconType: 'class',
		grid: [8, 0, 4, 3],
	},
	{
		widgetId: 'files',
		tileTitle: null,
		link: null,
		iconType: null,
		grid: [0, 3, 12, 5],
	},
]

type Account = { username: string; password: string }
type Placement = Record<string, unknown>

let account: Account | null = null
let created: { status: number; body: Record<string, unknown> }

/**
 * An API context as the administrator, or as the given account. It never
 * carries the admin session cookie, so a call made for the account is made
 * as the account (see support/principals.ts).
 *
 * @param who the account; the administrator when omitted.
 * @return the context; the caller disposes it.
 */
async function apiAs(who?: Account): Promise<APIRequestContext> {
	return who ? contextFor(who.username, who.password) : adminContext()
}

/**
 * The placements of the create response, in grid order (row, then column).
 *
 * @return the placements.
 */
function placements(): Placement[] {
	const list = (created.body.placements ?? []) as Placement[]
	return [...list].sort(
		(a, b) =>
			Number(a.gridY) - Number(b.gridY) || Number(a.gridX) - Number(b.gridX),
	)
}

/**
 * Log in as the account and open LaunchPad.
 *
 * @param browser the browser.
 * @return the context and page.
 */
async function openLaunchpad(browser: Browser) {
	const session = await loginAs(browser, account!.username, account!.password)
	await session.page.goto('/index.php/apps/launchpad')
	return session
}

/**
 * The grid cell of a placement.
 *
 * @param page the page.
 * @param placement the placement.
 * @return the cell.
 */
function cell(page: Page, placement: Placement) {
	return page.locator(`[data-placement-id="${placement.id}"]`)
}

test.beforeAll(async () => {
	const admin = await apiAs()
	try {
		const enable = await admin.put(`${API}/admin/settings`, {
			data: { allowUserDash: true },
		})
		expect(enable.status(), await enable.text()).toBeLessThan(300)
	} finally {
		await admin.dispose()
	}
	account = await provisionThrowawayUser('e2e-bundle')
	const api = await apiAs(account)
	try {
		const res = await api.post(`${API}/dashboard`, { data: { name: NAME } })
		created = { status: res.status(), body: await res.json() }
		const dash = (created.body.dashboard ?? {}) as Record<string, unknown>
		// Seeding only: make the new dashboard the one LaunchPad opens.
		const active = await api.post(`${API}/dashboards/active`, {
			data: { uuid: dash.uuid },
		})
		expect(active.status(), await active.text()).toBeLessThan(300)
	} finally {
		await api.dispose()
	}
})

test.afterAll(async () => {
	if (account) {
		await deprovisionUser(account.username)
	}
})

test.describe.serial('default widget bundle (REQ-DWB-001, 002, 005, 006)', () => {
	// @e2e default-widget-bundle::new-dashboard-ships-four-seeded-placements
	test('a new dashboard ships the four seeded placements and the grid shows them', async ({
		browser,
	}) => {
		expect(created.status).toBe(201)
		const list = placements()
		expect(list).toHaveLength(4)
		list.forEach((placement, index) => {
			const want = BUNDLE[index]
			expect(placement.widgetId).toBe(want.widgetId)
			expect([
				placement.gridX,
				placement.gridY,
				placement.gridWidth,
				placement.gridHeight,
			]).toEqual(want.grid)
			expect(placement.tileTitle ?? null).toBe(want.tileTitle)
		})

		const { context, page } = await openLaunchpad(browser)
		try {
			for (const [index, placement] of list.entries()) {
				const want = BUNDLE[index]
				await expect(cell(page, placement)).toBeVisible({ timeout: 30_000 })
				if (want.widgetId === 'tile') {
					// A `tile` placement, preset ones included, renders as the
					// launcher tile itself (Views.vue isTilePlacement → TileWidget).
					await expect(
						cell(page, placement).locator('.tile-widget__title'),
					).toHaveText(want.tileTitle!)
					await expect(
						cell(page, placement).locator('a.tile-widget__link'),
					).toHaveAttribute(
						'href',
						new RegExp(`^${want.link!.replace(/[.]/g, '\\.')}/?$`),
					)
				}
			}
		} finally {
			await context.close()
		}
	})

	// @e2e default-widget-bundle::preset-tiles-serialize-flat-tile-columns
	test('the seeded tiles carry tileType preset and the flat tile columns', async () => {
		const tiles = placements().filter(
			(placement) => placement.widgetId === 'tile',
		)
		expect(tiles).toHaveLength(3)
		tiles.forEach((tile, index) => {
			const want = BUNDLE[index]
			expect(tile.tileType).toBe('preset')
			expect(tile.tileTitle).toBe(want.tileTitle)
			expect(tile.tileLinkType).toBe('url')
			expect(tile.tileLinkValue).toBe(want.link)
			expect(tile.tileIconType).toBe(want.iconType)
			expect(String(tile.tileIcon ?? '')).not.toBe('')
		})
		// The Nextcloud tile names a CSS class, the others a bundled PNG.
		expect(tiles[2].tileIcon).toBe('icon-nextcloud')
		expect(String(tiles[0].tileIcon)).toMatch(/conduction-logo\.png$/)
		expect(String(tiles[1].tileIcon)).toMatch(/sendent-logo\.png$/)
		// The files widget is no tile: its tile columns stay empty.
		expect(placements()[3].tileType ?? null).toBeNull()
	})

	// @e2e default-widget-bundle::create-response-includes-placements-envelope
	test('the create response has the same dashboard and placements envelope as GET /api/dashboard', async () => {
		expect(Object.keys(created.body)).toEqual(
			expect.arrayContaining(['dashboard', 'placements']),
		)
		expect(Array.isArray(created.body.placements)).toBe(true)

		const api = await apiAs(account!)
		try {
			const res = await api.get(`${API}/dashboard`)
			expect(res.status(), await res.text()).toBe(200)
			const active = await res.json()
			expect(Object.keys(active)).toEqual(
				expect.arrayContaining(['dashboard', 'placements']),
			)
			const dash = created.body.dashboard as Record<string, unknown>
			expect((active.dashboard as Record<string, unknown>).uuid).toBe(
				dash.uuid,
			)
			expect(
				(active.placements as Placement[]).map((p) => p.id).sort(),
			).toEqual(
				placements()
					.map((p) => p.id)
					.sort(),
			)
		} finally {
			await api.dispose()
		}
	})

	// @e2e default-widget-bundle::loading-shim-shown-during-initial-fetch
	test('the loading shim, not the empty state, shows while the dashboard is fetched', async ({
		browser,
	}) => {
		const session = await loginAs(browser, account!.username, account!.password)
		const { context, page } = session
		try {
			let release!: () => void
			const held = new Promise<void>((resolve) => {
				release = resolve
			})
			let seen = false
			await page.route(
				/\/apps\/launchpad\/api\/dashboard(\?.*)?$/,
				async (route) => {
					if (route.request().method() !== 'GET') {
						return route.continue()
					}
					seen = true
					await held
					return route.continue()
				},
			)

			await page.goto('/index.php/apps/launchpad')
			await expect.poll(() => seen, { timeout: 30_000 }).toBe(true)
			await expect(
				page.locator('.launchpad-loading .loading-icon'),
			).toBeVisible()
			await expect(page.locator('.launchpad-empty')).toHaveCount(0)
			await expect(page.getByText('No dashboard yet')).toHaveCount(0)

			release()
			await expect(page.locator('.launchpad-loading')).toHaveCount(0, {
				timeout: 30_000,
			})
			await expect(cell(page, placements()[0])).toBeVisible({
				timeout: 30_000,
			})
			await expect(page.locator('.launchpad-empty')).toHaveCount(0)
		} finally {
			await context.close()
		}
	})
})
