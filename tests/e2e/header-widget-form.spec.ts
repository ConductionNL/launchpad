/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for adding and configuring a header widget the
 * way a person does (openspec/specs/header-widget, REQ-HDR-001/002 and the
 * default grid size of REQ-HDR-009), written for e2e-coverage-programme
 * task 4.4. Rendering of seeded placements: `header-widget.spec.ts`,
 * `header-widget-content.spec.ts` and `header-widget-a11y.spec.ts`.
 *
 * The flow is the real one: the start page's More menu, "Add custom
 * widget…", the Add Widget modal (nc-vue `CnAddWidgetModal`), the type
 * `<select>` and the `CnHeaderWidgetForm` sub-form. What the form wrote is
 * read back through `GET /api/dashboard/{id}`, so every assertion about the
 * stored configuration is about what persisted, not what the modal emitted.
 *
 * Each test gets a fresh dashboard, active for the admin, from
 * `headerSuite()` in `support/headerWidget.ts`, deleted afterwards.
 *
 * Where the spec types a field `string|null` (subtitle, backgroundImageUrl,
 * backgroundColor, textColor) and the field was left empty, the form writes
 * `''`. Both mean "unset" to the renderer, so these tests accept either and
 * reject anything else; the difference is reported, not asserted away.
 *
 * Gate-19 @e2e traceability:
 *   @e2e header-widget::widget-appears-in-picker
 *   @e2e header-widget::multiple-instances-allowed
 *   @e2e header-widget::widget-registration-survives-app-reload
 *   @e2e header-widget::config-for-title-and-subtitle
 *   @e2e header-widget::config-for-background-image-url
 *   @e2e header-widget::config-for-background-color
 *   @e2e header-widget::config-for-overlay-mode
 *   @e2e header-widget::config-for-overlay-opacity
 *   @e2e header-widget::config-for-text-color
 *   @e2e header-widget::config-for-text-and-vertical-alignment
 *   @e2e header-widget::config-for-height-preset
 *   @e2e header-widget::config-for-cta-button
 *   @e2e header-widget::config-is-json-serializable
 *   @e2e header-widget::default-grid-sizing-for-header-widget
 *   @e2e header-widget::a-preset-sets-the-starting-height
 *
 * @spec openspec/specs/header-widget/spec.md
 */

import type { Locator, Page } from '@playwright/test'
import type { PlacementRow } from './support/headerWidget.ts'

import { expect, test } from '@playwright/test'
import { MORE_MENU } from './support/dashboardSwitcher.ts'
import {
	banner,
	contrastRatio,
	cssOf,
	headerPlacements,
	headerSuite,
	LOGO_URL,
	openWorkspace,
	readDashboard,
} from './support/headerWidget.ts'

test.setTimeout(120_000)

const suite = headerSuite()

/** Content as the API returns it for a header placement. */
type HeaderContent = Record<string, unknown> & {
	cta?: { label?: string; url?: string; style?: string } | null
}

/** "Unset" as the form writes it: the spec's null, or the empty string. */
const UNSET = [null, '']

/**
 * Open the Add Widget modal from the More menu on the active dashboard.
 *
 * @param page the page, on the workspace.
 * @return the dialog.
 */
async function openModal(page: Page): Promise<Locator> {
	await page.locator(`${MORE_MENU} [data-testid="launchpad-more-menu-trigger"]`).click()
	await page.locator('[data-testid="launchpad-more-menu-item-add-widget"]').click()
	await page.locator('[data-testid="widget-picker-type-header"]').dblclick()
	const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
	await expect(dialog).toBeVisible({ timeout: 10_000 })
	return dialog
}

/**
 * Open the modal with the header type chosen and its form showing.
 *
 * @param page the page, on the workspace.
 * @return the dialog.
 */
async function openHeaderForm(page: Page): Promise<Locator> {
	const dialog = await openModal(page)
	await expect(titleField(dialog)).toBeVisible({ timeout: 10_000 })
	return dialog
}

/** The form's Title field (not the modal's "Custom title" chrome field). */
function titleField(dialog: Locator): Locator {
	return dialog.getByLabel('Title', { exact: true })
}

/** The modal's Add button. */
function addButton(dialog: Locator): Locator {
	return dialog.locator('[data-testid="add-widget-save"]')
}

/**
 * Pick an option of one of the form's NcSelects by position. NcSelect splits
 * option labels across spans, so matching by text is unreliable; the order
 * is fixed by the form's option lists.
 *
 * @param page the page.
 * @param dialog the dialog.
 * @param label the select's label.
 * @param index the option's position.
 * @return nothing.
 */
async function choose(page: Page, dialog: Locator, label: string, index: number): Promise<void> {
	const select = dialog
		.locator('.v-select')
		.filter({ has: page.getByRole('combobox', { name: label, exact: true }) })
	await expect(select, `the "${label}" select`).toHaveCount(1)
	await select.locator('.vs__dropdown-toggle').click()
	const option = page.locator('.vs__dropdown-menu .vs__dropdown-option').nth(index)
	await expect(option).toBeVisible({ timeout: 5_000 })
	await option.click()
}

/**
 * Set one of the form's colour pickers by typing a hex value.
 *
 * @param page the page.
 * @param dialog the dialog.
 * @param label the picker's label.
 * @param hex `#rrggbb`.
 * @return nothing.
 */
async function pickColour(page: Page, dialog: Locator, label: string, hex: string): Promise<void> {
	const row = dialog.locator('.cn-header-widget-form__color-label').filter({ hasText: label })
	await expect(row, `the "${label}" picker`).toHaveCount(1)
	const swatch = row.locator('.cn-color-picker__swatch')
	await swatch.click()
	const input = page.locator('.cn-color-picker__popper:visible .vc-chrome-fields:visible input').first()
	await expect(input).toBeVisible({ timeout: 5_000 })
	await input.fill(hex)
	await swatch.click()
}

/**
 * Press Add, wait for the placement to be created and the modal to close,
 * and read the new placement back from the server.
 *
 * @param page the page.
 * @param dialog the dialog.
 * @return the stored placement.
 */
async function submit(page: Page, dialog: Locator): Promise<PlacementRow> {
	const dashboardId = suite.dashboard().id
	const before = (await headerPlacements(suite.api(), dashboardId)).map((p) => p.id)
	await expect(addButton(dialog)).toBeEnabled({ timeout: 5_000 })
	const created = page.waitForResponse((r) =>
		r.request().method() === 'POST' && r.url().includes(`/api/dashboard/${dashboardId}/widgets`))
	await addButton(dialog).click()
	expect((await created).status()).toBe(201)
	await expect(dialog).not.toBeVisible({ timeout: 10_000 })

	const after = await headerPlacements(suite.api(), dashboardId)
	const fresh = after.filter((p) => !before.includes(p.id))
	expect(fresh, 'exactly one new header placement').toHaveLength(1)
	return fresh[0]
}

/**
 * Add a header through the form.
 *
 * @param page the page, on the workspace.
 * @param fill fills the form.
 * @return the stored placement.
 */
async function addHeader(page: Page, fill: (dialog: Locator) => Promise<void>): Promise<PlacementRow> {
	const dialog = await openHeaderForm(page)
	await fill(dialog)
	return submit(page, dialog)
}

/** The stored content of a placement, as an object. */
function contentOf(p: PlacementRow): HeaderContent {
	expect(typeof p.content, 'content is a JSON object, not a string').toBe('object')
	expect(p.content).not.toBeNull()
	return p.content as HeaderContent
}

test.describe('REQ-HDR-001 widget registration', () => {
	// @e2e header-widget::widget-appears-in-picker
	test('the picker lists the header type by its title and it can be placed', async ({ page }) => {
		await openWorkspace(page)
		const dialog = await openModal(page)

		const option = dialog.locator('[data-testid="widget-type-option-header"]')
		await expect(option).toHaveCount(1)
		await expect(option).toHaveText('Header Banner')
		await expect(option).toHaveAttribute('value', 'header')

		await dialog.locator('[data-testid="widget-type-select"]').selectOption('header')
		await titleField(dialog).fill('Picked from the picker')
		const placed = await submit(page, dialog)
		expect(placed.widgetId).toBe('header')
		await expect(banner(page, placed.id).getByRole('heading', { level: 2, name: 'Picked from the picker' }))
			.toBeVisible({ timeout: 15_000 })
	})

	// @e2e header-widget::multiple-instances-allowed
	test('two headers on one dashboard keep their own configuration', async ({ page }) => {
		await openWorkspace(page)
		const first = await addHeader(page, async (d) => {
			await titleField(d).fill('First banner')
			await d.getByLabel('Subtitle (optional)').fill('one')
		})
		const second = await addHeader(page, async (d) => {
			await titleField(d).fill('Second banner')
			await d.getByLabel('Subtitle (optional)').fill('two')
			await choose(page, d, 'Text alignment', 2)
		})

		expect(second.id, 'separate instances').not.toBe(first.id)
		const stored = await headerPlacements(suite.api(), suite.dashboard().id)
		expect(stored.map((p) => p.id)).toEqual([first.id, second.id])
		const [a, b] = stored.map(contentOf)
		expect([a.title, a.subtitle, a.textAlign]).toEqual(['First banner', 'one', 'center'])
		expect([b.title, b.subtitle, b.textAlign]).toEqual(['Second banner', 'two', 'right'])

		await expect(banner(page, first.id).locator('h2')).toHaveText('First banner')
		await expect(banner(page, second.id).locator('h2')).toHaveText('Second banner')
	})

	// @e2e header-widget::widget-registration-survives-app-reload
	test('the header type is still in the picker after the app reloads', async ({ page }) => {
		await openWorkspace(page)
		let dialog = await openModal(page)
		await expect(dialog.locator('[data-testid="widget-type-option-header"]')).toHaveCount(1)
		await dialog.getByRole('button', { name: 'Cancel' }).click()
		await expect(dialog).not.toBeVisible()

		await page.reload()
		await expect(page.locator(MORE_MENU)).toBeVisible({ timeout: 30_000 })
		dialog = await openModal(page)
		await expect(dialog.locator('[data-testid="widget-type-option-header"]')).toHaveText('Header Banner')
		await dialog.locator('[data-testid="widget-type-select"]').selectOption('header')
		await expect(titleField(dialog), 'and it still opens its form').toBeVisible()
	})
})

test.describe('REQ-HDR-002 placement configuration', () => {
	// @e2e header-widget::config-for-title-and-subtitle
	test('title and subtitle are stored as strings; an untouched subtitle is unset', async ({ page }) => {
		await openWorkspace(page)
		const both = contentOf(await addHeader(page, async (d) => {
			await titleField(d).fill('Welcome')
			await d.getByLabel('Subtitle (optional)').fill('Explore our dashboard')
		}))
		expect(both.title).toBe('Welcome')
		expect(both.subtitle).toBe('Explore our dashboard')

		const titleOnly = contentOf(await addHeader(page, async (d) => {
			await titleField(d).fill('Only a title')
		}))
		expect(titleOnly.title).toBe('Only a title')
		expect(UNSET, `default subtitle, got ${JSON.stringify(titleOnly.subtitle)}`).toContain(titleOnly.subtitle ?? null)

		// Title is required: the form refuses to save without one.
		const dialog = await openHeaderForm(page)
		await expect(addButton(dialog)).toBeDisabled()
	})

	// @e2e header-widget::config-for-background-image-url
	test('an http(s) image URL is stored and anything else is refused', async ({ page }) => {
		await openWorkspace(page)
		const stored = contentOf(await addHeader(page, async (d) => {
			await titleField(d).fill('With an image')
			await d.getByLabel('Background image URL').fill(LOGO_URL)
		}))
		expect(stored.backgroundImageUrl).toBe(LOGO_URL)

		const dialog = await openHeaderForm(page)
		await titleField(dialog).fill('Bad scheme')
		await dialog.getByLabel('Background image URL').fill('ftp://example.com/banner.jpg')
		await expect(addButton(dialog), 'a non-http(s) URL cannot be saved').toBeDisabled()
		await expect(addButton(dialog)).toHaveAttribute('title', /URL/)
		await dialog.getByLabel('Background image URL').fill('https://example.com/banner.jpg')
		await expect(addButton(dialog), 'https is accepted').toBeEnabled()
		// The "allow-list validation (see REQ-HDR-005)" clause points at a
		// requirement whose scenarios are excluded as never built: there is no
		// allow-list setting or check to exercise.
	})

	// @e2e header-widget::config-for-background-color
	test('a picked background colour is stored as a valid CSS colour', async ({ page }) => {
		await openWorkspace(page)
		const stored = contentOf(await addHeader(page, async (d) => {
			await titleField(d).fill('Coloured')
			await pickColour(page, d, 'Background color', '#336699')
		}))
		expect(String(stored.backgroundColor).toLowerCase()).toBe('#336699')
		const valid = await page.evaluate((c) => CSS.supports('color', c), String(stored.backgroundColor))
		expect(valid, 'a valid CSS colour string').toBe(true)
	})

	// @e2e header-widget::config-for-overlay-mode
	test('the chosen overlay mode is stored, and the default follows the image', async ({ page }) => {
		await openWorkspace(page)
		const chosen = contentOf(await addHeader(page, async (d) => {
			await titleField(d).fill('Gradient chosen')
			await choose(page, d, 'Overlay mode', 2)
		}))
		expect(chosen.overlayMode).toBe('gradient-bottom')

		// An untouched mode is stored as no value, so the default keeps
		// following the image; the banner is where the default shows.
		const withImageRow = await addHeader(page, async (d) => {
			await titleField(d).fill('Image, mode untouched')
			await d.getByLabel('Background image URL').fill(LOGO_URL)
		})
		expect([undefined, 'tint'], 'stored default with an image').toContain(contentOf(withImageRow).overlayMode)
		const tinted = banner(page, withImageRow.id)
		await expect(tinted).toBeVisible({ timeout: 15_000 })
		const overlay = tinted.locator('.cn-header-widget__overlay')
		await expect(overlay, 'default with an image: a tint overlay').toHaveCount(1)
		expect(await cssOf(overlay, 'background-image'), 'a tint, not a gradient').toBe('none')
		expect(await cssOf(overlay, 'opacity')).toBe('0.4')

		const withoutRow = await addHeader(page, async (d) => {
			await titleField(d).fill('No image, mode untouched')
		})
		expect([undefined, 'none'], 'stored default without an image').toContain(contentOf(withoutRow).overlayMode)
		const plain = banner(page, withoutRow.id)
		await expect(plain).toBeVisible({ timeout: 15_000 })
		await expect(plain.locator('.cn-header-widget__overlay'), 'default without an image: no overlay').toHaveCount(0)
	})

	// @e2e header-widget::config-for-overlay-opacity
	test('the opacity slider is bounded to [0, 1], defaults to 0.4 and stores its value', async ({ page }) => {
		await openWorkspace(page)
		const untouched = contentOf(await addHeader(page, async (d) => {
			await titleField(d).fill('Default opacity')
			await choose(page, d, 'Overlay mode', 1)
		}))
		expect(untouched.overlayOpacity).toBe(0.4)

		const set = contentOf(await addHeader(page, async (d) => {
			await titleField(d).fill('Custom opacity')
			await choose(page, d, 'Overlay mode', 1)
			const slider = d.locator('input[type="range"].cn-header-widget-form__range')
			await expect(slider).toHaveAttribute('min', '0')
			await expect(slider).toHaveAttribute('max', '1')
			await slider.fill('0.75')
		}))
		expect(typeof set.overlayOpacity).toBe('number')
		expect(set.overlayOpacity).toBe(0.75)
	})

	// @e2e header-widget::config-for-text-color
	test('a picked text colour is stored; unset, the banner derives a readable one', async ({ page }) => {
		await openWorkspace(page)
		const picked = contentOf(await addHeader(page, async (d) => {
			await titleField(d).fill('Red text')
			await pickColour(page, d, 'Text color', '#aa0000')
		}))
		expect(String(picked.textColor).toLowerCase()).toBe('#aa0000')
		expect(await page.evaluate((c) => CSS.supports('color', c), String(picked.textColor))).toBe(true)

		const autoRow = await addHeader(page, async (d) => {
			await titleField(d).fill('Auto text')
			await pickColour(page, d, 'Background color', '#f4f4f4')
		})
		const auto = contentOf(autoRow)
		expect(UNSET, `default textColor, got ${JSON.stringify(auto.textColor)}`).toContain(auto.textColor ?? null)
		const b = banner(page, autoRow.id)
		await expect(b).toBeVisible({ timeout: 15_000 })
		const ratio = contrastRatio(await cssOf(b.locator('h2'), 'color'), await cssOf(b, 'background-color'))
		expect(ratio, 'auto-derived text is contrast-safe on the background').toBeGreaterThanOrEqual(4.5)
	})

	// @e2e header-widget::config-for-text-and-vertical-alignment
	test('alignment defaults to center/middle and stores the chosen values', async ({ page }) => {
		await openWorkspace(page)
		const untouched = contentOf(await addHeader(page, async (d) => {
			await titleField(d).fill('Default alignment')
		}))
		expect([untouched.textAlign, untouched.verticalAlign]).toEqual(['center', 'middle'])

		const chosen = contentOf(await addHeader(page, async (d) => {
			await titleField(d).fill('Chosen alignment')
			await choose(page, d, 'Text alignment', 2)
			await choose(page, d, 'Vertical alignment', 2)
		}))
		expect([chosen.textAlign, chosen.verticalAlign]).toEqual(['right', 'bottom'])
	})

	// @e2e header-widget::config-for-height-preset
	test('height defaults to medium and a chosen preset is stored and drawn at its size', async ({ page }) => {
		await openWorkspace(page)
		const untouched = contentOf(await addHeader(page, async (d) => {
			await titleField(d).fill('Default height')
		}))
		expect(untouched.height).toBe('medium')

		// The form must offer the four presets, in this order.
		const row = await addHeader(page, async (d) => {
			await titleField(d).fill('Small height')
			await choose(page, d, 'Height', 0)
		})
		expect(contentOf(row).height).toBe('small')
		const b = banner(page, row.id)
		await expect(b).toBeVisible({ timeout: 15_000 })
		expect(Math.abs((await b.boundingBox())!.height - 120), 'small maps to 120px').toBeLessThanOrEqual(1)
	})

	// @e2e header-widget::config-for-cta-button
	test('the CTA is null by default and stored whole when given; half a CTA is refused', async ({ page }) => {
		await openWorkspace(page)
		const none = contentOf(await addHeader(page, async (d) => {
			await titleField(d).fill('No CTA')
		}))
		expect(none.cta).toBeNull()

		const full = contentOf(await addHeader(page, async (d) => {
			await titleField(d).fill('With CTA')
			await d.getByLabel('Button text').fill('Sign up')
			await d.getByLabel('Target URL').fill('https://example.com/signup')
			await choose(page, d, 'Button style', 2)
		}))
		expect(full.cta).toEqual({ label: 'Sign up', url: 'https://example.com/signup', style: 'ghost' })

		const dialog = await openHeaderForm(page)
		await titleField(dialog).fill('Half a CTA')
		await dialog.getByLabel('Button text').fill('Label only')
		await expect(addButton(dialog), 'a CTA without a URL cannot be saved').toBeDisabled()
		await dialog.getByLabel('Button text').fill('')
		await dialog.getByLabel('Target URL').fill('https://example.com')
		await expect(addButton(dialog), 'nor one without a label').toBeDisabled()
	})

	// @e2e header-widget::config-is-json-serializable
	test('the stored config deserializes to an object with the text exactly as typed', async ({ page }) => {
		await openWorkspace(page)
		const title = 'Welkom bij Café “Zon” — 日本語 ✓ & <ok>'
		const subtitle = 'Tab\there, quote " and backslash \\'
		const row = await addHeader(page, async (d) => {
			await titleField(d).fill(title)
			await d.getByLabel('Subtitle (optional)').fill(subtitle)
		})

		const { raw, placements } = await readDashboard(suite.api(), suite.dashboard().id)
		expect(() => JSON.parse(raw), 'the response is valid JSON').not.toThrow()
		const stored = placements.find((p) => p.id === row.id)!
		const content = contentOf(stored)
		expect(Array.isArray(content)).toBe(false)
		expect(JSON.parse(JSON.stringify(content)), 'it round-trips unchanged').toEqual(content)
		expect(content.title, 'no spurious whitespace or encoding damage').toBe(title)
		expect(content.subtitle).toBe(subtitle)
		// overlayMode is absent until chosen (see the overlay mode test).
		for (const key of ['title', 'overlayOpacity', 'textAlign', 'verticalAlign', 'height']) {
			expect(content, `field ${key} is present`).toHaveProperty(key)
		}
	})
})

test.describe('REQ-HDR-009 default grid sizing', () => {
	// @e2e header-widget::default-grid-sizing-for-header-widget
	test('a header added through the modal spans the full grid and is sized for its preset', async ({ page }) => {
		await openWorkspace(page)
		const row = await addHeader(page, async (d) => {
			await titleField(d).fill('Full width')
		})
		const { gridColumns } = await readDashboard(suite.api(), suite.dashboard().id)

		expect(row.gridWidth, `gridWidth defaults to gridColumns (${gridColumns})`).toBe(gridColumns)
		// The workspace grid's row is 60px (Views.vue `:cellHeight="60"`); the
		// medium preset is 200px.
		expect(row.gridHeight, 'gridHeight follows the preset and the row size').toBe(Math.ceil(200 / 60))
	})

	// @e2e header-widget::a-preset-sets-the-starting-height
	test('a header added with the small preset starts two rows tall', async ({ page }) => {
		await openWorkspace(page)
		const row = await addHeader(page, async (d) => {
			await titleField(d).fill('Small start')
			await choose(page, d, 'Height', 0)
		})
		expect(contentOf(row).height).toBe('small')
		// The workspace grid's row is 60px; the small preset is 120px.
		expect(row.gridHeight).toBe(Math.ceil(120 / 60))
	})
})
