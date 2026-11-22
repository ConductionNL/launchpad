/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * What a placement looks like on the workspace grid: its chrome, its title,
 * its style, whether it renders at all, and the save the grid sends after a
 * rearrangement (openspec/specs/widgets/spec.md, REQ-WDG-004, -006, -008,
 * -009), written for the e2e-coverage-programme.
 *
 * The admin owns a fresh active dashboard (support/dashboardFixture.ts) and
 * the browser runs as the admin (the suite's stored session). Placements are
 * seeded and changed over the API, because the scenarios start from "a
 * placement with ..." or from an API write; what they claim is what renders,
 * and that is read from the DOM: `[data-testid="widget-placement-<id>"]`
 * (WidgetWrapper.vue) and `[data-placement-id]` (Views.vue).
 *
 * Gate-19 @e2e traceability:
 *   @e2e widgets::update-custom-title
 *   @e2e widgets::toggle-title-visibility
 *   @e2e widgets::visible-widget-always-renders
 *   @e2e widgets::hidden-widget-never-renders
 *   @e2e widgets::conditional-widget-evaluated-at-render-time
 *   @e2e widgets::visibility-toggle-via-api
 *   @e2e widgets::batch-update-via-dashboard-update-endpoint
 *   @e2e widgets::widgetwrapper-renders-header-and-chrome
 *   @e2e widgets::widgetwrapper-applies-style-configuration
 *   @e2e widgets::widgetwrapper-handles-missing-widget
 *   @e2e widgets::tile-placement-bypasses-widgetwrapper
 *
 * @spec openspec/specs/widgets/spec.md
 */

import type { APIRequestContext, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'
import type { Placement } from './support/widgets.ts'

import { expect, test } from '@playwright/test'
import {
	clearSeededWidgets,
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'
import { enterEditMode, MORE_MENU } from './support/dashboardSwitcher.ts'
import { adminContext, API } from './support/principals.ts'
import {
	placementOf,
	placementsOf,
	readAllowUserDashboards,
	restoreAllowUserDashboards,
	seedPlacement,
	seedTile,
} from './support/widgets.ts'

const STAMP = `E2E WidgetRender ${Date.now()}`

interface CatalogWidget {
	id: string
	title: string
	iconUrl: string | null
	buttons: Array<{ text: string; link: string }>
}

let admin: APIRequestContext
let dashboard: SeededDashboard | null = null
let allowBefore: boolean | null = null
let catalog: CatalogWidget[] = []

/** The stock widget the rendering tests place: one with an icon, buttons if any has them. */
function stockWidget(): CatalogWidget {
	const withIcon = catalog.filter((w) => w.iconUrl)
	const chosen = withIcon.find((w) => w.buttons.length > 0) ?? withIcon[0]
	expect(chosen, 'no Nextcloud widget on this server has an icon').toBeTruthy()
	return chosen!
}

/**
 * The rendered placement wrapper.
 *
 * @param page the page.
 * @param id the placement id.
 * @return its locator.
 */
function rendered(page: Page, id: number) {
	return page.locator(`[data-testid="widget-placement-${id}"]`)
}

/**
 * Open LaunchPad on the seeded dashboard and wait for its grid.
 *
 * @param page the page.
 * @return nothing.
 */
async function openDashboard(page: Page): Promise<void> {
	await page.goto('/index.php/apps/launchpad/')
	await expect(page.locator(MORE_MENU)).toBeVisible({ timeout: 30_000 })
	await expect(page.locator('.launchpad-grid-item').first()).toBeVisible({
		timeout: 20_000,
	})
}

test.describe.configure({ mode: 'serial' })

test.beforeAll(async () => {
	admin = await adminContext()
	allowBefore = await readAllowUserDashboards(admin)
	const list = await admin.get(`${API}/widgets`)
	expect(list.status(), await list.text()).toBe(200)
	catalog = (await list.json()) as CatalogWidget[]
})

test.afterAll(async () => {
	await restoreAllowUserDashboards(admin, allowBefore)
	await admin.dispose()
})

test.beforeEach(async () => {
	// A fresh dashboard per test: what one test hides or moves cannot leak
	// into the next one's grid.
	dashboard = await seedActiveDashboard(
		admin,
		`${STAMP} ${test.info().title.slice(0, 40)}`,
	)
	// A new dashboard comes with the default bundle; each test places its own.
	await clearSeededWidgets(admin, dashboard.id)
})

test.afterEach(async () => {
	await removeSeededDashboard(admin, dashboard)
	dashboard = null
})

test.describe('REQ-WDG-004 title updates render', () => {
	// @e2e widgets::update-custom-title
	test('PUT customTitle stores it and the widget shows it instead of the default title', async ({
		page,
	}) => {
		const widget = stockWidget()
		const placement = await seedPlacement(admin, dashboard!.id, {
			widgetId: widget.id,
			gridX: 0,
			gridY: 0,
			gridWidth: 4,
			gridHeight: 4,
		})
		expect(placement.customTitle).toBeNull()

		const res = await admin.put(`${API}/widgets/${placement.id}`, {
			data: { customTitle: 'Weather Today' },
		})
		expect(res.status(), await res.text()).toBe(200)
		expect(
			(await placementOf(admin, dashboard!.id, placement.id))?.customTitle,
		).toBe('Weather Today')

		await openDashboard(page)
		const title = rendered(page, placement.id)
			.locator('.cn-widget-wrapper__title')
			.first()
		await expect(title).toHaveText('Weather Today')
		await expect(title).not.toHaveText(widget.title)
	})

	// @e2e widgets::toggle-title-visibility
	test('PUT showTitle 0 stores it and the widget renders without a title bar', async ({
		page,
	}) => {
		const widget = stockWidget()
		const placement = await seedPlacement(admin, dashboard!.id, {
			widgetId: widget.id,
			gridX: 0,
			gridY: 0,
			gridWidth: 4,
			gridHeight: 4,
		})
		expect(placement.showTitle).toBe(1)

		await openDashboard(page)
		await expect(
			rendered(page, placement.id)
				.locator('.cn-widget-wrapper__title')
				.first(),
		).toHaveText(widget.title)

		const res = await admin.put(`${API}/widgets/${placement.id}`, {
			data: { showTitle: 0 },
		})
		expect(res.status(), await res.text()).toBe(200)
		expect(
			(await placementOf(admin, dashboard!.id, placement.id))?.showTitle,
		).toBe(0)

		await openDashboard(page)
		await expect(rendered(page, placement.id)).toBeVisible()
		await expect(
			rendered(page, placement.id).locator('.cn-widget-wrapper__title'),
		).toHaveCount(0)
		await expect(
			rendered(page, placement.id).getByText(widget.title, { exact: true }),
		).toHaveCount(0)
	})
})

test.describe('REQ-WDG-006 placement visibility', () => {
	// @e2e widgets::visible-widget-always-renders
	test('a visible placement without rules is displayed, in view and in edit mode', async ({
		page,
	}) => {
		const widget = stockWidget()
		const placement = await seedPlacement(admin, dashboard!.id, {
			widgetId: widget.id,
			gridX: 0,
			gridY: 0,
			gridWidth: 4,
			gridHeight: 4,
		})
		expect(placement.isVisible).toBe(1)
		const rules = await admin.get(`${API}/widgets/${placement.id}/rules`)
		expect((await rules.json()).rules).toEqual([])

		await openDashboard(page)
		await expect(rendered(page, placement.id)).toBeVisible()
		await enterEditMode(page)
		await expect(rendered(page, placement.id)).toBeVisible()
		await page.reload()
		await expect(page.locator(MORE_MENU)).toBeVisible({ timeout: 30_000 })
		await expect(rendered(page, placement.id)).toBeVisible({ timeout: 20_000 })
	})

	// @e2e widgets::hidden-widget-never-renders
	test('a placement with isVisible 0 is not displayed and leaves no placeholder', async ({
		page,
	}) => {
		// Views.vue shownPlacements leaves out a placement with isVisible 0
		// in view and edit mode, as the public share grid does.
		const widget = stockWidget()
		const shown = await seedPlacement(admin, dashboard!.id, {
			widgetId: widget.id,
			gridX: 0,
			gridY: 0,
			gridWidth: 4,
			gridHeight: 4,
		})
		const hidden = await seedPlacement(admin, dashboard!.id, {
			widgetId: widget.id,
			gridX: 4,
			gridY: 0,
			gridWidth: 4,
			gridHeight: 4,
		})
		const res = await admin.put(`${API}/widgets/${hidden.id}`, {
			data: { isVisible: 0 },
		})
		expect(res.status(), await res.text()).toBe(200)

		await openDashboard(page)
		await expect(rendered(page, shown.id)).toBeVisible()
		await expect(rendered(page, hidden.id)).toHaveCount(0)
		// No placeholder cell either.
		await expect(page.locator(`[data-placement-id="${hidden.id}"]`)).toHaveCount(
			0,
		)
		await expect(page.locator('.launchpad-grid-item')).toHaveCount(1)
	})

	// @e2e widgets::conditional-widget-evaluated-at-render-time
	test.fixme('a visible placement whose rules do not match is not displayed; one whose rules match is', async ({
		page,
	}) => {
		// SUSPECTED DEFECT: no read path evaluates the rules.
		// ConditionalService::checkRulesForPlacement
		// (lib/Service/ConditionalService.php:93) is called only by
		// RuleApiController::getRules (lib/Controller/RuleApiController.php:79),
		// never by the dashboard read the grid renders from.
		const widget = stockWidget()
		const matching = await seedPlacement(admin, dashboard!.id, {
			widgetId: widget.id,
			gridX: 0,
			gridY: 0,
			gridWidth: 4,
			gridHeight: 4,
		})
		const failing = await seedPlacement(admin, dashboard!.id, {
			widgetId: widget.id,
			gridX: 4,
			gridY: 0,
			gridWidth: 4,
			gridHeight: 4,
		})
		const lastYear = `${new Date().getUTCFullYear() - 1}-01-01`
		const show = await admin.post(`${API}/widgets/${matching.id}/rules`, {
			data: {
				ruleType: 'date',
				ruleConfig: { startDate: lastYear },
				isInclude: true,
			},
		})
		expect(show.status(), await show.text()).toBeLessThan(300)
		const hide = await admin.post(`${API}/widgets/${failing.id}/rules`, {
			data: {
				ruleType: 'group',
				ruleConfig: { groups: [`${STAMP} nobody`] },
				isInclude: true,
			},
		})
		expect(hide.status(), await hide.text()).toBeLessThan(300)

		// The server's own evaluation, for the record.
		expect(
			(await (await admin.get(`${API}/widgets/${matching.id}/rules`)).json())
				.isVisible,
		).toBe(true)
		expect(
			(await (await admin.get(`${API}/widgets/${failing.id}/rules`)).json())
				.isVisible,
		).toBe(false)

		await openDashboard(page)
		await expect(rendered(page, matching.id)).toBeVisible()
		await expect(rendered(page, failing.id)).toHaveCount(0)
	})

	// @e2e widgets::visibility-toggle-via-api
	test('PUT isVisible 0 stores it and the next render hides the widget even with matching rules', async ({
		page,
	}) => {
		const widget = stockWidget()
		const placement = await seedPlacement(admin, dashboard!.id, {
			widgetId: widget.id,
			gridX: 0,
			gridY: 0,
			gridWidth: 4,
			gridHeight: 4,
		})
		const lastYear = `${new Date().getUTCFullYear() - 1}-01-01`
		const rule = await admin.post(`${API}/widgets/${placement.id}/rules`, {
			data: {
				ruleType: 'date',
				ruleConfig: { startDate: lastYear },
				isInclude: true,
			},
		})
		expect(rule.status(), await rule.text()).toBeLessThan(300)

		await openDashboard(page)
		await expect(rendered(page, placement.id)).toBeVisible()

		const res = await admin.put(`${API}/widgets/${placement.id}`, {
			data: { isVisible: 0 },
		})
		expect(res.status(), await res.text()).toBe(200)
		expect(
			(await placementOf(admin, dashboard!.id, placement.id))?.isVisible,
		).toBe(0)

		await page.reload()
		await expect(page.locator(MORE_MENU)).toBeVisible({ timeout: 30_000 })
		await expect(rendered(page, placement.id)).toHaveCount(0)
	})
})

test.describe('REQ-WDG-008 the grid saves a rearrangement in one batch', () => {
	// @e2e widgets::batch-update-via-dashboard-update-endpoint
	test("moving a widget sends PUT /api/dashboard/{id} with every placement's id and rectangle", async ({
		page,
	}) => {
		const widget = stockWidget()
		const first = await seedPlacement(admin, dashboard!.id, {
			widgetId: widget.id,
			gridX: 0,
			gridY: 0,
			gridWidth: 4,
			gridHeight: 4,
		})
		const second = await seedPlacement(admin, dashboard!.id, {
			widgetId: widget.id,
			gridX: 4,
			gridY: 0,
			gridWidth: 4,
			gridHeight: 4,
		})

		await openDashboard(page)
		await enterEditMode(page)

		// The keyboard path through the same `updatePlacements` store action
		// the grid's drag (`@layoutChange`) calls (src/views/Views.vue:272,
		// :2185); a drag cannot be made reliably from a headless browser.
		const item = page.locator(
			`.grid-stack-item:has([data-placement-id="${first.id}"])`,
		)
		await expect(item).toBeVisible()
		await item.focus()
		await page.keyboard.press('Enter')
		await expect(
			page.locator('[data-testid="widget-context-menu"]'),
		).toBeVisible({ timeout: 5_000 })
		await page.locator('[data-testid="ctx-move"]').click()
		const panel = page.locator('[data-test="widget-move-panel"]')
		await expect(panel).toBeVisible({ timeout: 5_000 })

		const save = page.waitForRequest(
			(req) =>
				req.method() === 'PUT'
				&& new URL(req.url()).pathname.endsWith(
					`/apps/launchpad/api/dashboard/${dashboard!.id}`,
				),
		)
		await page.keyboard.press('ArrowDown')
		await page.keyboard.press('ArrowDown')
		await page.keyboard.press('Enter')
		const body = (await save).postDataJSON() as {
			placements: Array<Record<string, number>>
		}

		expect(Array.isArray(body.placements)).toBe(true)
		expect(body.placements.map((p) => p.id).sort()).toEqual(
			[first.id, second.id].sort(),
		)
		for (const entry of body.placements) {
			expect(Object.keys(entry).sort()).toEqual([
				'gridHeight',
				'gridWidth',
				'gridX',
				'gridY',
				'id',
			])
		}
		const moved = body.placements.find((p) => p.id === first.id)!
		expect(moved.gridY).toBeGreaterThan(0)

		await expect
			.poll(
				async () =>
					(await placementOf(admin, dashboard!.id, first.id))?.gridY,
			)
			.toBe(moved.gridY)
	})
})

test.describe('REQ-WDG-009 rendering architecture', () => {
	// @e2e widgets::widgetwrapper-renders-header-and-chrome
	test('header with icon and title, an edit control only in edit mode, the content and the widget buttons', async ({
		page,
	}) => {
		const widget = stockWidget()
		const placement = await seedPlacement(admin, dashboard!.id, {
			widgetId: widget.id,
			gridX: 0,
			gridY: 0,
			gridWidth: 6,
			gridHeight: 6,
		})
		expect(placement.showTitle).toBe(1)

		await openDashboard(page)
		const card = rendered(page, placement.id)
		const header = card.locator('.cn-widget-wrapper__header').first()
		await expect(header).toBeVisible()
		await expect(header.locator('img.cn-widget-wrapper__icon')).toHaveAttribute(
			'src',
			widget.iconUrl!,
		)
		await expect(header.locator('.cn-widget-wrapper__title')).toHaveText(
			widget.title,
		)
		await expect(
			card.locator('.cn-widget-wrapper__content').first(),
		).toBeVisible()
		// No edit control in view mode.
		await expect(card.locator('[data-testid="cn-widget-edit-cog"]')).toHaveCount(
			0,
		)

		const footer = card.locator('.cn-widget-wrapper__footer').first()
		if (widget.buttons.length > 0) {
			for (const button of widget.buttons) {
				await expect(footer).toContainText(button.text)
			}
		} else {
			await expect(footer).toHaveCount(0)
		}

		await enterEditMode(page)
		await expect(
			card.locator('[data-testid="cn-widget-edit-cog"]'),
		).toBeVisible()
	})

	// @e2e widgets::widgetwrapper-applies-style-configuration
	test('styleConfig becomes inline CSS on the card, headerStyle on its header', async ({
		page,
	}) => {
		const widget = stockWidget()
		const placement = await seedPlacement(admin, dashboard!.id, {
			widgetId: widget.id,
			gridX: 0,
			gridY: 0,
			gridWidth: 4,
			gridHeight: 4,
		})
		const res = await admin.put(`${API}/widgets/${placement.id}`, {
			data: {
				styleConfig: {
					backgroundColor: '#f0f0f0',
					borderStyle: 'solid',
					borderWidth: 1,
					borderColor: '#ccc',
					borderRadius: 12,
					headerStyle: {
						backgroundColor: '#123456',
						textColor: '#fedcba',
					},
				},
			},
		})
		expect(res.status(), await res.text()).toBe(200)

		await openDashboard(page)
		const wrapper = rendered(page, placement.id)
			.locator('.cn-widget-wrapper')
			.first()
		const inline = await wrapper.evaluate((el) => {
			const s = (el as HTMLElement).style
			return {
				bg: s.backgroundColor,
				border: s.border,
				radius: s.borderRadius,
			}
		})
		expect(inline).toEqual({
			bg: 'rgb(240, 240, 240)',
			border: '1px solid rgb(204, 204, 204)',
			radius: '12px',
		})

		const header = rendered(page, placement.id)
			.locator('.cn-widget-wrapper__header')
			.first()
		const headerInline = await header.evaluate((el) => {
			const s = (el as HTMLElement).style
			return { bg: s.backgroundColor, color: s.color }
		})
		expect(headerInline).toEqual({
			bg: 'rgb(18, 52, 86)',
			color: 'rgb(254, 220, 186)',
		})
	})

	// @e2e widgets::widgetwrapper-handles-missing-widget
	test('a placement of an uninstalled widget renders with the "Widget" title and an empty state', async ({
		page,
	}) => {
		const placement = await seedPlacement(admin, dashboard!.id, {
			widgetId: 'uninstalled_widget',
			gridX: 0,
			gridY: 0,
			gridWidth: 4,
			gridHeight: 4,
		})
		expect(catalog.map((w) => w.id)).not.toContain('uninstalled_widget')

		const errors: string[] = []
		page.on('pageerror', (error) => errors.push(error.message))
		await openDashboard(page)
		const card = rendered(page, placement.id)
		await expect(card).toBeVisible()
		await expect(card.locator('.cn-widget-wrapper__title').first()).toHaveText(
			'Widget',
		)
		await expect(
			card.locator('.cn-widget-wrapper__content').first(),
		).toContainText('Widget not available')
		expect(errors).toEqual([])
	})

	// @e2e widgets::tile-placement-bypasses-widgetwrapper
	test('a custom tile renders as TileWidget without the wrapper; a tile- widgetId gets a frameless wrapper', async ({
		page,
	}) => {
		const tile = await seedTile(admin, dashboard!.id, {
			title: `${STAMP} tile`,
			linkType: 'url',
			linkValue: 'https://example.org/',
			gridX: 0,
			gridY: 0,
		})
		expect(tile.tileType).toBe('custom')
		// A tile-type widgetId that is NOT a custom tile goes through the
		// wrapper, which must paint it frameless.
		const tileId = await seedPlacement(admin, dashboard!.id, {
			widgetId: `tile-e2e${Date.now()}`,
			gridX: 4,
			gridY: 0,
			gridWidth: 2,
			gridHeight: 2,
		})
		expect(
			(await placementsOf(admin, dashboard!.id)).find(
				(p: Placement) => p.id === tileId.id,
			)?.tileType ?? null,
		).toBeNull()

		await openDashboard(page)
		const cell = page.locator(`[data-placement-id="${tile.id}"]`)
		await expect(cell.locator('.tile-widget')).toBeVisible()
		await expect(cell).toContainText(`${STAMP} tile`)
		await expect(cell.locator('.launchpad-widget')).toHaveCount(0)
		await expect(cell.locator('.cn-widget-wrapper')).toHaveCount(0)

		const framed = rendered(page, tileId.id)
			.locator('.cn-widget-wrapper')
			.first()
		await expect(framed).toHaveClass(/cn-widget-wrapper--borderless/)
		await expect(framed).toHaveClass(/cn-widget-wrapper--flush/)
		const look = await framed.evaluate((el) => {
			const content = el.querySelector(
				'.cn-widget-wrapper__content',
			) as HTMLElement | null
			return {
				background: getComputedStyle(el).backgroundColor,
				padding: content ? getComputedStyle(content).padding : null,
			}
		})
		expect(look.background).toBe('rgba(0, 0, 0, 0)')
		expect(look.padding).toBe('0px')
	})
})
