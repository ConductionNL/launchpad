/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * How a tile placement renders, links, looks and is edited on the Workspace
 * page (tiles REQ-TILE-001 rendering, REQ-TILE-006, -008, -009, -010, -011,
 * REQ-TILE-PLACEMENT).
 *
 * A throwaway account owns one dashboard. Four tiles, one per icon type, are
 * placed through `POST /api/dashboard/{id}/tile`, which writes the legacy
 * inline `tile*` fields. The tests open the page as that account and read the
 * rendered tiles; the last test adds a tile through the product's own
 * "Add widget" → "Custom widget" flow and reads the stored placement back.
 *
 * Gate-19 @e2e traceability:
 *   @e2e tiles::existing-tile-placements-still-render
 *   @e2e tiles::render-emoji-icon
 *   @e2e tiles::render-css-class-icon
 *   @e2e tiles::render-url-icon
 *   @e2e tiles::render-svg-path-icon
 *   @e2e tiles::app-link-navigation
 *   @e2e tiles::external-url-navigation
 *   @e2e tiles::tile-link-hover-effect
 *   @e2e tiles::tile-background-and-text-colors-applied
 *   @e2e tiles::nl-design-system-css-override
 *   @e2e tiles::tile-fills-grid-cell-completely
 *   @e2e tiles::edit-button-hidden-in-view-mode
 *   @e2e tiles::edit-button-click-emits-event
 *   @e2e tiles::new-tile-placement-uses-standard-content-shape
 *
 * @spec openspec/specs/tiles/spec.md
 */

import type { Browser, Locator, Page } from '@playwright/test'
import type { OwnDashboard } from './support/ownDashboard.ts'

import { expect, test } from '@playwright/test'
import { enterEditMode, MORE_MENU } from './support/dashboardSwitcher.ts'
import {
	openOwnDashboard,
	readPlacements,
	setUpOwnDashboard,
	tearDownOwnDashboard,
} from './support/ownDashboard.ts'
import { API } from './support/principals.ts'

const STAMP = `${Date.now()}`
const EMOJI = `Bestanden ${STAMP}`
const CLASS = `Map ${STAMP}`
const URL_ICON = `Voorbeeld ${STAMP}`
const SVG = `Vorm ${STAMP}`
const SVG_PATH = 'M12 2L2 7v10l10 5 10-5V7z'
const ICON_URL = '/core/img/logo/logo.svg'

let own: OwnDashboard
const ids: Record<string, number> = {}

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

test.beforeAll(async () => {
	own = await setUpOwnDashboard('e2e-tile-render', `E2E tile rendering ${STAMP}`)
	const tiles: Array<[string, Record<string, unknown>]> = [
		[
			EMOJI,
			{
				icon: '📁',
				iconType: 'emoji',
				bgColor: '#3b82f6',
				textColor: '#ffffff',
				linkType: 'app',
				linkValue: 'files',
				gridX: 0,
			},
		],
		[
			CLASS,
			{
				icon: 'icon-folder',
				iconType: 'class',
				bgColor: '#0f766e',
				textColor: '#ffffff',
				linkType: 'app',
				linkValue: 'files',
				gridX: 2,
			},
		],
		[
			URL_ICON,
			{
				icon: ICON_URL,
				iconType: 'url',
				bgColor: '#1e293b',
				textColor: '#ffffff',
				linkType: 'url',
				linkValue: 'https://example.com',
				gridX: 4,
			},
		],
		[
			SVG,
			{
				icon: SVG_PATH,
				iconType: 'svg',
				bgColor: '#7c2d12',
				textColor: '#fde047',
				linkType: 'url',
				linkValue: 'https://example.com',
				gridX: 6,
			},
		],
	]
	for (const [title, data] of tiles) {
		const res = await own.owner.api.post(`${API}/dashboard/${own.id}/tile`, {
			data: { title, gridY: 0, ...data },
		})
		expect(res.status(), await res.text()).toBe(201)
		ids[title] = Number((await res.json()).id)
	}
})

test.afterAll(async () => {
	await tearDownOwnDashboard(own)
})

/**
 * The tile of a placement.
 *
 * @param page The page.
 * @param title The tile title.
 * @return The `.tile-widget` element.
 */
function tile(page: Page, title: string): Locator {
	return page.locator(`.grid-stack-item[gs-id="${ids[title]}"] .tile-widget`)
}

/**
 * Open the dashboard as its owner.
 *
 * @param browser The browser.
 * @return The context and page.
 */
async function open(browser: Browser) {
	const session = await openOwnDashboard(browser, own, Object.keys(ids).length)
	await expect(tile(session.page, EMOJI)).toBeVisible()
	return session
}

// @e2e tiles::existing-tile-placements-still-render
test('a tile placement in the legacy inline shape renders title, icon, colours and link without errors', async ({
	browser,
}) => {
	const stored = (await readPlacements(own.owner.api, own.id)).find(
		(p) => Number(p.id) === ids[EMOJI],
	)!
	expect(stored).toMatchObject({
		tileType: 'custom',
		tileTitle: EMOJI,
		tileIcon: '📁',
		tileLinkValue: 'files',
	})

	const errors: string[] = []
	const { context, page } = await openOwnDashboard(
		browser,
		own,
		Object.keys(ids).length,
	)
	try {
		page.on('console', (msg) => {
			if (msg.type() === 'error') {
				errors.push(msg.text())
			}
		})
		page.on('pageerror', (error) => errors.push(error.message))
		await page.reload()
		const legacy = tile(page, EMOJI)
		await expect(legacy).toBeVisible({ timeout: 30_000 })
		await expect(legacy.locator('.tile-widget__title')).toHaveText(EMOJI)
		await expect(legacy.locator('.tile-widget__emoji')).toHaveText('📁')
		expect(
			await legacy.evaluate((el) => getComputedStyle(el).backgroundColor),
		).toBe('rgb(59, 130, 246)')
		await expect(legacy.locator('a.tile-widget__link')).toHaveAttribute(
			'href',
			/\/apps\/files$/,
		)
		// Errors from the tile renderer itself; unrelated instance noise (a
		// missing favicon, a 404 from another app) is not this scenario's subject.
		expect(
			errors.filter((e) => /tile|TypeError|ReferenceError/i.test(e)),
		).toEqual([])
	} finally {
		await context.close()
	}
})

// @e2e tiles::render-emoji-icon
test('an emoji icon renders in .tile-widget__emoji at 64 px', async ({
	browser,
}) => {
	const { context, page } = await open(browser)
	try {
		const emoji = tile(page, EMOJI).locator('span.tile-widget__emoji')
		await expect(emoji).toHaveText('📁')
		expect(await emoji.evaluate((el) => getComputedStyle(el).fontSize)).toBe(
			'64px',
		)
	} finally {
		await context.close()
	}
})

// @e2e tiles::render-css-class-icon
test('a CSS-class icon is a 64 px span.icon with the class, filtered white', async ({
	browser,
}) => {
	const { context, page } = await open(browser)
	try {
		const icon = tile(page, CLASS).locator('span.icon')
		await expect(icon).toHaveClass(/(^|\s)icon-folder(\s|$)/)
		const style = await icon.evaluate((el) => {
			const s = getComputedStyle(el)
			return { width: s.width, height: s.height, filter: s.filter }
		})
		expect(style).toEqual({
			width: '64px',
			height: '64px',
			filter: 'brightness(0) invert(1)',
		})
	} finally {
		await context.close()
	}
})

// @e2e tiles::render-url-icon
test('a URL icon renders as an img, object-fit contain, no taller than the 64 px icon area', async ({
	browser,
}) => {
	const { context, page } = await open(browser)
	try {
		const img = tile(page, URL_ICON).locator('.tile-widget__icon img')
		await expect(img).toHaveAttribute('src', ICON_URL)
		expect(await img.evaluate((el) => getComputedStyle(el).objectFit)).toBe(
			'contain',
		)
		const area = await tile(page, URL_ICON)
			.locator('.tile-widget__icon')
			.boundingBox()
		const box = await img.boundingBox()
		expect(area!.height).toBeCloseTo(64, 0)
		expect(box!.height).toBeLessThanOrEqual(64.5)
	} finally {
		await context.close()
	}
})

// @e2e tiles::render-svg-path-icon
test('an SVG path renders inside svg viewBox="0 0 24 24" filled with the text colour', async ({
	browser,
}) => {
	const { context, page } = await open(browser)
	try {
		const svg = tile(page, SVG).locator('svg.tile-widget__icon')
		await expect(svg).toHaveAttribute('viewBox', '0 0 24 24')
		await expect(svg.locator('path')).toHaveAttribute('d', SVG_PATH)
		expect(await svg.evaluate((el) => getComputedStyle(el).fill)).toBe(
			'rgb(253, 224, 71)',
		)
	} finally {
		await context.close()
	}
})

// @e2e tiles::app-link-navigation
test('an app tile opens the app in the same window', async ({ browser }) => {
	const { context, page } = await open(browser)
	try {
		const link = tile(page, EMOJI).locator('a.tile-widget__link')
		await expect(link).toHaveAttribute('href', /^(\/index\.php)?\/apps\/files$/)
		await expect(link).toHaveAttribute('target', '_self')
		const pages = context.pages().length
		await link.click()
		await page.waitForURL(/\/apps\/files/, { timeout: 30_000 })
		expect(context.pages()).toHaveLength(pages)
	} finally {
		await context.close()
	}
})

// @e2e tiles::external-url-navigation
test('a URL tile opens in a new tab with rel="noopener noreferrer"', async ({
	browser,
}) => {
	const { context, page } = await open(browser)
	try {
		await context.route('https://example.com/**', (route) =>
			route.fulfill({
				status: 200,
				contentType: 'text/html',
				body: '<title>Example</title>',
			}),
		)
		const link = tile(page, URL_ICON).locator('a.tile-widget__link')
		await expect(link).toHaveAttribute('href', 'https://example.com')
		await expect(link).toHaveAttribute('target', '_blank')
		await expect(link).toHaveAttribute('rel', 'noopener noreferrer')
		const [popup] = await Promise.all([
			context.waitForEvent('page'),
			link.click(),
		])
		await popup.waitForURL(/^https:\/\/example\.com\/?$/)
		expect(page.url()).toMatch(/\/apps\/launchpad/)
	} finally {
		await context.close()
	}
})

// @e2e tiles::tile-link-hover-effect
test('hovering a tile scales it to 1.02 at 0.95 opacity', async ({ browser }) => {
	const { context, page } = await open(browser)
	try {
		const link = tile(page, EMOJI).locator('a.tile-widget__link')
		await link.hover()
		await expect
			.poll(() =>
				link.evaluate((el) => {
					const s = getComputedStyle(el)
					return { transform: s.transform, opacity: s.opacity }
				}),
			)
			.toEqual({
				transform: 'matrix(1.02, 0, 0, 1.02, 0, 0)',
				opacity: '0.95',
			})
	} finally {
		await context.close()
	}
})

// @e2e tiles::tile-background-and-text-colors-applied
test('the tile colours are set as --tile-bg-color and --tile-text-color and win over theme styles', async ({
	browser,
}) => {
	const { context, page } = await open(browser)
	try {
		const t = tile(page, EMOJI)
		expect(
			await t.evaluate((el) => el.style.getPropertyValue('--tile-bg-color')),
		).toBe('#3b82f6')
		expect(
			await t.evaluate((el) => el.style.getPropertyValue('--tile-text-color')),
		).toBe('#ffffff')
		const painted = await t.evaluate((el) => {
			const link = el.querySelector('.tile-widget__link') as HTMLElement
			const title = el.querySelector('.tile-widget__title') as HTMLElement
			return {
				tile: getComputedStyle(el).backgroundColor,
				link: getComputedStyle(link).backgroundColor,
				linkText: getComputedStyle(link).color,
				title: getComputedStyle(title).color,
			}
		})
		expect(painted).toEqual({
			tile: 'rgb(59, 130, 246)',
			link: 'rgb(59, 130, 246)',
			linkText: 'rgb(255, 255, 255)',
			title: 'rgb(255, 255, 255)',
		})
	} finally {
		await context.close()
	}
})

// @e2e tiles::nl-design-system-css-override
test('each tile injects a <style> scoped by data-tile-id that forces its title colour', async ({
	browser,
}) => {
	const { context, page } = await open(browser)
	try {
		const tileId = await tile(page, SVG).getAttribute('data-tile-id')
		expect(tileId).toBeTruthy()
		const style = page.locator(`head style#tile-${tileId}-style`)
		await expect(style).toHaveCount(1)
		const css = (await style.textContent()) ?? ''
		expect(css).toContain(
			`.tile-widget[data-tile-id="${tileId}"] .tile-widget__title`,
		)
		expect(css).toMatch(/color:\s*#fde047\s*!important/)
	} finally {
		await context.close()
	}
})

// @e2e tiles::tile-fills-grid-cell-completely
test('a tile fills its whole grid cell, with no radius and no border', async ({
	browser,
}) => {
	const { context, page } = await open(browser)
	try {
		const t = tile(page, CLASS)
		const style = await t.evaluate((el) => {
			const s = getComputedStyle(el)
			return {
				position: s.position,
				top: s.top,
				left: s.left,
				radius: s.borderTopLeftRadius,
				border: s.borderTopStyle,
				borderWidth: s.borderTopWidth,
			}
		})
		expect(style).toEqual({
			position: 'absolute',
			top: '0px',
			left: '0px',
			radius: '0px',
			border: 'none',
			borderWidth: '0px',
		})
		const cell = await page
			.locator(`.grid-stack-item[gs-id="${ids[CLASS]}"] .launchpad-grid-item`)
			.boundingBox()
		const box = await t.boundingBox()
		expect(Math.abs(box!.width - cell!.width)).toBeLessThanOrEqual(1)
		expect(Math.abs(box!.height - cell!.height)).toBeLessThanOrEqual(1)
	} finally {
		await context.close()
	}
})

// @e2e tiles::edit-button-hidden-in-view-mode
test('in view mode a tile has no edit control', async ({ browser }) => {
	const { context, page } = await open(browser)
	try {
		await expect(page.locator('.launchpad-edit-mode')).toHaveCount(0)
		for (const title of Object.keys(ids)) {
			await expect(
				tile(page, title).locator('.tile-widget__edit'),
			).toHaveCount(0)
			await expect(tile(page, title).getByRole('button')).toHaveCount(0)
		}
	} finally {
		await context.close()
	}
})

// @e2e tiles::edit-button-click-emits-event
test('choosing Edit on a tile in edit mode opens the tile editor and does not follow the link', async ({
	browser,
}) => {
	const { context, page } = await open(browser)
	try {
		await enterEditMode(page)
		const before = page.url()
		const cog = tile(page, EMOJI).getByRole('button', { name: 'Tile menu' })
		await expect(cog).toBeVisible()
		await cog.click()
		await page.getByRole('menuitem', { name: 'Edit tile' }).click()
		const editor = page.getByRole('dialog', { name: 'Edit Tile' })
		await expect(editor).toBeVisible()
		await expect(editor.getByLabel('Title')).toHaveValue(EMOJI)
		expect(page.url()).toBe(before)
		expect(context.pages()).toHaveLength(1)
	} finally {
		await context.close()
	}
})

// @e2e tiles::new-tile-placement-uses-standard-content-shape
test('a tile added through "Add widget" → "Custom widget" stores its data in content, not in the tile columns', async ({
	browser,
}) => {
	const { context, page } = await open(browser)
	try {
		const title = `Nieuw ${STAMP}`
		await page
			.locator(MORE_MENU)
			.locator('[data-testid="launchpad-more-menu-trigger"]')
			.click()
		await page
			.locator('[data-testid="launchpad-more-menu-item-add-widget"]')
			.click()
		await page.locator('[data-testid="widget-picker-type-tile"]').click()
		const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
		await expect(dialog).toBeVisible({ timeout: 10_000 })
		await dialog.getByLabel('Title', { exact: true }).fill(title)
		await dialog.getByLabel(/^(App route|URL)$/).fill('/apps/files')

		const created = page.waitForResponse(
			(res) =>
				res.request().method() === 'POST'
				&& /\/api\/dashboard\/\d+\/widgets(\?|$)/.test(res.url()),
		)
		await dialog.getByRole('button', { name: /^add$/i }).click()
		const response = await created
		expect(response.status(), await response.text()).toBe(201)
		const sent = response.request().postDataJSON() as {
			widgetId: string
			content: Record<string, unknown>
		}
		expect(sent.widgetId).toBe('tile')
		const id = Number((await response.json()).id)

		const stored = (await readPlacements(own.owner.api, own.id)).find(
			(p) => Number(p.id) === id,
		)!
		expect(stored.widgetId).toBe('tile')
		const content =
			typeof stored.content === 'string'
				? JSON.parse(stored.content)
				: stored.content
		expect(content).toMatchObject({ title, linkValue: '/apps/files' })
		for (const column of [
			'tileType',
			'tileTitle',
			'tileIcon',
			'tileIconType',
			'tileBackgroundColor',
			'tileTextColor',
			'tileLinkType',
			'tileLinkValue',
		]) {
			expect(stored[column] ?? null, column).toBeNull()
		}
	} finally {
		await context.close()
	}
})
