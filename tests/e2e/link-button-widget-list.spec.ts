/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the link-button widget's list mode and its
 * legacy-placement rendering (openspec/specs/link-button-widget,
 * REQ-LBLM-001..005, 007..009), written for the e2e-coverage-programme change.
 *
 * Every placement is seeded through the API with the content its scenario
 * names, including the legacy shapes (no `displayMode`, no `links`) that only
 * an older placement or an import can still carry. The assertions read the
 * rendered DOM, its computed layout, and what a click actually opens.
 *
 * Gate-19 @e2e traceability:
 *   @e2e link-button-widget::existing-placements-remain-valid-without-displaymode
 *   @e2e link-button-widget::single-button-mode-ignores-links-array
 *   @e2e link-button-widget::links-array-can-be-empty-for-button-mode
 *   @e2e link-button-widget::list-item-with-external-link
 *   @e2e link-button-widget::an-action-id-entry-is-never-rendered-as-a-link
 *   @e2e link-button-widget::list-item-with-createfile-action
 *   @e2e link-button-widget::list-item-click-suppressed-in-edit-mode
 *   @e2e link-button-widget::custom-icon-url-in-list-item
 *   @e2e link-button-widget::icon-name-in-list-item
 *   @e2e link-button-widget::no-icon-in-list-item
 *   @e2e link-button-widget::vertical-list-orientation
 *   @e2e link-button-widget::horizontal-list-orientation
 *   @e2e link-button-widget::list-item-spacing
 *   @e2e link-button-widget::orientation-and-gap-default-to-vertical-and-normal
 *   @e2e link-button-widget::button-mode-with-empty-links-falls-back-to-legacy-fields
 *   @e2e link-button-widget::vertical-list-renders-as-ul-with-li
 *   @e2e link-button-widget::horizontal-list-renders-as-div-with-flex
 *   @e2e link-button-widget::list-item-uses-button-styling
 *   @e2e link-button-widget::hover-effect-applies-to-list-items
 *   @e2e link-button-widget::pre-list-mode-placement-renders-with-legacy-fields
 *
 * @spec openspec/specs/link-button-widget/spec.md
 */

import type { APIRequestContext, Locator, Page, Request } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, test } from '@playwright/test'
import { removeSeededDashboard, seedActiveDashboard } from './support/dashboardFixture.ts'
import { enterEditMode } from './support/dashboardSwitcher.ts'
import {
	adminApi,
	BUTTON,
	collectPopups,
	davDelete,
	davFileId,
	expectSelected,
	liftOf,
	LIST_ITEM,
	MODAL,
	openEditForm,
	openWithWidget,
	readPlacement,
	removePlacements,
	seedLink,
	stubHost,
} from './support/linkButton.ts'

const RUN = Date.now()

let api: APIRequestContext
let dashboard: SeededDashboard | null = null
const seeded: number[] = []

test.beforeAll(async () => {
	api = await adminApi()
	dashboard = await seedActiveDashboard(api, `E2E Link List ${RUN}`)
})

test.afterEach(async () => {
	await removePlacements(api, seeded)
})

test.afterAll(async () => {
	await removeSeededDashboard(api, dashboard)
	await api?.dispose()
})

/**
 * Seed a placement on this file's dashboard and remember it for cleanup.
 *
 * @param content the placement content.
 * @param box optional grid box.
 * @param box.gridX column.
 * @param box.gridWidth width in columns.
 * @param box.gridHeight height in rows.
 * @return the placement id.
 */
async function place(
	content: Record<string, unknown>,
	box: { gridX?: number; gridWidth?: number; gridHeight?: number } = {},
): Promise<number> {
	const id = await seedLink(api, dashboard!.id, content, box)
	seeded.push(id)
	return id
}

/** Three short links A, B, C. */
function threeLinks(): Array<Record<string, string>> {
	return ['A', 'B', 'C'].map((l) => ({
		label: `Link ${l}`,
		url: `https://example.com/${l.toLowerCase()}`,
		actionType: 'url',
		icon: '',
	}))
}

/** The bounding boxes of locators, in order. */
async function boxes(items: Locator): Promise<Array<{ x: number; y: number; width: number; height: number }>> {
	const out = []
	for (let i = 0; i < (await items.count()); i++) {
		const box = await items.nth(i).boundingBox()
		expect(box, `item ${i} has no box`).not.toBeNull()
		out.push(box!)
	}
	return out
}

/** The root font size in px, which `rem` resolves against. */
async function remPx(page: Page): Promise<number> {
	return page.evaluate(() => parseFloat(getComputedStyle(document.documentElement).fontSize))
}

test.describe('link button: legacy and button-mode placements (REQ-LBLM-001, 002, 007, 009)', () => {
	// @e2e link-button-widget::existing-placements-remain-valid-without-displaymode
	test('a placement without displayMode is read back as-is and renders as one button', async ({
		context,
		page,
	}) => {
		await stubHost(context, 'https://example.com')
		const id = await place({ label: 'Legacy', url: 'https://example.com/legacy', actionType: 'external' })

		const read = await readPlacement(api, dashboard!.id, id)
		expect(read.widgetId).toBe('link')
		// Nothing was migrated into it: it still has no displayMode, so the
		// button reading is the implicit one.
		expect(read.content).not.toHaveProperty('displayMode')
		expect(read.content).toMatchObject({ label: 'Legacy', url: 'https://example.com/legacy' })

		const widget = await openWithWidget(page, id)
		await expect(widget.locator('[role="list"]')).toHaveCount(0)
		const button = widget.locator(BUTTON)
		await expect(button).toHaveCount(1)
		await expect(button).toHaveText('Legacy')

		const popupPromise = context.waitForEvent('page')
		await button.click()
		const popup = await popupPromise
		await popup.waitForLoadState()
		expect(popup.url()).toBe('https://example.com/legacy')
		await popup.close()
	})

	// @e2e link-button-widget::single-button-mode-ignores-links-array
	test('button mode renders the legacy fields and none of a populated links array', async ({ page }) => {
		const id = await place({
			displayMode: 'button',
			label: 'Legacy label',
			url: 'https://example.com/legacy',
			icon: '/apps/launchpad/resource/legacy.png',
			actionType: 'external',
			links: [
				{ label: 'From links one', url: 'https://example.com/one', actionType: 'url', icon: '/apps/launchpad/resource/one.png' },
				{ label: 'From links two', url: 'https://example.com/two', actionType: 'url', icon: '' },
			],
		})
		const widget = await openWithWidget(page, id)
		await expect(widget.locator('[role="list"]')).toHaveCount(0)
		await expect(widget.locator(LIST_ITEM)).toHaveCount(0)

		const button = widget.locator(BUTTON)
		await expect(button).toHaveCount(1)
		await expect(button).toHaveText('Legacy label')
		await expect(button).toHaveAttribute('href', /^https:\/\/example\.com\/legacy\/?$/)
		await expect(button.locator('img')).toHaveAttribute('src', '/apps/launchpad/resource/legacy.png')
		await expect(widget).not.toContainText('From links')
	})

	// @e2e link-button-widget::links-array-can-be-empty-for-button-mode
	test('button mode with an empty links array falls back to the legacy fields without an error', async ({
		page,
	}) => {
		const errors: string[] = []
		page.on('pageerror', (e) => errors.push(e.message))
		const id = await place({
			displayMode: 'button',
			label: 'Fallback',
			url: 'https://example.com/fallback',
			actionType: 'external',
			links: [],
		})
		const widget = await openWithWidget(page, id)
		const button = widget.locator(BUTTON)
		await expect(button).toHaveText('Fallback')
		await expect(button).toHaveAttribute('href', /^https:\/\/example\.com\/fallback\/?$/)
		await expect(widget.locator('[role="list"]')).toHaveCount(0)
		expect(errors).toEqual([])
	})

	// @e2e link-button-widget::button-mode-with-empty-links-falls-back-to-legacy-fields
	test('button mode with empty links opens the legacy URL on click', async ({ context, page }) => {
		await stubHost(context, 'https://example.com')
		const errors: string[] = []
		page.on('pageerror', (e) => errors.push(e.message))
		const id = await place({
			displayMode: 'button',
			links: [],
			label: 'Example',
			url: 'https://example.com',
			icon: 'external',
			actionType: 'external',
		})
		const widget = await openWithWidget(page, id)
		const button = widget.locator(BUTTON)
		await expect(button).toHaveText('Example')

		const popupPromise = context.waitForEvent('page')
		await button.click()
		const popup = await popupPromise
		await popup.waitForLoadState()
		expect(popup.url()).toBe('https://example.com/')
		await popup.close()
		expect(errors).toEqual([])
	})

	// @e2e link-button-widget::pre-list-mode-placement-renders-with-legacy-fields
	test('a pre-list-mode placement renders and works from its legacy fields', async ({ context, page }) => {
		await stubHost(context, 'https://example.com')
		const errors: string[] = []
		page.on('pageerror', (e) => errors.push(e.message))
		const id = await place({ url: 'https://example.com', icon: 'external', label: 'Go' })

		const widget = await openWithWidget(page, id)
		await expect(widget.locator('[role="list"]')).toHaveCount(0)
		const button = widget.locator(BUTTON)
		await expect(button).toHaveCount(1)
		await expect(button.locator('.cn-link-button-widget__label')).toHaveText('Go')
		// The icon field is honoured too: an icon slot is rendered for it.
		await expect(button.locator('.cn-link-button-widget__icon')).toHaveCount(1)

		// No actionType at all: the pre-list default is an external link.
		const popupPromise = context.waitForEvent('page')
		await button.click()
		const popup = await popupPromise
		await popup.waitForLoadState()
		expect(popup.url()).toBe('https://example.com/')
		await popup.close()
		expect(errors).toEqual([])
	})
})

test.describe('link button: list actions (REQ-LBLM-003)', () => {
	// @e2e link-button-widget::list-item-with-external-link
	test('clicking an external list item opens its URL in a new tab', async ({ context, page }) => {
		await stubHost(context, 'https://example.com')
		const id = await place({
			displayMode: 'list',
			links: [
				{ label: 'Example', url: 'https://example.com', actionType: 'url', icon: '' },
				{ label: 'Other', url: 'https://example.com/other', actionType: 'url', icon: '' },
			],
		})
		const widget = await openWithWidget(page, id)
		const item = widget.locator(LIST_ITEM).filter({ hasText: 'Example' })

		const popupPromise = context.waitForEvent('page')
		await item.click()
		const popup = await popupPromise
		await popup.waitForLoadState()
		expect(popup.url()).toBe('https://example.com/')
		expect(await popup.evaluate(() => window.opener === null)).toBe(true)
		expect(page.url()).toContain('/apps/launchpad')
		await popup.close()
	})

	// @e2e link-button-widget::list-item-with-createfile-action
	test('a createFile list item opens the modal with its extension and creates a .docx', async ({ page }) => {
		const base = `List-Report-${RUN}`
		const file = `${base}.docx`
		try {
			const id = await place({
				displayMode: 'list',
				links: [
					{ label: 'New Report', actionType: 'createFile', value: 'docx', url: '', icon: '' },
					{ label: 'Elsewhere', url: 'https://example.com', actionType: 'url', icon: '' },
				],
			})
			const widget = await openWithWidget(page, id)
			await widget.locator(LIST_ITEM).filter({ hasText: 'New Report' }).click()

			const modal = page.locator(MODAL)
			await expect(modal).toBeVisible()
			await expect(modal.locator('.link-button-host__modal-extension')).toHaveText('.docx')
			await modal.locator('.link-button-host__modal-input').fill(base)

			const popupPromise = page.context().waitForEvent('page')
			const created = page.waitForResponse((r) => r.url().includes('/api/files/create'))
			await modal.locator('.link-button-host__modal-create').click()
			const res = await created
			expect(res.status()).toBe(200)
			expect(res.request().postDataJSON()).toMatchObject({ filename: file })
			const popup = await popupPromise
			await popup.close()
			await expect(modal).toHaveCount(0)

			expect(await davFileId(api, file)).not.toBeNull()
		} finally {
			await davDelete(api, file)
		}
	})

	// @e2e link-button-widget::list-item-click-suppressed-in-edit-mode
	test('in edit mode, clicking list items opens nothing, posts nothing and shows no modal', async ({
		context,
		page,
	}) => {
		await stubHost(context, 'https://example.com')
		const id = await place({
			displayMode: 'list',
			links: [
				{ label: 'Example', url: 'https://example.com', actionType: 'url', icon: '' },
				{ label: 'New Report', actionType: 'createFile', value: 'docx', url: '', icon: '' },
			],
		})
		const widget = await openWithWidget(page, id)
		await enterEditMode(page)

		const popups = collectPopups(context)
		const creates: Request[] = []
		page.on('request', (r) => {
			if (r.url().includes('/api/files/create')) {
				creates.push(r)
			}
		})

		await widget.locator(LIST_ITEM).filter({ hasText: 'Example' }).click()
		await widget.locator(LIST_ITEM).filter({ hasText: 'New Report' }).click()
		await page.waitForTimeout(1_500)

		expect(popups.map((p) => p.url()), 'no tab may open in edit mode').toEqual([])
		expect(creates.length).toBe(0)
		await expect(page.locator(MODAL)).toHaveCount(0)
		await expect(page.locator('.launchpad-edit-mode')).toBeVisible()
	})

	// @e2e link-button-widget::an-action-id-entry-is-never-rendered-as-a-link
	test('an action_id list item is dispatched as an action, never opened as a link', async ({
		context,
		page,
	}) => {
		const id = await place({
			displayMode: 'list',
			links: [
				{ label: 'Files', url: 'open-files', actionType: 'action_id', icon: '' },
				{ label: 'Example', url: 'https://example.com', actionType: 'url', icon: '' },
			],
		})
		const warnings: string[] = []
		page.on('console', (m) => {
			if (m.type() === 'warning') {
				warnings.push(m.text())
			}
		})
		const widget = await openWithWidget(page, id)
		const item = widget.locator(LIST_ITEM).filter({ hasText: 'Files' })
		await expect(item).toHaveJSProperty('tagName', 'BUTTON')
		await expect(item).not.toHaveAttribute('href', /.*/)

		const popups = collectPopups(context)
		await item.click()
		// Nothing registers `open-files`, so reaching the registry shows as its warning.
		await expect.poll(() => warnings).toContain('Unknown internal action: open-files')
		expect(popups.map((p) => p.url())).toEqual([])
	})
})

test.describe('link button: list icons (REQ-LBLM-004)', () => {
	// @e2e link-button-widget::custom-icon-url-in-list-item
	test('a URL icon renders as an image followed by the label', async ({ page }) => {
		const id = await place({
			displayMode: 'list',
			links: [
				{ label: 'Q4 Report', url: 'https://example.com/q4', actionType: 'url', icon: '/apps/launchpad/icons/report.png' },
				{ label: 'Plain', url: 'https://example.com/plain', actionType: 'url', icon: '' },
			],
		})
		const widget = await openWithWidget(page, id)
		const item = widget.locator(LIST_ITEM).filter({ hasText: 'Q4 Report' })
		const img = item.locator('img')
		await expect(img).toHaveCount(1)
		await expect(img).toHaveAttribute('src', '/apps/launchpad/icons/report.png')

		const imgBox = await img.boundingBox()
		const labelBox = await item.locator('.cn-link-button-widget__list-label').boundingBox()
		expect(Math.round(imgBox!.width)).toBe(24)
		expect(Math.round(imgBox!.height)).toBe(24)
		// Followed by the label: inline, to its right, on the same line.
		expect(labelBox!.x).toBeGreaterThanOrEqual(imgBox!.x + imgBox!.width - 1)
		expect(labelBox!.y).toBeLessThan(imgBox!.y + imgBox!.height)
		expect(labelBox!.y + labelBox!.height).toBeGreaterThan(imgBox!.y)
		await expect(item.locator('.cn-link-button-widget__list-label')).toHaveText('Q4 Report')
	})

	// @e2e link-button-widget::icon-name-in-list-item
	test('an icon name renders the MDI folder icon followed by the label', async ({ page }) => {
		const id = await place({
			displayMode: 'list',
			links: [
				{ label: 'Browse files', url: 'https://example.com/files', actionType: 'url', icon: 'folder' },
				{ label: 'Plain', url: 'https://example.com/plain', actionType: 'url', icon: '' },
			],
		})
		const widget = await openWithWidget(page, id)
		const item = widget.locator(LIST_ITEM).filter({ hasText: 'Browse files' })
		const icon = item.locator('.cn-link-button-widget__list-icon .material-design-icon')
		await expect(icon).toHaveCount(1)
		// A folder glyph, not the help-circle fallback for an unknown name.
		await expect(icon).toHaveClass(/\bfolder(-outline)?-icon\b/)
		await expect(icon).not.toHaveClass(/help-circle/)
		await expect(icon.locator('svg')).toHaveCount(1)
		await expect(item.locator('img')).toHaveCount(0)

		const iconBox = await icon.boundingBox()
		const labelBox = await item.locator('.cn-link-button-widget__list-label').boundingBox()
		expect(labelBox!.x).toBeGreaterThanOrEqual(iconBox!.x + iconBox!.width - 1)
		await expect(item.locator('.cn-link-button-widget__list-label')).toHaveText('Browse files')
	})

	// @e2e link-button-widget::no-icon-in-list-item
	test('an empty icon renders the label alone', async ({ page }) => {
		const id = await place({
			displayMode: 'list',
			links: [
				{ label: 'Click here', url: 'https://example.com/here', actionType: 'url', icon: '' },
				{ label: 'With icon', url: 'https://example.com/icon', actionType: 'url', icon: 'folder' },
			],
		})
		const widget = await openWithWidget(page, id)
		const item = widget.locator(LIST_ITEM).filter({ hasText: 'Click here' })
		await expect(item.locator('img')).toHaveCount(0)
		await expect(item.locator('svg')).toHaveCount(0)
		await expect(item.locator('.cn-link-button-widget__list-icon')).toHaveCount(0)
		await expect(item).toHaveText('Click here')
		// The sibling with an icon does draw one, so the selector can see icons.
		await expect(widget.locator(LIST_ITEM).filter({ hasText: 'With icon' }).locator('svg')).toHaveCount(1)
	})
})

test.describe('link button: list layout (REQ-LBLM-005, 008)', () => {
	// @e2e link-button-widget::vertical-list-orientation
	test('a vertical list stacks its items in a ul role=list', async ({ page }) => {
		const id = await place({ displayMode: 'list', listOrientation: 'vertical', links: threeLinks() }, { gridHeight: 5 })
		const widget = await openWithWidget(page, id)
		const list = widget.locator('ul[role="list"]')
		await expect(list).toHaveCount(1)
		await expect(widget.locator('div[role="list"]')).toHaveCount(0)

		const items = list.locator(':scope > li')
		await expect(items).toHaveCount(3)
		const b = await boxes(items)
		expect(b[1].y).toBeGreaterThanOrEqual(b[0].y + b[0].height - 1)
		expect(b[2].y).toBeGreaterThanOrEqual(b[1].y + b[1].height - 1)
		expect(Math.abs(b[1].x - b[0].x)).toBeLessThan(1)
		expect(Math.abs(b[2].x - b[0].x)).toBeLessThan(1)
	})

	// @e2e link-button-widget::horizontal-list-orientation
	test('a horizontal list lays its items out inline in a wrapping div role=list', async ({ page }) => {
		const id = await place(
			{ displayMode: 'list', listOrientation: 'horizontal', links: threeLinks() },
			{ gridWidth: 12 },
		)
		const widget = await openWithWidget(page, id)
		const list = widget.locator('div[role="list"]')
		await expect(list).toHaveCount(1)
		await expect(widget.locator('ul[role="list"]')).toHaveCount(0)
		expect(await list.evaluate((el) => getComputedStyle(el).flexWrap)).toBe('wrap')
		expect(await list.evaluate((el) => getComputedStyle(el).display)).toBe('flex')

		const items = list.locator(':scope > [role="listitem"]')
		await expect(items).toHaveCount(3)
		const b = await boxes(items)
		// One row, left to right.
		expect(Math.abs(b[1].y - b[0].y)).toBeLessThan(1)
		expect(Math.abs(b[2].y - b[0].y)).toBeLessThan(1)
		expect(b[1].x).toBeGreaterThanOrEqual(b[0].x + b[0].width - 1)
		expect(b[2].x).toBeGreaterThanOrEqual(b[1].x + b[1].width - 1)
	})

	// @e2e link-button-widget::list-item-spacing
	test('spacious spacing puts a 1.5rem gap between items', async ({ page }) => {
		const id = await place(
			{ displayMode: 'list', listOrientation: 'vertical', listItemGap: 'spacious', links: threeLinks() },
			{ gridHeight: 6 },
		)
		const widget = await openWithWidget(page, id)
		const list = widget.locator('[role="list"]')
		expect(await list.evaluate((el) => (el as HTMLElement).style.getPropertyValue('gap'))).toBe('1.5rem')
		const rem = await remPx(page)
		const gap = await list.evaluate((el) => getComputedStyle(el).rowGap)
		expect(parseFloat(gap)).toBeCloseTo(1.5 * rem, 1)

		// And the items really are that far apart.
		const b = await boxes(list.locator(':scope > li'))
		expect(b[1].y - (b[0].y + b[0].height)).toBeCloseTo(1.5 * rem, 0)
	})

	// @e2e link-button-widget::orientation-and-gap-default-to-vertical-and-normal
	test('a list without orientation or spacing is vertical with normal spacing', async ({ page }) => {
		const id = await place({ displayMode: 'list', links: threeLinks() }, { gridHeight: 5 })
		const read = await readPlacement(api, dashboard!.id, id)
		expect(read.content).not.toHaveProperty('listOrientation')
		expect(read.content).not.toHaveProperty('listItemGap')

		const widget = await openWithWidget(page, id)
		const list = widget.locator('ul[role="list"]')
		await expect(list).toHaveCount(1)
		const rem = await remPx(page)
		expect(parseFloat(await list.evaluate((el) => getComputedStyle(el).rowGap))).toBeCloseTo(rem, 1)

		// The editor reads the placement the same way.
		const dialog = await openEditForm(page, id)
		await expectSelected(dialog, 'List orientation', 'Vertical (list)')
		await expectSelected(dialog, 'List item spacing', 'Normal')
	})

	// @e2e link-button-widget::vertical-list-renders-as-ul-with-li
	test('a vertical list is exactly a ul role=list holding one li per link', async ({ page }) => {
		const links = threeLinks().slice(0, 2)
		const id = await place({ displayMode: 'list', listOrientation: 'vertical', links })
		const widget = await openWithWidget(page, id)
		const list = widget.locator('[role="list"]')
		await expect(list).toHaveCount(1)
		expect(await list.evaluate((el) => el.tagName)).toBe('UL')
		expect(await list.evaluate((el) => Array.from(el.children).map((c) => c.tagName))).toEqual(['LI', 'LI'])
		await expect(list.locator(':scope > li').nth(0)).toContainText('Link A')
		await expect(list.locator(':scope > li').nth(1)).toContainText('Link B')
		// Each item carries an accessible name from its label.
		await expect(list.locator(LIST_ITEM).nth(0)).toHaveAttribute('aria-label', 'Link A')
	})

	// @e2e link-button-widget::horizontal-list-renders-as-div-with-flex
	test('a horizontal list is a flex, wrapping div role=list of div role=listitem', async ({ page }) => {
		const links = threeLinks().slice(0, 2)
		const id = await place({ displayMode: 'list', listOrientation: 'horizontal', links }, { gridWidth: 12 })
		const widget = await openWithWidget(page, id)
		const list = widget.locator('[role="list"]')
		await expect(list).toHaveCount(1)
		const shape = await list.evaluate((el) => {
			const s = getComputedStyle(el)
			return {
				tag: el.tagName,
				display: s.display,
				wrap: s.flexWrap,
				inlineGap: (el as HTMLElement).style.getPropertyValue('gap'),
				children: Array.from(el.children).map((c) => `${c.tagName}:${c.getAttribute('role')}`),
			}
		})
		expect(shape).toEqual({
			tag: 'DIV',
			display: 'flex',
			wrap: 'wrap',
			inlineGap: '1rem',
			children: ['DIV:listitem', 'DIV:listitem'],
		})
	})

	// @e2e link-button-widget::list-item-uses-button-styling
	test('a list item applies its own colours as inline styles', async ({ page }) => {
		const id = await place({
			displayMode: 'list',
			links: [
				{ label: 'Blue', url: 'https://example.com/blue', actionType: 'url', icon: '', backgroundColor: '#0066cc', textColor: '#ffffff' },
				{ label: 'Default', url: 'https://example.com/default', actionType: 'url', icon: '' },
			],
		})
		const widget = await openWithWidget(page, id)
		const item = widget.locator(LIST_ITEM).filter({ hasText: 'Blue' })
		const style = await item.evaluate((el) => {
			const inline = (el as HTMLElement).style
			const computed = getComputedStyle(el)
			return {
				inlineBg: inline.backgroundColor,
				inlineFg: inline.color,
				bg: computed.backgroundColor,
				fg: computed.color,
			}
		})
		expect(style).toEqual({
			inlineBg: 'rgb(0, 102, 204)',
			inlineFg: 'rgb(255, 255, 255)',
			bg: 'rgb(0, 102, 204)',
			fg: 'rgb(255, 255, 255)',
		})
		// The sibling without colours uses the theme variables, not these.
		const other = await widget
			.locator(LIST_ITEM)
			.filter({ hasText: 'Default' })
			.evaluate((el) => (el as HTMLElement).style.getPropertyValue('background-color'))
		expect(other).toBe('var(--color-primary)')
	})

	// @e2e link-button-widget::hover-effect-applies-to-list-items
	test('hovering a list item lifts it by 2 px and adds a drop shadow', async ({ page }) => {
		const id = await place({ displayMode: 'list', links: threeLinks() }, { gridHeight: 5 })
		const widget = await openWithWidget(page, id)
		const item = widget.locator(LIST_ITEM).first()
		const sibling = widget.locator(LIST_ITEM).nth(1)

		await page.mouse.move(0, 0)
		expect((await liftOf(item)).transform).toBe('none')
		expect((await liftOf(item)).shadow).toBe('none')

		await item.hover()
		await expect.poll(async () => (await liftOf(item)).transform).toBe('matrix(1, 0, 0, 1, 0, -2)')
		expect((await liftOf(item)).shadow).toMatch(/rgba\(0, 0, 0, 0\.\d+\)/)
		// Only the hovered item lifts.
		expect((await liftOf(sibling)).transform).toBe('none')
	})
})
