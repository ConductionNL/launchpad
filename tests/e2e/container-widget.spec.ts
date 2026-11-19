/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The container widget: a sub-grid of child placements in one outer cell.
 *
 * Written for e2e-coverage-programme task 4.23. The spec used to sit behind
 * one blanket ("inner GridStack instances cannot be reliably driven
 * headlessly"). That holds for nothing here: the picker and the form are the
 * Add Widget modal, the inner grid is a real GridStack instance the page
 * exposes on its element, children render into the DOM like any widget, and
 * the depth limit is an HTTP contract.
 *
 * Containers with children are seeded over the API, because no control in
 * the product adds a child to a container (see the exclusion on
 * add-a-child-widget-inside-a-container in the spec).
 *
 * The sorting scenarios of this capability live in
 * container-tile-sorting.spec.ts.
 *
 * @e2e openspec/specs/container-widget/spec.md
 */

import type { APIRequestContext, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, request, test } from '@playwright/test'
import {
	closeSidebar,
	gotoLaunchPad,
	openAddWidgetModal,
} from './fixtures/widget-flow.ts'
import { BASE_URL } from './support/baseUrl.ts'
import {
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'

const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}

const API = '/index.php/apps/launchpad/api'
const STAMP = `${Date.now()}`
const EMPTY_TITLE = `Leeg ${STAMP}`
const FILLED_TITLE = `Inhoud ${STAMP}`
const LINK_TITLE = `Snelkoppeling ${STAMP}`
const LABEL_TEXT = `Hi ${STAMP}`
const TOP_LABEL_TEXT = `Bovenaan ${STAMP}`
const TILE_TITLE = `Bestanden ${STAMP}`
const LOGO = `${BASE_URL}/core/img/logo/logo.svg`

type Placement = {
	type: string
	content: Record<string, unknown>
	gridX?: number
	gridY?: number
	gridWidth?: number
	gridHeight?: number
}

let api: APIRequestContext
let dashboard: SeededDashboard | null = null

/*
 * The admin over basic auth, with an explicitly empty jar so the project's
 * stored browser session is not sent along.
 */
async function adminApi(): Promise<APIRequestContext> {
	return request.newContext({
		baseURL: BASE_URL,
		storageState: { cookies: [], origins: [] },
		extraHTTPHeaders: {
			'OCS-APIRequest': 'true',
			Authorization: `Basic ${Buffer.from(`${ADMIN.user}:${ADMIN.pass}`).toString('base64')}`,
		},
	})
}

/**
 * Add a placement to the seeded dashboard the way the store does.
 *
 * @param widgetId The widget type.
 * @param content The placement's content.
 * @param grid Its outer-grid position and size.
 * @return the created placement.
 */
async function addPlacement(
	widgetId: string,
	content: Record<string, unknown>,
	grid: { gridX: number; gridY: number; gridWidth: number; gridHeight: number },
): Promise<Record<string, any>> {
	const created = await api.post(`${API}/dashboard/${dashboard!.id}/widgets`, {
		data: { widgetId, ...grid, content },
	})
	expect(created.status(), await created.text()).toBe(201)
	return created.json()
}

/**
 * The placements on the seeded dashboard, read back as its owner.
 *
 * @return the placements.
 */
async function storedPlacements(): Promise<Array<Record<string, any>>> {
	const res = await api.get(`${API}/dashboard/${dashboard!.id}`)
	expect(res.status(), await res.text()).toBe(200)
	return (await res.json()).placements ?? []
}

/**
 * A chain of `levels` nested containers ending in a label: c1 → … → label.
 *
 * @param levels How many containers to nest.
 * @return the outermost child placement.
 */
function nestedChain(levels: number): Placement {
	if (levels === 0) {
		return {
			type: 'label',
			content: { text: `leaf ${STAMP}` },
			gridX: 0,
			gridY: 0,
			gridWidth: 2,
			gridHeight: 2,
		}
	}
	return {
		type: 'container',
		content: {
			placements: [nestedChain(levels - 1)],
			backgroundColor: 'transparent',
			padding: 'medium',
			title: '',
		},
		gridX: 0,
		gridY: 0,
		gridWidth: 4,
		gridHeight: 4,
	}
}

/**
 * The container on the page carrying `title`.
 *
 * @param page The workspace page.
 * @param title The container's title.
 * @return its locator.
 */
function containerTitled(page: Page, title: string) {
	return page
		.locator('.container-widget')
		.filter({
			has: page.locator('.container-widget__title', { hasText: title }),
		})
}

test.describe.configure({ mode: 'serial', timeout: 90_000 })

test.beforeAll(async () => {
	api = await adminApi()
	dashboard = await seedActiveDashboard(api, `E2E container widget ${STAMP}`)

	await addPlacement(
		'container',
		{
			placements: [],
			backgroundColor: 'transparent',
			padding: 'medium',
			title: EMPTY_TITLE,
		},
		{ gridX: 0, gridY: 0, gridWidth: 4, gridHeight: 4 },
	)
	await addPlacement(
		'container',
		{
			placements: [
				{
					type: 'label',
					content: { text: LABEL_TEXT },
					gridX: 0,
					gridY: 0,
					gridWidth: 2,
					gridHeight: 2,
				},
				{
					type: 'image',
					content: { url: LOGO, alt: 'Logo', fit: 'contain' },
					gridX: 2,
					gridY: 0,
					gridWidth: 2,
					gridHeight: 2,
				},
			],
			backgroundColor: 'transparent',
			padding: 'medium',
			title: FILLED_TITLE,
		},
		{ gridX: 4, gridY: 0, gridWidth: 4, gridHeight: 4 },
	)
	await addPlacement(
		'container',
		{
			placements: [
				{
					type: 'tile',
					content: {
						title: TILE_TITLE,
						icon: 'icon-folder',
						iconType: 'class',
						backgroundColor: '#0082c9',
						textColor: '#ffffff',
						linkType: 'app',
						linkValue: '/apps/files',
					},
					gridX: 0,
					gridY: 0,
					gridWidth: 4,
					gridHeight: 2,
				},
			],
			backgroundColor: 'transparent',
			padding: 'medium',
			title: LINK_TITLE,
		},
		{ gridX: 8, gridY: 0, gridWidth: 4, gridHeight: 4 },
	)
	// A label on the top-level grid, to compare a child's renderer against.
	await addPlacement(
		'label',
		{ text: TOP_LABEL_TEXT },
		{ gridX: 0, gridY: 4, gridWidth: 4, gridHeight: 1 },
	)
})

test.afterAll(async () => {
	await removeSeededDashboard(api, dashboard)
	await api.dispose()
})

test.describe('the Add Widget modal', () => {
	test.beforeEach(async ({ page }) => {
		await gotoLaunchPad(page)
		await openAddWidgetModal(page)
	})

	test.afterEach(async ({ page }) => {
		const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
		if (await dialog.isVisible().catch(() => false)) {
			await dialog.getByRole('button', { name: /^cancel$/i }).click()
		}
		await closeSidebar(page)
	})

	// @e2e container-widget::container-appears-in-picker
	test('the type picker offers Container and mounts its form', async ({
		page,
	}) => {
		const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
		const picker = dialog.getByLabel(/widget type/i)
		await expect(picker).toBeVisible()
		await expect(picker.locator('option[value="container"]')).toHaveCount(1)

		await expect(dialog.locator('.container-widget-form')).toHaveCount(0)
		await picker.selectOption('container')
		await expect(picker).toHaveValue('container')
		const form = dialog.locator(
			'.cn-add-widget-modal__form .container-widget-form',
		)
		await expect(form).toBeVisible()
		await expect(form.locator('.cn-container-form')).toBeVisible()
	})

	// @e2e container-widget::form-has-three-fields
	test('the container form has the three communal fields, Sort tiles, and nothing for children', async ({
		page,
	}) => {
		const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
		await dialog.getByLabel(/widget type/i).selectOption('container')
		const form = dialog.locator('.container-widget-form')
		await expect(form).toBeVisible()

		// The communal form: background colour, padding, title. Exactly one of each.
		const communal = form.locator('.cn-container-form')
		await expect(communal.locator('.cn-color-picker')).toHaveCount(1)
		await expect(
			communal.getByRole('button', { name: /open color picker/i }),
		).toBeVisible()
		await expect(communal.locator('.nc-select')).toHaveCount(1)
		await expect(communal.getByLabel('Padding', { exact: true })).toBeVisible()
		await expect(
			communal.getByRole('textbox', { name: /title \(optional\)/i }),
		).toHaveCount(1)

		// LaunchPad adds exactly one control next to it: "Sort tiles".
		await expect(form.locator(':scope > *')).toHaveCount(2)
		const added = form.locator(':scope > *').nth(1)
		await expect(added).toHaveClass(/nc-select/)
		await expect(added.getByLabel('Sort tiles', { exact: true })).toBeVisible()
		await expect(form.locator('.nc-select')).toHaveCount(2)
		await expect(form.locator('.cn-color-picker')).toHaveCount(1)

		// Nothing to manage children with.
		await expect(
			form.getByRole('button', { name: /child|widget|add|remove/i }),
		).toHaveCount(0)
		await expect(form.getByRole('list')).toHaveCount(0)
		await expect(form.getByRole('table')).toHaveCount(0)
		await expect(form.locator('.container-widget, .grid-stack')).toHaveCount(0)
	})
})

test.describe('on the dashboard', () => {
	test.beforeEach(async ({ page }) => {
		await gotoLaunchPad(page)
	})

	// @e2e container-widget::inner-grid-initialised-with-correct-config
	test('an empty container starts a 4-column inner GridStack', async ({
		page,
	}) => {
		const container = containerTitled(page, EMPTY_TITLE)
		await expect(container).toBeVisible()
		const inner = container.locator('.launchpad-container-grid')
		await expect(inner).toHaveCount(1)

		// The grid is started after a lazy import; wait until it is there.
		await expect
			.poll(() => inner.evaluate((el) => Boolean((el as any).gridstack)))
			.toBe(true)
		const grid = await inner.evaluate((el) => {
			const instance = (el as any).gridstack
			return {
				tag: el.tagName,
				insideContainer:
					el.parentElement?.closest('.container-widget') !== null,
				ownElement: instance.el === el,
				column: instance.getColumn(),
				cellHeight: instance.getCellHeight(),
				margin: instance.getMargin(),
			}
		})
		expect(grid.tag).toBe('DIV')
		expect(grid.insideContainer).toBe(true)
		expect(grid.ownElement).toBe(true)
		expect(grid.column).toBe(4)
		expect(grid.cellHeight).toBe(40)
		expect(grid.margin).toBe(4)
		await expect(inner).toHaveClass(/\bgs-4\b/)
	})

	// @e2e container-widget::child-widgets-render-via-the-registry-dispatcher
	test('a label child and an image child render as those widgets inside the inner grid', async ({
		page,
	}) => {
		const container = containerTitled(page, FILLED_TITLE)
		await expect(container).toBeVisible()
		const children = container.locator(
			'.launchpad-container-grid > .container-widget__child',
		)
		await expect(children).toHaveCount(2)

		// Dispatched through the registry: no child fell back to "unknown type".
		await expect(container.locator('.container-child--unknown')).toHaveCount(0)

		const first = children.nth(0)
		await expect(first.locator('.cn-label-widget')).toHaveCount(1)
		await expect(first.locator('.cn-label-widget__text')).toHaveText(LABEL_TEXT)
		await expect(first.locator('.cn-image-widget')).toHaveCount(0)

		const second = children.nth(1)
		await expect(second.locator('.cn-image-widget')).toHaveCount(1)
		await expect(second.locator('img.cn-image-widget__img')).toHaveAttribute(
			'src',
			LOGO,
		)
		await expect(second.locator('.cn-label-widget')).toHaveCount(0)

		// The same renderer the top-level grid uses for a label.
		const topLevel = page
			.locator('.cn-label-widget')
			.filter({ hasText: TOP_LABEL_TEXT })
		await expect(topLevel).toHaveCount(1)
		await expect(
			topLevel.locator(
				'xpath=ancestor::*[contains(@class,"container-widget")]',
			),
		).toHaveCount(0)
	})

	// @e2e container-widget::click-on-child-fires-childs-handler-not-containers
	test('a click on a tile inside a container reaches the tile and opens Files', async ({
		page,
	}) => {
		const container = containerTitled(page, LINK_TITLE)
		await expect(container).toBeVisible()
		const link = container
			.locator('.cn-dash-tile-widget__link')
			.filter({ hasText: TILE_TITLE })
		await expect(link).toBeVisible()

		// The container itself takes no pointer events in view mode.
		expect(
			await container.evaluate((el) => getComputedStyle(el).pointerEvents),
		).toBe('none')

		// What is under the cursor at the tile's centre is the tile, not the container.
		const box = await link.boundingBox()
		expect(box).not.toBeNull()
		const hit = await page.evaluate(
			({ x, y }) => {
				const el = document.elementFromPoint(x, y)
				return el?.closest('.cn-dash-tile-widget__link') !== null
			},
			{ x: box!.x + box!.width / 2, y: box!.y + box!.height / 2 },
		)
		expect(hit, 'the point under the tile does not belong to the tile').toBe(
			true,
		)

		// Watch the click bubble to the document: if a container handler
		// stopped or cancelled it, this record is never written or reads
		// defaultPrevented. Session storage survives the navigation.
		await page.evaluate(() => {
			document.addEventListener('click', (event) => {
				const target = event.target as Element | null
				window.sessionStorage.setItem(
					'e2e-container-click',
					JSON.stringify({
						onTile:
							target?.closest('.cn-dash-tile-widget__link') !== null,
						defaultPrevented: event.defaultPrevented,
					}),
				)
			})
		})

		await Promise.all([
			page.waitForURL((url) => /\/apps\/files(\/|$)/.test(url.pathname), {
				timeout: 20_000,
			}),
			link.click(),
		])
		expect(new URL(page.url()).pathname).toMatch(
			/\/index\.php\/apps\/files(\/|$)/,
		)

		const seen = await page.evaluate(() =>
			window.sessionStorage.getItem('e2e-container-click'),
		)
		expect(seen, 'the click never reached the document').not.toBeNull()
		expect(JSON.parse(seen!)).toEqual({ onTile: true, defaultPrevented: false })
	})
})

test.describe('the nesting limit', () => {
	// @e2e container-widget::depth-3-is-allowed
	test('three nested containers are accepted and stored', async () => {
		const before = (await storedPlacements()).length
		const content = {
			placements: [nestedChain(3)],
			backgroundColor: 'transparent',
			padding: 'medium',
			title: '',
		}

		const created = await addPlacement('container', content, {
			gridX: 0,
			gridY: 8,
			gridWidth: 4,
			gridHeight: 4,
		})
		expect(Number(created.id)).toBeGreaterThan(0)

		const after = await storedPlacements()
		expect(after).toHaveLength(before + 1)
		const stored = after.find((entry) => Number(entry.id) === Number(created.id))
		// Three containers deep, then the label.
		const c1 = stored?.content?.placements?.[0]
		const c2 = c1?.content?.placements?.[0]
		const c3 = c2?.content?.placements?.[0]
		expect([c1?.type, c2?.type, c3?.type]).toEqual([
			'container',
			'container',
			'container',
		])
		expect(c3?.content?.placements?.[0]?.type).toBe('label')
	})

	// @e2e container-widget::depth-4-is-rejected
	test('four nested containers are refused with 400 and nothing is stored', async () => {
		const before = await storedPlacements()
		const content = {
			placements: [nestedChain(4)],
			backgroundColor: 'transparent',
			padding: 'medium',
			title: '',
		}

		const refused = await api.post(`${API}/dashboard/${dashboard!.id}/widgets`, {
			data: {
				widgetId: 'container',
				gridX: 0,
				gridY: 12,
				gridWidth: 4,
				gridHeight: 4,
				content,
			},
		})
		expect(refused.status(), await refused.text()).toBe(400)
		expect(await refused.json()).toMatchObject({
			status: 'error',
			error: 'container_depth_exceeded',
			maxDepth: 3,
		})

		const after = await storedPlacements()
		expect(after.map((entry) => entry.id).sort()).toEqual(
			before.map((entry) => entry.id).sort(),
		)
	})
})
