/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for how the quicklinks widget RENDERS
 * (openspec/specs/quicklinks-widget), written for e2e-coverage-programme.
 *
 * Each test seeds one placement through `POST /api/dashboard/{id}/widgets`
 * with exactly the content its scenario names, opens the dashboard the way a
 * person does, and measures the rendered DOM: the element that carries the
 * icon, its box, its computed border-radius and background, where the label
 * sits, and how the list lays out. Hover, click and keyboard behaviour live
 * in `quicklinks-widget-interaction.spec.ts`; the add/edit form in
 * `quicklinks-widget-form.spec.ts`.
 *
 * Gate-19 @e2e traceability:
 *   @e2e quicklinks-widget::custom-url-icon-renders-as-image
 *   @e2e quicklinks-widget::mdi-icon-name-renders-via-iconrenderer
 *   @e2e quicklinks-widget::empty-icon-renders-fallback
 *   @e2e quicklinks-widget::icon-size-small-renders-32px
 *   @e2e quicklinks-widget::icon-size-xlarge-with-rounded-shape
 *   @e2e quicklinks-widget::shape-has-no-effect-when-background-is-transparent
 *   @e2e quicklinks-widget::labels-below-icons
 *   @e2e quicklinks-widget::labels-overlay-on-hover
 *   @e2e quicklinks-widget::showlabels-false-suppresses-all-labels
 *   @e2e quicklinks-widget::auto-columns-with-wrap
 *   @e2e quicklinks-widget::fixed-columns-with-grid
 *   @e2e quicklinks-widget::invalid-column-value-falls-back-to-auto
 *   @e2e quicklinks-widget::empty-widget-shows-empty-state
 *
 * @spec openspec/specs/quicklinks-widget/spec.md
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, test } from '@playwright/test'
import { removeSeededDashboard, seedActiveDashboard } from './support/dashboardFixture.ts'
import {
	adminApi,
	clearPlacements,
	contentHeight,
	makeLinks,
	openWidget,
	seedQuicklinks,
} from './support/quicklinks.ts'

const LOGO = '/core/img/logo/logo.svg'

let api: APIRequestContext
let dashboard: SeededDashboard | null = null

test.setTimeout(90_000)

test.beforeAll(async () => {
	api = await adminApi()
	dashboard = await seedActiveDashboard(api, `E2E Quicklinks render ${Date.now()}`)
})

test.afterEach(async () => {
	await clearPlacements(api, dashboard?.id ?? 0)
})

test.afterAll(async () => {
	await removeSeededDashboard(api, dashboard)
	await api?.dispose()
})

/**
 * Seed a placement on this file's dashboard and open it.
 *
 * @param page the page.
 * @param content the content blob.
 * @param rect optional grid rectangle.
 * @return the rendered widget root.
 */
async function render(
	page: Page,
	content: Record<string, unknown>,
	rect: Partial<{ gridX: number, gridY: number, gridWidth: number, gridHeight: number }> = {},
): Promise<Locator> {
	const id = await seedQuicklinks(api, dashboard!.id, content, rect)
	return openWidget(page, id)
}

/**
 * The computed style values the size/shape scenarios assert on.
 *
 * @param icon an icon container.
 * @return its rendered box and paint.
 */
async function iconPaint(icon: Locator) {
	return icon.evaluate((el) => {
		const cs = getComputedStyle(el)
		const box = el.getBoundingClientRect()
		return {
			width: box.width,
			height: box.height,
			radius: cs.borderTopLeftRadius,
			backgroundColor: cs.backgroundColor,
			backgroundImage: cs.backgroundImage,
		}
	})
}

const TRANSPARENT = /^(transparent|rgba\(0, 0, 0, 0\))$/

test.describe('quicklinks widget: icon resolution (REQ-QLNK-003)', () => {
	// @e2e quicklinks-widget::custom-url-icon-renders-as-image
	test('a custom URL icon renders as a 48px <img> with the label below it', async ({ page }) => {
		const widget = await render(page, {
			links: [{ label: 'Docs', url: 'https://example.com', icon: LOGO }],
			iconSize: 'medium',
			labelPosition: 'below',
		})

		const icon = widget.locator('.cn-quicklinks-widget__icon').first()
		const img = icon.locator('img')
		await expect(img, 'a /-prefixed icon must render as an <img>').toHaveCount(1)
		await expect(img).toHaveAttribute('src', LOGO)

		const box = await iconPaint(icon)
		expect(box.width, 'medium icons are 48px wide').toBeCloseTo(48, 0)
		expect(box.height, 'medium icons are 48px tall').toBeCloseTo(48, 0)
		const imgBox = await img.boundingBox()
		expect(imgBox!.width).toBeLessThanOrEqual(48.5)
		expect(imgBox!.height).toBeLessThanOrEqual(48.5)

		const label = widget.locator('.cn-quicklinks-widget__label').first()
		await expect(label).toBeVisible()
		await expect(label).toHaveText('Docs')
		const iconBox = await icon.boundingBox()
		const labelBox = await label.boundingBox()
		expect(labelBox!.y, 'labelPosition below puts the label under the icon').toBeGreaterThanOrEqual(
			iconBox!.y + iconBox!.height - 1,
		)
	})

	// @e2e quicklinks-widget::mdi-icon-name-renders-via-iconrenderer
	test('a bare icon name renders that MDI icon at the configured size', async ({ page }) => {
		const widget = await render(page, {
			links: [{ label: 'Calendar', url: 'https://nc/calendar', icon: 'calendar' }],
			iconSize: 'large',
		})

		const icon = widget.locator('.cn-quicklinks-widget__icon').first()
		await expect(icon.locator('img'), 'a bare name is not an image URL').toHaveCount(0)
		const glyph = icon.locator('.material-design-icon').first()
		await expect(glyph).toBeAttached()
		// vue-material-design-icons name the glyph in its class (`calendar-icon`,
		// `calendar-outline-icon`, ...). The generic fallback is
		// `help-circle-outline-icon`, which must NOT be what renders here.
		await expect(glyph, 'the calendar glyph, not a fallback').toHaveClass(/(^|\s)calendar(-[a-z-]+)?-icon(\s|$)/)
		const svg = glyph.locator('svg').first()
		await expect(svg, 'rendered at iconSize large (64px)').toHaveAttribute('width', '64')
		await expect(svg).toHaveAttribute('height', '64')
	})

	// @e2e quicklinks-widget::empty-icon-renders-fallback
	test('an empty icon renders the MDI link fallback icon', async ({ page }) => {
		const widget = await render(page, {
			links: [{ label: 'Quick link', url: 'https://example.com', icon: '' }],
		})

		const icon = widget.locator('.cn-quicklinks-widget__icon').first()
		await expect(icon.locator('img')).toHaveCount(0)
		const glyph = icon.locator('.material-design-icon').first()
		await expect(glyph, 'an empty icon must still draw something').toBeAttached()
		// MDI `link` / `link-variant`, never the "?" help-circle.
		await expect(glyph, 'the fallback is the MDI link icon').toHaveClass(/(^|\s)link(-variant)?-icon(\s|$)/)
	})
})

test.describe('quicklinks widget: icon sizes and shapes (REQ-QLNK-004)', () => {
	// @e2e quicklinks-widget::icon-size-small-renders-32px
	test('iconSize small with a circle shape renders 32x32 containers at 50% radius', async ({ page }) => {
		const widget = await render(page, {
			links: makeLinks(3),
			iconSize: 'small',
			iconShape: 'circle',
		})

		const icons = widget.locator('.cn-quicklinks-widget__icon')
		await expect(icons).toHaveCount(3)
		for (let i = 0; i < 3; i++) {
			const paint = await iconPaint(icons.nth(i))
			expect(paint.width, `icon ${i + 1} width`).toBeCloseTo(32, 0)
			expect(paint.height, `icon ${i + 1} height`).toBeCloseTo(32, 0)
			expect(paint.radius, `icon ${i + 1} radius`).toBe('50%')
		}
	})

	// @e2e quicklinks-widget::icon-size-xlarge-with-rounded-shape
	test('iconSize xlarge with a rounded shape renders 96x96 containers at 8px radius', async ({ page }) => {
		const widget = await render(page, {
			links: makeLinks(2),
			iconSize: 'xlarge',
			iconShape: 'rounded',
		}, { gridWidth: 8, gridHeight: 6 })

		const icons = widget.locator('.cn-quicklinks-widget__icon')
		await expect(icons).toHaveCount(2)
		for (let i = 0; i < 2; i++) {
			const paint = await iconPaint(icons.nth(i))
			expect(paint.width, `icon ${i + 1} width`).toBeCloseTo(96, 0)
			expect(paint.height, `icon ${i + 1} height`).toBeCloseTo(96, 0)
			expect(paint.radius, `icon ${i + 1} radius`).toBe('8px')
		}
	})

	// @e2e quicklinks-widget::shape-has-no-effect-when-background-is-transparent
	test('a circle shape on a transparent background clips at 50% but paints no tile', async ({ page }) => {
		const widget = await render(page, {
			links: [{ label: 'Docs', url: 'https://example.com', icon: 'Star', color: '#ff0000' }],
			tileBackgroundStyle: 'transparent',
			iconShape: 'circle',
		})

		const icon = widget.locator('.cn-quicklinks-widget__icon').first()
		const paint = await iconPaint(icon)
		expect(paint.radius, 'the shape still applies to the clip').toBe('50%')
		expect(paint.backgroundColor, 'no fill behind the icon, even with a link colour').toMatch(TRANSPARENT)
		expect(paint.backgroundImage, 'no gradient behind the icon').toBe('none')

		// Nothing else behind the glyph either: the link tile is unpainted.
		const tile = await widget.locator('.cn-quicklinks-widget__link').first().evaluate((el) => {
			const cs = getComputedStyle(el)
			return { color: cs.backgroundColor, image: cs.backgroundImage }
		})
		expect(tile.color).toMatch(TRANSPARENT)
		expect(tile.image).toBe('none')
		await expect(icon.locator('.material-design-icon')).toBeVisible()
	})
})

test.describe('quicklinks widget: label position (REQ-QLNK-005)', () => {
	// @e2e quicklinks-widget::labels-below-icons
	test('labels below icons are always visible under each icon', async ({ page }) => {
		const widget = await render(page, {
			links: [
				{ label: 'Docs', url: 'https://example.com/docs', icon: 'FileDocument' },
				{ label: 'Calendar', url: 'https://example.com/cal', icon: 'Calendar' },
				{ label: 'Team', url: 'https://example.com/team', icon: 'AccountGroup' },
			],
			showLabels: true,
			labelPosition: 'below',
			iconSize: 'medium',
		})

		const links = widget.locator('.cn-quicklinks-widget__link')
		await expect(links).toHaveCount(3)
		const names = ['Docs', 'Calendar', 'Team']
		for (let i = 0; i < 3; i++) {
			const link = links.nth(i)
			const label = link.locator('.cn-quicklinks-widget__label')
			// Visible without any hover: the pointer is parked off the widget.
			await page.mouse.move(0, 0)
			await expect(label, `${names[i]} label shows at rest`).toBeVisible()
			await expect(label).toHaveText(names[i])

			const iconBox = (await link.locator('.cn-quicklinks-widget__icon').boundingBox())!
			const labelBox = (await label.boundingBox())!
			expect(labelBox.y, `${names[i]} label is below its icon`).toBeGreaterThanOrEqual(iconBox.y + iconBox.height - 1)
			expect(
				Math.abs((labelBox.x + labelBox.width / 2) - (iconBox.x + iconBox.width / 2)),
				`${names[i]} label sits directly under (centred on) its icon`,
			).toBeLessThanOrEqual(2)

			// iconSize (48px) + a label area of roughly 20px.
			const used = await contentHeight(link)
			expect(used - 48, `${names[i]} label area`).toBeGreaterThanOrEqual(12)
			expect(used - 48, `${names[i]} label area`).toBeLessThanOrEqual(28)
		}
	})

	// @e2e quicklinks-widget::labels-overlay-on-hover
	test('overlay labels are hidden at rest, centred over the icon on hover, gone after', async ({ page }) => {
		const widget = await render(page, {
			links: makeLinks(3),
			showLabels: true,
			labelPosition: 'overlay',
			iconSize: 'medium',
		})

		const link = widget.locator('.cn-quicklinks-widget__link').nth(1)
		const label = link.locator('.cn-quicklinks-widget__label')
		const icon = link.locator('.cn-quicklinks-widget__icon')

		await page.mouse.move(0, 0)
		await expect(widget.locator('.cn-quicklinks-widget__icon').first()).toBeVisible()
		for (const l of await widget.locator('.cn-quicklinks-widget__label').all()) {
			await expect(l, 'no label shows without hover').toBeHidden()
		}
		const used = await contentHeight(link)
		expect(used, 'only the 48px icon takes vertical space').toBeGreaterThanOrEqual(47)
		expect(used).toBeLessThanOrEqual(49)

		await link.hover()
		await expect(label, 'hovering reveals the label').toBeVisible()
		await expect(label).toHaveText('Link 2')
		const iconBox = (await icon.boundingBox())!
		const labelBox = (await label.boundingBox())!
		expect(
			Math.abs((labelBox.x + labelBox.width / 2) - (iconBox.x + iconBox.width / 2)),
			'the overlay is centred on the icon',
		).toBeLessThanOrEqual(2)
		const overlaps = labelBox.y < iconBox.y + iconBox.height && labelBox.y + labelBox.height > iconBox.y
		expect(overlaps, 'the overlay sits over the icon, not under it').toBe(true)
		// Only the hovered link reveals its label.
		await expect(widget.locator('.cn-quicklinks-widget__link').nth(0).locator('.cn-quicklinks-widget__label')).toBeHidden()

		await page.mouse.move(0, 0)
		await expect(label, 'the label disappears when hover ends').toBeHidden()
	})

	// @e2e quicklinks-widget::showlabels-false-suppresses-all-labels
	test('showLabels false renders no label text, not even on hover', async ({ page }) => {
		const labels = makeLinks(3, 'Hidden')
		const widget = await render(page, {
			links: labels,
			showLabels: false,
			labelPosition: 'overlay',
			iconSize: 'medium',
		})

		const links = widget.locator('.cn-quicklinks-widget__link')
		await expect(links).toHaveCount(3)
		await expect(widget.locator('.cn-quicklinks-widget__label'), 'no label element at all').toHaveCount(0)

		for (let i = 0; i < 3; i++) {
			await links.nth(i).hover()
			await expect(links.nth(i), 'hover reveals no label text').not.toContainText(labels[i].label)
			await expect(widget.locator('.cn-quicklinks-widget__label')).toHaveCount(0)
		}

		const used = await contentHeight(links.first())
		expect(used, 'the footprint is just the icon').toBeGreaterThanOrEqual(47)
		expect(used).toBeLessThanOrEqual(49)
	})
})

test.describe('quicklinks widget: column layout (REQ-QLNK-006)', () => {
	// @e2e quicklinks-widget::auto-columns-with-wrap
	test('columns auto flows left to right and wraps only when the next link does not fit', async ({ page }) => {
		// Three of twelve grid columns: about 300px wide at the 1280px viewport.
		const widget = await render(page, {
			links: makeLinks(5),
			columns: 'auto',
			iconSize: 'medium',
		}, { gridWidth: 3, gridHeight: 4 })

		const list = widget.locator('.cn-quicklinks-widget__list')
		const layout = await list.evaluate((el) => {
			const cs = getComputedStyle(el)
			const inner = el.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight)
			const items = Array.from(el.querySelectorAll(':scope > .cn-quicklinks-widget__item')).map((li) => {
				const r = li.getBoundingClientRect()
				return { x: r.x, y: r.y, w: r.width }
			})
			return {
				display: cs.display,
				wrap: cs.flexWrap,
				gap: parseFloat(cs.columnGap) || 0,
				inner,
				left: el.getBoundingClientRect().x + parseFloat(cs.paddingLeft),
				items,
			}
		})
		expect(layout.display, 'auto uses flexbox').toBe('flex')
		expect(layout.wrap, 'auto wraps').toBe('wrap')
		expect(layout.items).toHaveLength(5)

		// Group into rows by top edge, in source order.
		const rows: { x: number, y: number, w: number }[][] = []
		for (const item of layout.items) {
			const row = rows[rows.length - 1]
			if (row && Math.abs(row[0].y - item.y) < 1) {
				expect(item.x, 'within a row, links run left to right').toBeGreaterThan(row[row.length - 1].x)
				row.push(item)
			} else {
				if (row) {
					expect(item.y, 'a new row starts below the previous one').toBeGreaterThan(row[0].y)
				}
				rows.push([item])
			}
		}
		for (let r = 0; r < rows.length; r++) {
			const row = rows[r]
			expect(row[0].x - layout.left, `row ${r + 1} starts at the left edge`).toBeLessThanOrEqual(1)
			const used = row.reduce((sum, it) => sum + it.w, 0) + layout.gap * (row.length - 1)
			expect(used, `row ${r + 1} fits the widget`).toBeLessThanOrEqual(layout.inner + 1)
			if (r < rows.length - 1) {
				// It wrapped because the next link would not have fitted.
				expect(used + layout.gap + rows[r + 1][0].w, `row ${r + 1} wrapped only when out of room`)
					.toBeGreaterThan(layout.inner - 1)
			}
		}
	})

	// @e2e quicklinks-widget::fixed-columns-with-grid
	test('columns 3 lays nine links out as a 3x3 grid of equal thirds', async ({ page }) => {
		const widget = await render(page, {
			links: makeLinks(9),
			columns: 3,
			iconSize: 'large',
		}, { gridWidth: 8, gridHeight: 8 })

		const list = widget.locator('.cn-quicklinks-widget__list')
		const layout = await list.evaluate((el) => {
			const cs = getComputedStyle(el)
			return {
				display: cs.display,
				tracks: cs.gridTemplateColumns.split(' ').map((v) => parseFloat(v)),
				gap: parseFloat(cs.columnGap) || 0,
				inner: el.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight),
				items: Array.from(el.querySelectorAll(':scope > .cn-quicklinks-widget__item')).map((li) => {
					const r = li.getBoundingClientRect()
					return { x: r.x, y: r.y, w: r.width }
				}),
			}
		})
		expect(layout.display, 'a number uses CSS grid').toBe('grid')
		expect(layout.tracks, 'exactly three column tracks').toHaveLength(3)
		const third = (layout.inner - 2 * layout.gap) / 3
		for (const track of layout.tracks) {
			expect(Math.abs(track - third), 'each column is one third of the widget').toBeLessThanOrEqual(1)
		}
		expect(layout.items).toHaveLength(9)

		for (let row = 0; row < 3; row++) {
			const cells = layout.items.slice(row * 3, row * 3 + 3)
			for (const cell of cells) {
				expect(Math.abs(cell.y - cells[0].y), `links ${row * 3 + 1}-${row * 3 + 3} share row ${row + 1}`).toBeLessThan(1)
				expect(Math.abs(cell.w - third), 'each link fills its third').toBeLessThanOrEqual(1)
			}
			expect(cells[1].x).toBeGreaterThan(cells[0].x)
			expect(cells[2].x).toBeGreaterThan(cells[1].x)
			if (row > 0) {
				expect(cells[0].y, `row ${row + 1} is below row ${row}`).toBeGreaterThan(layout.items[(row - 1) * 3].y)
				expect(Math.abs(cells[0].x - layout.items[0].x), 'every row starts in column 1').toBeLessThan(1)
			}
		}
	})

	// @e2e quicklinks-widget::invalid-column-value-falls-back-to-auto
	test('columns 0, "invalid" and 15 all fall back to the auto flex-wrap layout', async ({ page }) => {
		const ids: number[] = []
		let y = 0
		for (const columns of [0, 'invalid', 15]) {
			ids.push(await seedQuicklinks(api, dashboard!.id, { links: makeLinks(4), columns }, { gridY: y, gridWidth: 6, gridHeight: 3 }))
			y += 3
		}
		await openWidget(page, ids[0])

		for (const [i, columns] of ['0', '"invalid"', '15'].entries()) {
			const list = page.locator(`[data-testid="widget-placement-${ids[i]}"] .cn-quicklinks-widget__list`)
			await expect(list).toBeAttached({ timeout: 15_000 })
			const style = await list.evaluate((el) => {
				const cs = getComputedStyle(el)
				return { display: cs.display, wrap: cs.flexWrap, template: cs.gridTemplateColumns }
			})
			expect(style.display, `columns ${columns} renders flexbox`).toBe('flex')
			expect(style.wrap, `columns ${columns} wraps`).toBe('wrap')
			expect(style.template, `columns ${columns} sets no grid tracks`).toBe('none')
		}
	})
})

test.describe('quicklinks widget: empty state (REQ-QLNK-011)', () => {
	// @e2e quicklinks-widget::empty-widget-shows-empty-state
	test('links [] shows the empty-state message and a gear affordance', async ({ page }) => {
		const widget = await render(page, { links: [] })

		await expect(widget.locator('.cn-quicklinks-widget__list'), 'no link list for an empty widget').toHaveCount(0)
		await expect(widget.getByText('No quicklinks yet — click the gear icon to add some.', { exact: true })).toBeVisible()
		const gear = widget.locator('.cn-quicklinks-widget__empty').getByRole('button')
		await expect(gear, 'a gear button is the affordance').toBeVisible()
		await expect(gear.locator('.material-design-icon')).toHaveClass(/(^|\s)cog(-outline)?-icon(\s|$)/)
	})
})
