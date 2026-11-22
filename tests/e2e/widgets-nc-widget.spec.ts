/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The `nc-widget` placement type (openspec/specs/widgets REQ-WDG-018..021):
 * the sub-form's widget picker, native-callback versus API-list rendering,
 * the two display modes and the items request. Written for
 * e2e-coverage-programme, lane e3.
 *
 * WHAT IS REAL AND WHAT IS STAGED
 * ===============================
 * The placements are real rows, added through `POST /api/dashboard/{id}/widgets`
 * and rendered by the real workspace. Two things are staged because the CI
 * instance (NC 32 + openregister) has neither:
 *  - a native dashboard bundle: an init script registers a render callback
 *    through `OCA.Dashboard.register`, the very function the LaunchPad widget
 *    bridge (src/services/widgetBridge.js) installs for Nextcloud bundles;
 *  - widget items: the renderer (nextcloud-vue CnNcWidgetWidget) reads them
 *    from Nextcloud's `GET /ocs/v2.php/apps/dashboard/api/v2/widget-items`,
 *    which is answered here with fixed items for a widget id of this run.
 *
 * Gate-19 @e2e traceability:
 *   @e2e widgets::form-picker-lists-discovered-widgets
 *   @e2e widgets::native-callback-already-registered-at-mount
 *   @e2e widgets::callback-registers-late-within-the-polling-window
 *   @e2e widgets::callback-never-registers-full-api-fallback
 *   @e2e widgets::vertical-mode-list-rendering
 *   @e2e widgets::horizontal-mode-card-rendering
 *   @e2e widgets::default-item-limit-is-7
 *   @e2e widgets::empty-list-state
 *
 * @spec openspec/specs/widgets/spec.md
 */

import type { Page, Request } from '@playwright/test'
import type { Placement } from './support/widgetPlacements.ts'

import { expect, test } from '@playwright/test'
import { MORE_MENU } from './support/dashboardSwitcher.ts'
import {
	adminApi,
	gridItem,
	isWidgetCatalogue,
	ocsWidgetItems,
	openWorkspace,
	Seeds,
} from './support/widgetPlacements.ts'

const RUN = Date.now()
const ITEMS_PATH = '/apps/dashboard/api/v2/widget-items'
const LP_ITEMS_PATH = '/apps/launchpad/api/widgets/items'
const CELL = '.cn-nc-widget-widget'

let seeds: Seeds

/**
 * Seed a dashboard with one nc-widget placement proxying `widgetId`.
 *
 * @param tag distinguishes the test.
 * @param content the placement content.
 * @param size grid width and height.
 * @return the placement.
 */
async function ncWidget(
	tag: string,
	content: Record<string, unknown>,
	size = { gridWidth: 4, gridHeight: 6 },
): Promise<Placement> {
	const dash = await seeds.dashboard(`E2E nc-widget ${tag} ${RUN}`)
	return seeds.widget(dash, {
		widgetId: 'nc-widget',
		gridX: 0,
		gridY: 0,
		...size,
		content,
	})
}

/**
 * Whether a request asks either items endpoint for `widgetId`.
 *
 * @param req the request.
 * @param widgetId the Nextcloud widget id.
 * @return true for an items request about that widget.
 */
function isItemsRequestFor(req: Request, widgetId: string): boolean {
	const url = new URL(req.url())
	const isItems =
		url.pathname.endsWith(ITEMS_PATH) || url.pathname.endsWith(LP_ITEMS_PATH)
	return isItems && decodeURIComponent(url.search).includes(widgetId)
}

/**
 * Answer Nextcloud's widget-items endpoint for `widgetId` with `items`, and
 * count the calls.
 *
 * @param page the page.
 * @param widgetId the Nextcloud widget id.
 * @param items the items.
 * @return the live list of answered requests.
 */
async function serveItems(
	page: Page,
	widgetId: string,
	items: Array<Record<string, unknown>>,
): Promise<Request[]> {
	const seen: Request[] = []
	await page.route(
		(url) => url.pathname.endsWith(ITEMS_PATH),
		async (route) => {
			if (!isItemsRequestFor(route.request(), widgetId)) {
				return route.continue()
			}
			seen.push(route.request())
			return route.fulfill({
				status: 200,
				contentType: 'application/json',
				body: ocsWidgetItems(widgetId, items),
			})
		},
	)
	return seen
}

/**
 * An init script that registers a native render callback for `widgetId` as
 * soon as the LaunchPad widget bridge has installed `OCA.Dashboard.register`,
 * i.e. before any placement can mount. The callback records the class of the
 * element it was handed and paints a marker into it.
 *
 * @param page the page.
 * @param widgetId the Nextcloud widget id.
 * @return nothing.
 */
async function registerNativeEarly(page: Page, widgetId: string): Promise<void> {
	await page.addInitScript((id) => {
		const w = window as unknown as Record<string, any>
		w.__e2eNativeMounts = []
		const register = () => {
			const dash = w.OCA && w.OCA.Dashboard
			if (!dash || typeof dash.register !== 'function' || !dash.callbacks) {
				return false
			}
			dash.register(id, (el: HTMLElement) => {
				w.__e2eNativeMounts.push(el.className)
				el.innerHTML =
					'<p data-testid="e2e-native-body">painted by the native callback</p>'
			})
			return true
		}
		if (!register()) {
			const timer = setInterval(() => {
				if (register()) {
					clearInterval(timer)
				}
			}, 0)
		}
	}, widgetId)
}

/**
 * Four items, the first with a title far too long for its row.
 *
 * @return the items.
 */
function fourItems(): Array<Record<string, unknown>> {
	return [1, 2, 3, 4].map((n) => ({
		title:
			n === 1
				? `E2E item ${n} ${'with a very long title that cannot fit '.repeat(6)}`
				: `E2E item ${n}`,
		subtitle: `Subtitle ${n}`,
		link: `/index.php/apps/files/?e2e=${n}`,
		iconUrl: '/core/img/places/files.svg',
		sinceId: String(n),
	}))
}

test.beforeAll(async () => {
	seeds = new Seeds(await adminApi())
})

test.afterAll(async () => {
	await seeds?.cleanup()
})

test.describe('nc-widget placement type (REQ-WDG-018..021)', () => {
	test.describe.configure({ timeout: 120_000 })

	// @e2e widgets::form-picker-lists-discovered-widgets
	//
	// The scenario calls the picker a `<select>`; the product's sub-form
	// (nextcloud-vue CnNcDashboardWidgetForm) renders the discovered widgets as
	// a radiogroup of cards (CnNcWidgetGridPicker). The test asserts what the
	// scenario requires of it: both discovered widgets offered, none chosen
	// means Add stays disabled, choosing one enables it.
	test('the nc-widget sub-form lists the discovered widgets and needs one before Add', async ({
		page,
	}) => {
		const dash = await seeds.dashboard(`E2E nc-widget form ${RUN}`)
		expect(dash.id).toBeGreaterThan(0)
		await page.route(
			(url) => isWidgetCatalogue(url),
			(route) =>
				route.fulfill({
					status: 200,
					contentType: 'application/json',
					body: JSON.stringify([
						{
							id: 'weather_status',
							title: 'Weather',
							order: 1,
							iconClass: '',
							iconUrl: '',
							widgetUrl: null,
							itemIconsRound: false,
							itemApiVersions: [],
							reloadInterval: 0,
							buttons: [],
						},
						{
							id: 'recommendations',
							title: 'Recommended',
							order: 2,
							iconClass: '',
							iconUrl: '',
							widgetUrl: null,
							itemIconsRound: false,
							itemApiVersions: [1, 2],
							reloadInterval: 0,
							buttons: [],
						},
					]),
				}),
		)
		await openWorkspace(page)
		await page
			.locator(`${MORE_MENU} [data-testid="cn-grouped-menu-trigger"]`)
			.click()
		await page
			.locator('[data-testid="cn-grouped-menu-item-add-widget"]')
			.click()
		await page.locator('[data-testid="widget-picker-custom-widget"]').click()

		const dialog = page.getByRole('dialog', { name: 'Add Widget' })
		await expect(dialog).toBeVisible({ timeout: 10_000 })
		await dialog
			.locator('[data-testid="widget-type-select"]')
			.selectOption('nc-widget')

		const picker = dialog.getByRole('radiogroup', { name: 'Pick a widget' })
		await expect(picker.getByRole('radio')).toHaveCount(2)
		await expect(picker.getByRole('radio', { name: 'Weather' })).toBeVisible()
		await expect(
			picker.getByRole('radio', { name: 'Recommended' }),
		).toBeVisible()

		const add = dialog.locator('[data-testid="add-widget-save"]')
		await expect(add).toBeDisabled()
		await picker.getByRole('radio', { name: 'Weather' }).click()
		await expect(picker.getByRole('radio', { name: 'Weather' })).toHaveAttribute(
			'aria-checked',
			'true',
		)
		await expect(add).toBeEnabled()
	})

	// @e2e widgets::native-callback-already-registered-at-mount
	//
	// The scenario names `widgetBridge.mountWidget`; the nc-widget renderer
	// calls the callback the bridge registered on `OCA.Dashboard` directly, with
	// its own container. What is observable is the same: the registered
	// callback paints into the cell, and no items request is made.
	test('a callback registered before mount renders natively and fetches no items', async ({
		page,
	}) => {
		const widgetId = `e2e-native-${RUN}`
		await registerNativeEarly(page, widgetId)
		const itemRequests: Request[] = []
		page.on('request', (req) => {
			if (isItemsRequestFor(req, widgetId)) {
				itemRequests.push(req)
			}
		})
		const placement = await ncWidget('native', {
			widgetId,
			displayMode: 'vertical',
		})
		await openWorkspace(page, placement.id)

		const cell = gridItem(page, placement.id).locator(CELL)
		await expect(
			cell.locator(
				'.cn-nc-widget-widget__native [data-testid="e2e-native-body"]',
			),
		).toBeVisible({ timeout: 10_000 })
		await expect(cell.locator('.cn-nc-widget-widget__body')).toHaveCount(0)
		const mounts = await page.evaluate(
			() => (window as unknown as Record<string, string[]>).__e2eNativeMounts,
		)
		expect(mounts).toEqual(['cn-nc-widget-widget__native'])

		await page.waitForTimeout(1_500)
		expect(itemRequests.map((r) => r.url())).toEqual([])
	})

	// @e2e widgets::callback-registers-late-within-the-polling-window
	//
	// FIXME — suspected product bug. The renderer (nextcloud-vue
	// src/components/CnNcWidgetWidget/CnNcWidgetWidget.vue, `tryMount()` and
	// `loadApiItems()`) resolves the native callback once at mount and once
	// more only when the API list comes back EMPTY; there is no 200 ms /
	// 15-retry poll. A bundle that registers one second after mount is never
	// picked up while the API list has items. (The legacy stock-widget path,
	// src/components/WidgetRenderer.vue `tryMountLegacyWidget`, does retry,
	// but it is not the nc-widget renderer.)
	test.fixme('a callback that registers within the polling window takes over from the API list', async ({
		page,
	}) => {
		const widgetId = `e2e-late-${RUN}`
		const served = await serveItems(page, widgetId, fourItems())
		const placement = await ncWidget('late', {
			widgetId,
			displayMode: 'vertical',
		})
		await openWorkspace(page, placement.id)

		const cell = gridItem(page, placement.id).locator(CELL)
		await expect
			.poll(() => served.length, { timeout: 10_000 })
			.toBeGreaterThan(0)
		await page.waitForTimeout(1_000)
		await page.evaluate((id) => {
			const w = window as unknown as Record<string, any>
			w.OCA.Dashboard.register(id, (el: HTMLElement) => {
				el.innerHTML = '<p data-testid="e2e-native-body">painted late</p>'
			})
		}, widgetId)

		await expect(
			cell.locator(
				'.cn-nc-widget-widget__native [data-testid="e2e-native-body"]',
			),
		).toBeVisible({ timeout: 2_000 })
		await expect(cell.locator('.cn-nc-widget-widget__body')).toHaveCount(0)
		await expect(cell.locator('.cn-nc-widget-widget__item')).toHaveCount(0)
	})

	// @e2e widgets::callback-never-registers-full-api-fallback
	test('without a callback the API list is the final state and the callback checks stop', async ({
		page,
	}) => {
		const widgetId = `e2e-never-${RUN}`
		const served = await serveItems(page, widgetId, fourItems().slice(1, 3))
		const placement = await ncWidget('never', {
			widgetId,
			displayMode: 'vertical',
		})
		await openWorkspace(page, placement.id)
		const cell = gridItem(page, placement.id).locator(CELL)
		await expect(cell.locator('.cn-nc-widget-widget__item')).toHaveCount(2, {
			timeout: 10_000,
		})
		expect(served.length).toBe(1)

		// Past the ~3 s polling window, count every read of the registry the
		// renderer resolves callbacks from (`window.OCA.Dashboard` callbacks,
		// widgets and getWidget).
		await page.waitForTimeout(3_500)
		await page.evaluate(() => {
			const w = window as unknown as Record<string, any>
			w.__e2eCallbackChecks = 0
			const real = w.OCA.Dashboard
			w.OCA.Dashboard = new Proxy(real, {
				get(target, prop, receiver) {
					if (
						prop === 'callbacks'
						|| prop === 'widgets'
						|| prop === 'getWidget'
					) {
						w.__e2eCallbackChecks++
					}
					return Reflect.get(target, prop, receiver)
				},
			})
		})
		await page.waitForTimeout(2_000)
		const checks = await page.evaluate(
			() => (window as unknown as Record<string, number>).__e2eCallbackChecks,
		)
		expect(checks).toBe(0)

		await expect(cell.locator('.cn-nc-widget-widget__body')).toBeVisible()
		await expect(cell.locator('.cn-nc-widget-widget__item')).toHaveCount(2)
		await expect(cell.locator('.cn-nc-widget-widget__native')).toBeHidden()
		expect(served.length).toBe(1)
	})

	// @e2e widgets::vertical-mode-list-rendering
	test('vertical mode renders a flex-column list with a 32px icon left of the text', async ({
		page,
	}) => {
		const widgetId = `e2e-vertical-${RUN}`
		await serveItems(page, widgetId, fourItems())
		const placement = await ncWidget('vertical', {
			widgetId,
			displayMode: 'vertical',
		})
		await openWorkspace(page, placement.id)

		const cell = gridItem(page, placement.id).locator(CELL)
		const body = cell.locator('.cn-nc-widget-widget__body')
		await expect(body.locator('a.cn-nc-widget-widget__item')).toHaveCount(4, {
			timeout: 10_000,
		})
		await expect(body).toHaveCSS('display', 'flex')
		await expect(body).toHaveCSS('flex-direction', 'column')

		const rows = body.locator('a.cn-nc-widget-widget__item')
		for (let i = 0; i < 4; i++) {
			const row = rows.nth(i)
			await expect(row).toHaveCSS('flex-direction', 'row')
			const icon = row.locator('.cn-nc-widget-widget__item-icon')
			await expect(icon).toHaveCSS('width', '32px')
			await expect(icon).toHaveCSS('height', '32px')
			const iconBox = (await icon.boundingBox())!
			const textBox = (await row
				.locator('.cn-nc-widget-widget__item-text')
				.boundingBox())!
			expect(iconBox.x + iconBox.width).toBeLessThanOrEqual(textBox.x)
			await expect(
				row.locator('.cn-nc-widget-widget__item-title'),
			).toContainText(`E2E item ${i + 1}`)
			await expect(
				row.locator('.cn-nc-widget-widget__item-subtitle'),
			).toHaveText(`Subtitle ${i + 1}`)
		}

		const longTitle = rows.first().locator('.cn-nc-widget-widget__item-title')
		await expect(longTitle).toHaveCSS('text-overflow', 'ellipsis')
		await expect(longTitle).toHaveCSS('white-space', 'nowrap')
		const overflow = await longTitle.evaluate(
			(el) => el.scrollWidth > el.clientWidth,
		)
		expect(overflow, 'the long title must actually be cut off').toBe(true)
	})

	// @e2e widgets::horizontal-mode-card-rendering
	//
	// FIXME — suspected product bug. nextcloud-vue CnNcWidgetWidget.vue sizes
	// the item icon at 32px in BOTH modes (`.cn-nc-widget-widget__item-icon-wrap`
	// and `__item-icon`: width/height 32px); there is no 44px horizontal-mode
	// icon. The flex-row wrap, ~120px cards and centred text are there.
	test.fixme('horizontal mode renders wrapping ~120px cards with a 44px icon on top', async ({
		page,
	}) => {
		const widgetId = `e2e-horizontal-${RUN}`
		await serveItems(page, widgetId, fourItems())
		const placement = await ncWidget(
			'horizontal',
			{ widgetId, displayMode: 'horizontal' },
			{ gridWidth: 12, gridHeight: 6 },
		)
		await openWorkspace(page, placement.id)

		const cell = gridItem(page, placement.id).locator(CELL)
		const body = cell.locator('.cn-nc-widget-widget__body')
		const cards = body.locator('a.cn-nc-widget-widget__item')
		await expect(cards).toHaveCount(4, { timeout: 10_000 })
		await expect(body).toHaveCSS('display', 'flex')
		await expect(body).toHaveCSS('flex-direction', 'row')
		await expect(body).toHaveCSS('flex-wrap', 'wrap')

		for (let i = 0; i < 4; i++) {
			const card = cards.nth(i)
			const box = (await card.boundingBox())!
			expect(Math.abs(box.width - 120)).toBeLessThanOrEqual(4)
			const icon = card.locator('.cn-nc-widget-widget__item-icon')
			await expect(icon).toHaveCSS('width', '44px')
			await expect(icon).toHaveCSS('height', '44px')
			const iconBox = (await icon.boundingBox())!
			const textBox = (await card
				.locator('.cn-nc-widget-widget__item-text')
				.boundingBox())!
			expect(iconBox.y + iconBox.height).toBeLessThanOrEqual(textBox.y)
			await expect(card).toHaveCSS('text-align', 'center')
		}
	})

	// @e2e widgets::default-item-limit-is-7
	test('the API fallback asks for seven items', async ({ page }) => {
		const widgetId = `e2e-limit-${RUN}`
		const served = await serveItems(page, widgetId, fourItems())
		const placement = await ncWidget('limit', {
			widgetId,
			displayMode: 'vertical',
		})
		await openWorkspace(page, placement.id)
		await expect(
			gridItem(page, placement.id).locator('.cn-nc-widget-widget__item'),
		).toHaveCount(4, { timeout: 10_000 })

		expect(served.length).toBe(1)
		const url = new URL(served[0].url())
		expect(url.searchParams.get('limit')).toBe('7')
	})

	// @e2e widgets::empty-list-state
	test('an empty item list shows "No items available", centred, and no item links', async ({
		page,
	}) => {
		const widgetId = `e2e-empty-${RUN}`
		const served = await serveItems(page, widgetId, [])
		const placement = await ncWidget('empty', {
			widgetId,
			displayMode: 'vertical',
		})
		await openWorkspace(page, placement.id)

		const cell = gridItem(page, placement.id).locator(CELL)
		const state = cell.locator('.cn-nc-widget-widget__state')
		await expect(state).toHaveText('No items available', { timeout: 10_000 })
		await expect(state).toHaveCSS('display', 'flex')
		await expect(state).toHaveCSS('justify-content', 'center')
		await expect(state).toHaveCSS('align-items', 'center')
		await expect(cell.locator('a')).toHaveCount(0)
		expect(served.length).toBe(1)
	})
})
