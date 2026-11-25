/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The workspace is one canvas and widgets are found through the "Add widget"
 * picker (openspec/specs/widgets REQ-WDG-027), and that picker narrows its
 * lists as you type (REQ-WDG-028). Written for e2e-coverage-programme,
 * lane e3.
 *
 * The REQ-WDG-028 tests fix what the picker is given: `GET /api/widgets`,
 * `GET /api/tiles/discover` and `GET /api/catalogue` are answered with known
 * entries so "cal" has something to match on an instance whose installed
 * widgets nobody controls. REQ-WDG-027's discovery test keeps the server's
 * real widget list and adds one entry of this run to it, so it can place
 * something it knows the name of.
 *
 * Gate-19 @e2e traceability:
 *   @e2e widgets::workspace-shows-only-the-dashboard-canvas
 *   @e2e widgets::widget-discovery-happens-through-the-picker
 *   @e2e widgets::typing-narrows-the-list
 *   @e2e widgets::clearing-the-search
 *   @e2e widgets::nothing-matches
 *
 * @spec openspec/specs/widgets/spec.md
 */

import type { Locator, Page } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { MORE_MENU, openDashboardSwitcher } from './support/dashboardSwitcher.ts'
import { catalogueFromApi } from './support/widgetCatalogue.ts'
import {
	adminApi,
	gridItem,
	isWidgetCatalogue,
	openWorkspace,
	Seeds,
} from './support/widgetPlacements.ts'

const RUN = Date.now()

let seeds: Seeds

/**
 * A widget descriptor in the shape `GET /api/widgets` answers with.
 *
 * @param id the widget id.
 * @param title its title.
 * @param order its sort order.
 * @return the descriptor.
 */
function descriptor(
	id: string,
	title: string,
	order: number,
): Record<string, unknown> {
	return {
		id,
		title,
		order,
		iconClass: 'icon-category-dashboard',
		iconUrl: '',
		widgetUrl: null,
		itemIconsRound: false,
		itemApiVersions: [],
		reloadInterval: 0,
		buttons: [],
	}
}

const WIDGETS = [
	descriptor(`e2e-cal-${RUN}`, 'Calendar', 1),
	descriptor(`e2e-notes-${RUN}`, 'Notes', 2),
	descriptor(`e2e-deck-${RUN}`, 'Upcoming cards', 3),
]
const APPS = [
	{
		appId: 'calendar',
		name: 'Calendar',
		href: '/index.php/apps/calendar/',
		icon: '',
	},
	{ appId: 'files', name: 'Files', href: '/index.php/apps/files/', icon: '' },
]

/**
 * Answer the three lists the picker shows with the fixed entries above.
 *
 * @param page the page.
 * @return nothing.
 */
async function stagePickerLists(page: Page): Promise<void> {
	await catalogueFromApi(page)
	await page.route(
		(url) => isWidgetCatalogue(url),
		(route) =>
			route.fulfill({
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify(WIDGETS),
			}),
	)
	await page.route(
		(url) => url.pathname.endsWith('/apps/launchpad/api/tiles/discover'),
		(route) =>
			route.fulfill({
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify({ apps: APPS }),
			}),
	)
	await page.route(
		(url) => url.pathname.endsWith('/apps/launchpad/api/catalogue'),
		(route) =>
			route.fulfill({
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify({ apps: [] }),
			}),
	)
}

/**
 * Open "Add widget" from the More menu, the way a person does.
 *
 * @param page the page, on the workspace.
 * @return the picker.
 */
async function openPicker(page: Page): Promise<Locator> {
	await page
		.locator(`${MORE_MENU} [data-testid="launchpad-more-menu-trigger"]`)
		.click()
	await page.locator('[data-testid="launchpad-more-menu-item-add-widget"]').click()
	const picker = page.locator('.widget-picker')
	await expect(picker).toBeVisible({ timeout: 15_000 })
	return picker
}

/**
 * The titles of the widgets the picker lists (not the app sections).
 *
 * @param picker the picker.
 * @return the titles, in order.
 */
function widgetTitles(picker: Locator): Locator {
	return picker.locator(
		'[data-testid="widget-picker-section-apps"] button.widget-picker__widget .widget-picker__widget-title',
	)
}

/**
 * The names of the discovered apps the picker lists.
 *
 * @param picker the picker.
 * @return the names, in order.
 */
function appNames(picker: Locator): Locator {
	return picker.locator(
		'[data-testid="widget-picker-section-tiles"] .widget-picker__widget-title',
	)
}

test.beforeAll(async () => {
	seeds = new Seeds(await adminApi())
})

test.afterAll(async () => {
	await seeds?.cleanup()
})

test.describe('workspace canvas and the widget picker (REQ-WDG-027, REQ-WDG-028)', () => {
	test.describe.configure({ timeout: 120_000 })

	// @e2e widgets::workspace-shows-only-the-dashboard-canvas
	test('the workspace renders the dashboard canvas and no catalog region or mode switch', async ({
		page,
	}) => {
		const dash = await seeds.dashboard(`E2E canvas ${RUN}`)
		const placement = await seeds.widget(dash, {
			widgetId: 'label',
			content: { text: `E2E canvas label ${RUN}` },
		})
		await openWorkspace(page, placement.id)

		const region = page.locator('[data-testid="launchpad-dashboard-region"]')
		await expect(region).toHaveCount(1)
		await expect(region).toHaveAttribute('data-dashboard-uuid', dash.uuid)
		await expect(region.locator('.grid-stack')).toBeVisible()
		await expect(
			region.locator(
				`.launchpad-grid-item[data-placement-id="${placement.id}"]`,
			),
		).toBeVisible()

		// The removed Catalog region (openspec/changes/archive/2026-06-21-deprecate-catalog).
		await expect(
			page.locator(
				'[class*="catalog-view"], [class*="CatalogView"], [data-testid*="catalog-view"]',
			),
		).toHaveCount(0)
		await expect(page.getByRole('heading', { name: /^catalog$/i })).toHaveCount(
			0,
		)

		await openDashboardSwitcher(page)
		const sidebar = page.locator('.dashboard-switcher-sidebar.open')
		await expect(
			sidebar.locator('[class*="__modes"], [class*="__mode"]'),
		).toHaveCount(0)
		await expect(
			sidebar.getByRole('button', { name: /^catalog$/i }),
		).toHaveCount(0)
		await expect(sidebar.getByRole('tab')).toHaveCount(0)
		await expect(sidebar.getByText(/^catalog$/i)).toHaveCount(0)
	})

	// @e2e widgets::widget-discovery-happens-through-the-picker
	test('"Add widget" lists every available widget and places the chosen one on the active dashboard', async ({
		page,
	}) => {
		const dash = await seeds.dashboard(`E2E discovery ${RUN}`)
		const extra = descriptor(
			`e2e-discovery-${RUN}`,
			`E2E discovery widget ${RUN}`,
			999,
		)
		let available: Array<Record<string, unknown>> = []
		await catalogueFromApi(page)
		await page.route(
			(url) => isWidgetCatalogue(url),
			async (route) => {
				const real = await route.fetch()
				const body = await real.json()
				const list = Array.isArray(body) ? body : Object.values(body ?? {})
				available = [...list, extra] as Array<Record<string, unknown>>
				await route.fulfill({
					response: real,
					body: JSON.stringify(available),
				})
			},
		)
		await openWorkspace(page)
		const picker = await openPicker(page)
		await expect(page.locator('.launchpad-edit-mode')).toHaveCount(1)

		const titles = widgetTitles(picker)
		await expect(titles).toHaveCount(available.length)
		const listed = await titles.allTextContents()
		expect(listed.map((t) => t.trim()).sort()).toEqual(
			available.map((w) => String(w.title)).sort(),
		)

		const created = page.waitForResponse(
			(res) =>
				res.request().method() === 'POST'
				&& new URL(res.url()).pathname.endsWith(
					`/apps/launchpad/api/dashboard/${dash.id}/widgets`,
				),
		)
		await picker
			.locator('[data-testid="widget-picker-section-apps"] button.widget-picker__widget', {
				hasText: String(extra.title),
			})
			.click()
		const response = await created
		expect(response.status()).toBe(201)
		const placement = await response.json()
		expect(placement.widgetId).toBe(extra.id)

		const stored = (await seeds.placements(dash)).filter(
			(p) => p.widgetId === extra.id,
		)
		expect(stored).toHaveLength(1)
		await page.keyboard.press('Escape')
		await expect(gridItem(page, Number(placement.id))).toHaveCount(1, {
			timeout: 10_000,
		})
	})

	// @e2e widgets::typing-narrows-the-list
	test('typing "cal" narrows the widgets and the apps to the ones whose title contains it', async ({
		page,
	}) => {
		await seeds.dashboard(`E2E search narrow ${RUN}`)
		await stagePickerLists(page)
		await openWorkspace(page)
		const picker = await openPicker(page)
		await expect(widgetTitles(picker)).toHaveText([
			'Calendar',
			'Notes',
			'Upcoming cards',
		])

		await picker.locator('.widget-picker__search input').fill('cal')
		await expect(widgetTitles(picker)).toHaveText(['Calendar'])
		await expect(appNames(picker)).toHaveText(['Calendar'])
		await expect(
			picker.locator('[data-testid="widget-picker-office-pack"]'),
		).toHaveCount(0)
		// The approved-catalogue cards share the App tiles section; none matches.
		await expect(
			picker.locator('[data-testid^="widget-picker-catalogue-"]'),
		).toHaveCount(0)
	})

	// @e2e widgets::clearing-the-search
	test('clearing the search box brings every widget and app back', async ({
		page,
	}) => {
		await seeds.dashboard(`E2E search clear ${RUN}`)
		await stagePickerLists(page)
		await openWorkspace(page)
		const picker = await openPicker(page)
		const search = picker.locator('.widget-picker__search input')

		await search.fill('cal')
		await expect(widgetTitles(picker)).toHaveText(['Calendar'])
		await expect(appNames(picker)).toHaveText(['Calendar'])

		await search.fill('')
		await expect(widgetTitles(picker)).toHaveText([
			'Calendar',
			'Notes',
			'Upcoming cards',
		])
		// Board LpWidgetKiezen ends the app tiles with "Custom tile", which a
		// search for "cal" left out.
		await expect(appNames(picker)).toHaveText(['Calendar', 'Files', 'Custom tile'])
		await expect(
			picker.locator('[data-testid="widget-picker-office-pack"]'),
		).toBeVisible()
	})

	// @e2e widgets::nothing-matches
	test('a search that matches nothing says so and shows no empty list', async ({
		page,
	}) => {
		await seeds.dashboard(`E2E search none ${RUN}`)
		await stagePickerLists(page)
		await openWorkspace(page)
		const picker = await openPicker(page)

		await picker.locator('.widget-picker__search input').fill('zzz')
		await expect(
			picker.getByText('No widgets found', { exact: true }),
		).toBeVisible()
		await expect(picker.locator('button.widget-picker__widget')).toHaveCount(0)
		await expect(
			picker.locator('[data-testid="widget-picker-section-tiles"]'),
		).toHaveCount(0)
		await expect(
			picker.locator('[data-testid="widget-picker-office-pack"]'),
		).toHaveCount(0)
	})
})
