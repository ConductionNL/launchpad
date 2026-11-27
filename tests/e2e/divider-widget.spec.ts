/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright e2e for the `divider` widget, written for e2e-coverage-programme
 * task 4.13: one verdict per scenario of openspec/specs/divider-widget/spec.md.
 *
 * WHAT IS UNDER TEST
 * ==================
 * The renderer is nc-vue's `CnDividerWidget`
 * (node_modules/@conduction/nextcloud-vue/src/components/CnDividerWidget), the
 * form is `CnDividerWidgetForm`, both registered as widget type `divider` and
 * re-exported by `src/constants/widgetRegistry.js`. A placement's `content`
 * blob is the spec's "widgetContent JSON": `{style, lineColor, lineThickness,
 * lineStyle, whitespaceSize, headingText}`.
 *
 * HOW
 * ===
 * One dashboard is seeded over the API with a divider placement per style
 * configuration the scenarios name; the tests read the rendered DOM and its
 * computed styles. The form scenarios (REQ-DIV-002) drive the real edit path —
 * edit mode → right-click → Edit → the Add/Edit Widget modal — and read the
 * saved content back over the API. The sizing scenarios (REQ-DIV-006) drive the
 * Add Widget modal and the keyboard move/resize panel. Print scenarios use
 * `page.emulateMedia({ media: 'print' })`; "no API calls" records every request
 * the page makes while the dashboard renders.
 *
 * Tests that are expected to go red against the code as shipped say so at the
 * test, with the file and line that disagrees with the scenario.
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, request, test } from '@playwright/test'
import { closeSidebar, gotoLaunchPad } from './fixtures/widget-flow.ts'
import { BASE_URL } from './support/baseUrl.ts'
import {
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'
import { addWidgetFromMore, enterEditMode as enterEditModeFromMore, switchToDashboard } from './support/dashboardSwitcher.ts'
import { chooseAndAdd } from './support/widgetPicker.ts'

const API = '/index.php/apps/launchpad/api'
const ADMIN = {
	user: process.env.NEXTCLOUD_USER || 'admin',
	pass: process.env.NEXTCLOUD_PASS || 'admin',
}
const STAMP = Date.now().toString(36)
const DASHBOARD_NAME = `Divider widget ${STAMP}`

/** Divider content as the renderer reads it. */
type DividerContent = Record<string, unknown>

/** A placement to seed: its content and grid height (full-row, stacked). */
interface Seed {
	content: DividerContent
	h?: number
	w?: number
}

/** Every placement the file needs, keyed by what the tests call it. */
const SEEDS = {
	lineDefault: { content: { style: 'line' } },
	lineRed: { content: { style: 'line', lineColor: '#ff0000', lineThickness: 3 } },
	lineDashed: {
		content: { style: 'line', lineStyle: 'dashed', lineThickness: 2 },
	},
	lineDotted: {
		content: { style: 'line', lineStyle: 'dotted', lineThickness: 2 },
	},
	lineOrange: { content: { style: 'line', lineColor: '#ff9800' } },
	wsMedium: { content: { style: 'whitespace', whitespaceSize: 'medium' }, h: 2 },
	wsSmall: { content: { style: 'whitespace', whitespaceSize: 'small' }, h: 2 },
	wsLarge: { content: { style: 'whitespace', whitespaceSize: 'large' }, h: 2 },
	wsXlarge: { content: { style: 'whitespace', whitespaceSize: 'xlarge' }, h: 3 },
	headingKey: { content: { style: 'heading-break', headingText: 'Key Section' } },
	headingGreen: {
		content: {
			style: 'heading-break',
			headingText: 'Important',
			lineColor: '#00ff00',
		},
	},
	headingSummary: { content: { style: 'heading-break', headingText: 'Summary' } },
	headingSection: { content: { style: 'heading-break', headingText: 'Section' } },
	headingNoApi: {
		content: { style: 'heading-break', headingText: `Seeded only ${STAMP}` },
	},
	// Form fixtures: each starts on a DIFFERENT style from the one the test
	// selects, so selecting it is a real change.
	editToLine: { content: { style: 'whitespace', whitespaceSize: 'large' }, h: 2 },
	editToWhitespace: { content: { style: 'line' } },
	editToHeading: { content: { style: 'line' } },
	editMinimal: { content: { style: 'line' } },
	// Last, alone in its row, so growing it pushes nothing.
	resize: { content: { style: 'line' }, w: 4, h: 3 },
} satisfies Record<string, Seed>

type SeedKey = keyof typeof SEEDS

let admin: APIRequestContext
let dashboard: SeededDashboard | null = null
const ids = {} as Record<SeedKey, number>
const extraPlacements: number[] = []

// Not serial: several tests are expected red as shipped (see each), and a
// serial file would skip everything after the first failure. One worker runs
// the file in order; after a failure the new worker re-seeds in beforeAll.
test.describe.configure({ timeout: 90_000 })

test.beforeAll(async () => {
	admin = await request.newContext({
		baseURL: BASE_URL,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true', Accept: 'application/json' },
	})
	dashboard = await seedActiveDashboard(admin, DASHBOARD_NAME)

	// Start below anything the dashboard was created with.
	let y = 0
	for (const p of await placementsOf(dashboard.id)) {
		y = Math.max(y, Number(p.gridY) + Number(p.gridHeight))
	}
	for (const [key, seed] of Object.entries(SEEDS) as Array<[SeedKey, Seed]>) {
		const h = seed.h ?? 1
		const res = await admin.post(`${API}/dashboard/${dashboard.id}/widgets`, {
			data: {
				widgetId: 'divider',
				gridX: 0,
				gridY: y,
				gridWidth: seed.w ?? 12,
				gridHeight: h,
				content: seed.content,
			},
		})
		expect(res.status(), await res.text()).toBeLessThan(300)
		ids[key] = placementIdOf(await res.json())
		y += h
	}
})

test.afterAll(async () => {
	if (!admin) {
		return
	}
	for (const id of extraPlacements) {
		await admin.delete(`${API}/widgets/${id}`).catch(() => undefined)
	}
	await removeSeededDashboard(admin, dashboard)
	await admin.dispose()
})

/**
 * The placement id in a create response, whatever envelope it came in.
 *
 * @param body The parsed JSON body.
 */
function placementIdOf(body: any): number {
	const id = Number(
		body?.widget?.id ?? body?.placement?.id ?? body?.data?.id ?? body?.id,
	)
	expect(id, `no placement id in ${JSON.stringify(body)}`).toBeTruthy()
	return id
}

/**
 * The placements of a dashboard, read back over the API.
 *
 * @param dashboardId The dashboard id.
 */
async function placementsOf(dashboardId: number): Promise<any[]> {
	const res = await admin.get(`${API}/dashboard/${dashboardId}`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	return body.placements ?? body.data?.placements ?? []
}

/**
 * One placement of the seeded dashboard, read back over the API.
 *
 * @param id The placement id.
 */
async function placement(id: number): Promise<any> {
	const found = (await placementsOf(dashboard!.id)).find(
		(p) => Number(p.id) === id,
	)
	expect(found, `placement ${id} not on the dashboard`).toBeTruthy()
	return found
}

/** The grid cell (Views.vue `.launchpad-grid-item`) holding a placement. */
function cell(page: Page, key: SeedKey): Locator {
	return page.locator(`.launchpad-grid-item[data-placement-id="${ids[key]}"]`)
}

/** The rendered divider root (`.cn-divider-widget`) of a placement. */
function divider(page: Page, key: SeedKey): Locator {
	return cell(page, key).locator('.cn-divider-widget')
}

/** The GridStack item wrapping a placement. */
function gridItem(page: Page, id: number): Locator {
	return page
		.locator('.grid-stack-item')
		.filter({ has: page.locator(`[data-placement-id="${id}"]`) })
}

/**
 * A GridStack size attribute. GridStack omits `gs-w` / `gs-h` when the value
 * is 1, so a missing attribute reads as 1.
 *
 * @param item The `.grid-stack-item`.
 * @param name `gs-w` or `gs-h`.
 */
async function gsSize(item: Locator, name: 'gs-w' | 'gs-h'): Promise<number> {
	return Number((await item.getAttribute(name)) ?? 1)
}

/** Open the seeded dashboard and wait until every seeded divider rendered. */
async function openDashboard(page: Page): Promise<void> {
	await gotoLaunchPad(page)
	await expect(divider(page, 'lineDefault')).toBeAttached({ timeout: 15_000 })
}

/**
 * The colour `var(--color-border)` resolves to at `el`, read from a probe
 * element so it inherits exactly what the divider inherits.
 *
 * @param el The element whose context the theme variable is read in.
 */
async function themeBorderColor(el: Locator): Promise<string> {
	return el.evaluate((node) => {
		const probe = document.createElement('div')
		probe.style.borderTop = '1px solid var(--color-border)'
		node.appendChild(probe)
		const color = getComputedStyle(probe).borderTopColor
		probe.remove()
		return color
	})
}

/** Computed style properties of an element. */
async function styleOf(
	el: Locator,
	props: string[],
): Promise<Record<string, string>> {
	return el.evaluate((node, names) => {
		const cs = getComputedStyle(node)
		return Object.fromEntries(names.map((n) => [n, cs.getPropertyValue(n)]))
	}, props)
}

/**
 * Whether the element or any ancestor is hidden by display, visibility or
 * opacity — the "hide rules" the print scenarios rule out.
 *
 * @param el The element.
 */
async function hiddenBy(el: Locator): Promise<string | null> {
	return el.evaluate((node) => {
		for (let n: Element | null = node; n; n = n.parentElement) {
			const cs = getComputedStyle(n)
			if (cs.display === 'none') return `display:none on ${n.className}`
			if (cs.visibility === 'hidden')
				return `visibility:hidden on ${n.className}`
			if (cs.opacity === '0') return `opacity:0 on ${n.className}`
		}
		return null
	})
}

/** Switch the seeded dashboard into edit mode via its sidebar row cog. */
async function enterEditMode(page: Page): Promise<void> {
	if ((await page.locator('.launchpad-edit-mode').count()) > 0) {
		return
	}
	await switchToDashboard(page, DASHBOARD_NAME)
	await enterEditModeFromMore(page)
}

/**
 * Open the right-click context menu of a placement (edit mode).
 * `dispatchEvent` rather than a real right-click: see
 * add-widget-modal.spec.ts for the viewport retries a real click hits.
 *
 * @param page The page.
 * @param key The seeded placement.
 */
async function openContextMenu(page: Page, key: SeedKey): Promise<void> {
	await enterEditMode(page)
	await expect(cell(page, key)).toBeAttached({ timeout: 8_000 })
	await cell(page, key).dispatchEvent('contextmenu')
	await expect(page.locator('[data-testid="widget-context-menu"]')).toBeVisible({
		timeout: 5_000,
	})
}

/**
 * Open the divider's edit form (right-click → Edit) and return the dialog.
 *
 * @param page The page.
 * @param key The seeded placement.
 */
async function openEditForm(page: Page, key: SeedKey): Promise<Locator> {
	await openDashboard(page)
	await openContextMenu(page, key)
	await page.locator('[data-testid="ctx-edit"]').click()
	const dialog = page.getByRole('dialog', { name: /edit widget/i }).first()
	await expect(dialog).toBeVisible({ timeout: 8_000 })
	await expect(dialog.locator('.cn-divider-widget-form')).toBeVisible()
	return dialog
}

/**
 * Pick an option in one of the form's NcSelect (vue-select) controls.
 *
 * @param page The page (the option list is appended to the body).
 * @param control The combobox.
 * @param option The visible option label.
 */
async function pick(page: Page, control: Locator, option: string): Promise<void> {
	await control.click()
	await page
		.locator('.vs__dropdown-option')
		.filter({
			hasText: new RegExp(`^\\s*${option.replace(/[()]/g, '\\$&')}\\s*$`),
		})
		.first()
		.click()
}

/** The selected label of an NcSelect, read from its `.vs__selected`. */
function selectedOf(control: Locator): Locator {
	return control
		.locator('xpath=ancestor::*[contains(@class,"v-select")][1]')
		.locator('.vs__selected')
}

/**
 * Save the edit dialog, then the edit-mode draft it changed (REQ-EDIT-002),
 * and wait for that one dashboard request to land with the widget in it.
 */
async function saveEdit(page: Page, dialog: Locator, id: number): Promise<void> {
	const save = dialog.getByRole('button', { name: /^save$/i })
	await expect(save).toBeEnabled({ timeout: 5_000 })
	await save.click()
	await expect(dialog).not.toBeVisible({ timeout: 8_000 })
	const put = page.waitForResponse(
		(res) =>
			res.request().method() === 'PUT'
			&& res.url().includes(`/api/dashboard/${dashboard!.id}`)
			&& (res.request().postDataJSON()?.placements ?? []).some((p: { id?: number }) => Number(p.id) === id),
		{ timeout: 10_000 },
	)
	await page.getByTestId('launchpad-edit-bar-save').click()
	expect((await put).status()).toBeLessThan(300)
}

/** The combobox of a labelled NcSelect in the divider form. */
function combo(dialog: Locator, label: RegExp): Locator {
	return dialog
		.locator('.cn-divider-widget-form')
		.getByRole('combobox', { name: label })
}

/** "Unset" for a nullable colour: the form stores '' where the spec says null. */
function expectUnsetColor(value: unknown): void {
	expect(
		value === null || value === '' || value === undefined,
		`lineColor ${JSON.stringify(value)}`,
	).toBe(true)
}

// ─── REQ-DIV-002 Configure divider style ─────────────────────────────────────

test.describe('REQ-DIV-002 the divider edit form', () => {
	// @e2e divider-widget::line-style-configuration
	test('selecting line exposes colour, thickness 1–8 and line style, and persists the line defaults', async ({
		page,
	}) => {
		const dialog = await openEditForm(page, 'editToLine')
		const form = dialog.locator('.cn-divider-widget-form')
		await pick(page, combo(dialog, /^style$/i), 'Horizontal line')

		// lineColor: a colour picker, empty = the theme border colour.
		await expect(form.getByText('Line color').first()).toBeVisible()
		// lineThickness: a number input, 1..8, default 1.
		const thickness = form.getByLabel(/thickness/i)
		await expect(thickness).toBeVisible()
		await expect(thickness).toHaveAttribute('type', 'number')
		await expect(thickness).toHaveAttribute('min', '1')
		await expect(thickness).toHaveAttribute('max', '8')
		await expect(thickness).toHaveValue('1')
		// lineStyle: solid / dashed / dotted, default solid.
		const lineStyle = combo(dialog, /^line style$/i)
		await expect(lineStyle).toBeVisible()
		await expect(selectedOf(lineStyle)).toHaveText(/solid/i)
		await lineStyle.click()
		await expect(page.locator('.vs__dropdown-option')).toHaveText([
			/solid/i,
			/dashed/i,
			/dotted/i,
		])
		// Close the list by re-picking the current value (Escape would also
		// close the dialog).
		await page.locator('.vs__dropdown-option').first().click()

		await saveEdit(page, dialog, ids.editToLine)
		const content = (await placement(ids.editToLine)).content
		expect(content).toMatchObject({
			style: 'line',
			lineThickness: 1,
			lineStyle: 'solid',
		})
		expectUnsetColor(content.lineColor)
	})

	// @e2e divider-widget::whitespace-style-configuration
	test('selecting whitespace exposes the four sizes with their heights and persists medium', async ({
		page,
	}) => {
		const dialog = await openEditForm(page, 'editToWhitespace')
		await pick(page, combo(dialog, /^style$/i), 'Whitespace')

		const size = combo(dialog, /spacing size/i)
		await expect(size).toBeVisible()
		await expect(selectedOf(size)).toHaveText(/medium/i)
		// The size names map to 16 / 32 / 64 / 128 px.
		await size.click()
		await expect(page.locator('.vs__dropdown-option')).toHaveText([
			/small.*16px/i,
			/medium.*32px/i,
			/large.*64px/i,
			/extra large.*128px/i,
		])
		await page.locator('.vs__dropdown-option').nth(1).click()
		// It is the only extra field: no line controls for whitespace.
		await expect(dialog.getByLabel(/thickness/i)).toHaveCount(0)
		await expect(combo(dialog, /^line style$/i)).toHaveCount(0)

		await saveEdit(page, dialog, ids.editToWhitespace)
		expect((await placement(ids.editToWhitespace)).content).toMatchObject({
			style: 'whitespace',
			whitespaceSize: 'medium',
		})
		// ...and medium renders 32px tall.
		const block = divider(page, 'editToWhitespace').locator(
			'.cn-divider-widget__whitespace',
		)
		await expect(block).toBeAttached({ timeout: 8_000 })
		expect((await styleOf(block, ['height'])).height).toBe('32px')
	})

	// @e2e divider-widget::heading-break-style-configuration
	test('selecting heading-break requires heading text, offers colour and line style, and persists them', async ({
		page,
	}) => {
		const dialog = await openEditForm(page, 'editToHeading')
		const form = dialog.locator('.cn-divider-widget-form')
		await pick(page, combo(dialog, /^style$/i), 'Heading with lines')

		const heading = form.getByLabel(/heading text/i)
		await expect(heading).toBeVisible()
		await expect(heading).toHaveAttribute('required', /.*/)
		// Required: an empty heading cannot be saved.
		await expect(dialog.getByRole('button', { name: /^save$/i })).toBeDisabled()
		// Optional lineColor and lineStyle for the flanking lines.
		await expect(form.getByText('Line color').first()).toBeVisible()
		await expect(combo(dialog, /^line style$/i)).toBeVisible()

		await heading.fill('Important Section')
		await saveEdit(page, dialog, ids.editToHeading)
		const content = (await placement(ids.editToHeading)).content
		expect(content).toMatchObject({
			style: 'heading-break',
			headingText: 'Important Section',
			lineStyle: 'solid',
		})
		expectUnsetColor(content.lineColor)
	})

	// Expected RED as shipped: the shared CnAddWidgetModal renders an
	// "Appearance" block (Show title, Custom title, Background, Icon) for every
	// type, divider included —
	// node_modules/@conduction/nextcloud-vue/src/dialogs/CnAddWidgetModal.vue:55-93.
	// @e2e divider-widget::edit-form-is-minimal
	test('the edit form holds only the style control and its fields, each clearly labelled', async ({
		page,
	}) => {
		const dialog = await openEditForm(page, 'editMinimal')
		const form = dialog.locator('.cn-divider-widget-form')

		// Visual labels for every field, per style.
		await expect(combo(dialog, /^style$/i)).toBeVisible()
		await expect(form.getByText('Line color').first()).toBeVisible()
		await expect(form.getByLabel(/thickness/i)).toBeVisible()
		await expect(combo(dialog, /^line style$/i)).toBeVisible()
		await pick(page, combo(dialog, /^style$/i), 'Whitespace')
		await expect(combo(dialog, /spacing size/i)).toBeVisible()
		await pick(page, combo(dialog, /^style$/i), 'Heading with lines')
		await expect(form.getByLabel(/heading text/i)).toBeVisible()

		// ONLY those: no name/title, icon, click-target or other standard fields
		// anywhere in the dialog.
		await expect(dialog.getByLabel(/title|name/i)).toHaveCount(0)
		await expect(dialog.getByText(/^icon$/i)).toHaveCount(0)
		await expect(dialog.getByLabel(/link|url|target/i)).toHaveCount(0)
		await expect(dialog.locator('.cn-add-widget-modal__chrome')).toHaveCount(0)
	})
})

// ─── REQ-DIV-003 Render line divider ─────────────────────────────────────────

test.describe('REQ-DIV-003 line render', () => {
	test.beforeEach(async ({ page }) => {
		await openDashboard(page)
	})

	// @e2e divider-widget::default-line-render
	test('a line without colour is a 1px solid full-width rule in the theme border colour', async ({
		page,
	}) => {
		const root = divider(page, 'lineDefault')
		const line = root.locator('.cn-divider-widget__line')
		await expect(line).toBeVisible()
		const s = await styleOf(line, [
			'border-bottom-width',
			'border-bottom-style',
			'border-bottom-color',
		])
		expect(s['border-bottom-width']).toBe('1px')
		expect(s['border-bottom-style']).toBe('solid')
		expect(s['border-bottom-color']).toBe(await themeBorderColor(root))
		// Inline style is literally the theme variable.
		expect(await line.getAttribute('style')).toContain('var(--color-border)')

		const lineBox = await line.boundingBox()
		const rootBox = await root.boundingBox()
		expect(rootBox!.width).toBeGreaterThan(0)
		expect(Math.abs(lineBox!.width - rootBox!.width)).toBeLessThanOrEqual(1)
	})

	// @e2e divider-widget::custom-line-color-render
	test('lineColor #ff0000 with thickness 3 is a 3px solid red rule, not the theme colour', async ({
		page,
	}) => {
		const root = divider(page, 'lineRed')
		const line = root.locator('.cn-divider-widget__line')
		await expect(line).toBeVisible()
		const s = await styleOf(line, [
			'border-bottom-width',
			'border-bottom-style',
			'border-bottom-color',
		])
		expect(s['border-bottom-width']).toBe('3px')
		expect(s['border-bottom-style']).toBe('solid')
		expect(s['border-bottom-color']).toBe('rgb(255, 0, 0)')
		expect(await themeBorderColor(root)).not.toBe('rgb(255, 0, 0)')
		expect(await line.getAttribute('style')).not.toContain('--color-border')
	})

	// @e2e divider-widget::dashed-line-style
	test('lineStyle dashed with thickness 2 is a 2px dashed rule, and dotted is supported', async ({
		page,
	}) => {
		const dashed = divider(page, 'lineDashed').locator(
			'.cn-divider-widget__line',
		)
		await expect(dashed).toBeVisible()
		expect(
			await styleOf(dashed, ['border-bottom-width', 'border-bottom-style']),
		).toEqual({ 'border-bottom-width': '2px', 'border-bottom-style': 'dashed' })

		const dotted = divider(page, 'lineDotted').locator(
			'.cn-divider-widget__line',
		)
		await expect(dotted).toBeVisible()
		expect(
			(await styleOf(dotted, ['border-bottom-style']))['border-bottom-style'],
		).toBe('dotted')
	})

	// @e2e divider-widget::line-accessibility
	test('a line divider is role=separator with no text content', async ({
		page,
	}) => {
		const line = divider(page, 'lineDefault').locator('.cn-divider-widget__line')
		await expect(line).toHaveAttribute('role', 'separator')
		expect(await line.textContent()).toBe('')
		expect((await divider(page, 'lineDefault').innerText()).trim()).toBe('')
	})
})

// ─── REQ-DIV-004 Render whitespace divider ───────────────────────────────────

test.describe('REQ-DIV-004 whitespace render', () => {
	test.beforeEach(async ({ page }) => {
		await openDashboard(page)
	})

	// @e2e divider-widget::default-whitespace-render
	test('medium whitespace is a transparent 32px block that takes grid space and shows nothing', async ({
		page,
	}) => {
		const block = divider(page, 'wsMedium').locator(
			'.cn-divider-widget__whitespace',
		)
		await expect(block).toBeVisible()
		const s = await styleOf(block, [
			'height',
			'background-color',
			'border-bottom-width',
		])
		expect(s.height).toBe('32px')
		expect(s['background-color']).toBe('rgba(0, 0, 0, 0)')
		expect(s['border-bottom-width']).toBe('0px')
		// No visible content.
		expect(await block.textContent()).toBe('')
		expect(await block.evaluate((n) => n.children.length)).toBe(0)

		// It contributes to grid flow: its grid item occupies the rows it was
		// seeded with, and the next placement starts below it.
		const item = gridItem(page, ids.wsMedium)
		expect(await gsSize(item, 'gs-h')).toBe(2)
		const box = await item.boundingBox()
		expect(box!.height).toBeGreaterThan(0)
		const next = await gridItem(page, ids.wsSmall).boundingBox()
		expect(next!.y).toBeGreaterThanOrEqual(box!.y + box!.height - 1)
	})

	// @e2e divider-widget::custom-whitespace-sizes
	test('small / medium / large / xlarge render 16 / 32 / 64 / 128px, at desktop and narrow widths', async ({
		page,
	}) => {
		const expected: Array<[SeedKey, string]> = [
			['wsSmall', '16px'],
			['wsMedium', '32px'],
			['wsLarge', '64px'],
			['wsXlarge', '128px'],
		]
		const heights = async () => {
			const out: string[] = []
			for (const [key] of expected) {
				const block = divider(page, key).locator(
					'.cn-divider-widget__whitespace',
				)
				await expect(block).toBeAttached()
				out.push((await styleOf(block, ['height'])).height)
			}
			return out
		}
		expect(await heights()).toEqual(expected.map(([, h]) => h))

		// Responsive: at a 4-column tablet width the sizes stay the same fixed
		// presets (predictable — they do not stretch with the column count).
		await page.setViewportSize({ width: 700, height: 900 })
		await expect
			.poll(
				async () =>
					(await divider(page, 'wsSmall').boundingBox())?.width ?? 0,
			)
			.toBeLessThan(700)
		expect(await heights()).toEqual(expected.map(([, h]) => h))
	})

	// @e2e divider-widget::whitespace-accessibility
	test('a whitespace divider is role=separator with no text content', async ({
		page,
	}) => {
		const block = divider(page, 'wsMedium').locator(
			'.cn-divider-widget__whitespace',
		)
		await expect(block).toHaveAttribute('role', 'separator')
		expect(await block.textContent()).toBe('')
		expect((await divider(page, 'wsMedium').innerText()).trim()).toBe('')
	})
})

// ─── REQ-DIV-005 Render heading-break divider ────────────────────────────────

/** The heading-break parts of a placement. */
function headingParts(page: Page, key: SeedKey) {
	const root = divider(page, key)
	const wrap = root.locator('.cn-divider-widget__heading-break')
	return {
		root,
		wrap,
		h3: wrap.locator('h3'),
		lines: wrap.locator('hr.cn-divider-widget__heading-line'),
	}
}

test.describe('REQ-DIV-005 heading-break render', () => {
	test.beforeEach(async ({ page }) => {
		await openDashboard(page)
	})

	// @e2e divider-widget::default-heading-break-render
	test('"Key Section" is a centred h3 between two lines in the theme border colour', async ({
		page,
	}) => {
		const { root, wrap, h3, lines } = headingParts(page, 'headingKey')
		await expect(h3).toHaveText('Key Section')
		expect(await h3.evaluate((n) => n.tagName)).toBe('H3')
		await expect(lines).toHaveCount(2)
		// Between: DOM order is line, h3, line.
		expect(
			await wrap.evaluate((n) => Array.from(n.children).map((c) => c.tagName)),
		).toEqual(['HR', 'H3', 'HR'])

		// Centred in the widget.
		const hb = (await h3.boundingBox())!
		const wb = (await wrap.boundingBox())!
		expect(
			Math.abs(hb.x + hb.width / 2 - (wb.x + wb.width / 2)),
		).toBeLessThanOrEqual(2)

		// Both lines use the theme border colour by default.
		const theme = await themeBorderColor(root)
		for (const line of await lines.all()) {
			const s = await styleOf(line, ['border-top-color', 'border-top-width'])
			expect(s['border-top-color']).toBe(theme)
			expect(s['border-top-width']).toBe('1px')
		}
	})

	// @e2e divider-widget::custom-line-color-on-heading-break
	test('lineColor #00ff00 makes both lines green; the heading is centred, full width, with room around it', async ({
		page,
	}) => {
		const { root, wrap, h3, lines } = headingParts(page, 'headingGreen')
		await expect(h3).toHaveText('Important')
		await expect(lines).toHaveCount(2)
		for (const line of await lines.all()) {
			expect(
				(await styleOf(line, ['border-top-color']))['border-top-color'],
			).toBe('rgb(0, 255, 0)')
		}

		const rb = (await root.boundingBox())!
		const wb = (await wrap.boundingBox())!
		const hb = (await h3.boundingBox())!
		// The heading row spans the widget width, and the text sits centred in it.
		expect(Math.abs(wb.width - rb.width)).toBeLessThanOrEqual(1)
		expect(
			Math.abs(hb.x + hb.width / 2 - (wb.x + wb.width / 2)),
		).toBeLessThanOrEqual(2)
		// Breathing room: a gap between each line and the text.
		const [first, second] = [
			(await lines.nth(0).boundingBox())!,
			(await lines.nth(1).boundingBox())!,
		]
		expect(hb.x - (first.x + first.width)).toBeGreaterThanOrEqual(8)
		expect(second.x - (hb.x + hb.width)).toBeGreaterThanOrEqual(8)
	})

	// Likely RED as shipped: the h3 sits INSIDE the role="separator" wrapper
	// (CnDividerWidget.vue:32-41), and ARIA makes a separator's children
	// presentational, so Chromium does not expose the h3 as a heading.
	// @e2e divider-widget::heading-break-accessibility
	test('the h3 holds "Summary", the wrapper is labelled "Summary divider", and AT gets a level-3 heading', async ({
		page,
	}) => {
		const { wrap, h3 } = headingParts(page, 'headingSummary')
		await expect(h3).toHaveText('Summary')
		await expect(wrap).toHaveAttribute('aria-label', 'Summary divider')

		// What a screen reader is given: Chromium's own accessibility tree.
		const cdp = await page.context().newCDPSession(page)
		const { nodes } = (await cdp.send('Accessibility.getFullAXTree')) as {
			nodes: Array<{
				role?: { value?: string }
				name?: { value?: string }
				properties?: Array<{ name: string; value: { value: unknown } }>
				ignored?: boolean
			}>
		}
		await cdp.detach()
		const named = (role: string, name: string) =>
			nodes.filter(
				(n) =>
					!n.ignored && n.role?.value === role && n.name?.value === name,
			)
		// Chromium names the ARIA separator role "separator" or "splitter".
		expect(
			named('separator', 'Summary divider').length
				+ named('splitter', 'Summary divider').length,
		).toBeGreaterThan(0)
		const headings = named('heading', 'Summary')
		expect(headings, 'the Summary h3 is exposed as a heading').toHaveLength(1)
		const level = headings[0].properties?.find((p) => p.name === 'level')
		expect(level?.value.value).toBe(3)
	})
})

// ─── REQ-DIV-006 Default widget sizing ───────────────────────────────────────

/**
 * Add a divider through the Add Widget modal of the seeded dashboard and
 * return the POST body the app sent and the created placement id.
 *
 * @param page The page.
 */
async function addDividerThroughModal(
	page: Page,
): Promise<{ body: any; id: number }> {
	await openDashboard(page)
	await switchToDashboard(page, DASHBOARD_NAME)
	await addWidgetFromMore(page)
	await chooseAndAdd(page.locator('[data-testid="widget-picker-type-divider"]'))
	const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
	await expect(dialog).toBeVisible({ timeout: 10_000 })
	await expect(dialog.locator('.cn-divider-widget-form')).toBeVisible()

	// REQ-EDIT-001/002: the divider joins the draft; the edit-mode Save
	// creates it, in the one dashboard request.
	const before = new Set((await placementsOf(dashboard!.id)).map((p) => Number(p.id)))
	await dialog.getByRole('button', { name: /^add$/i }).click()
	await expect(dialog).not.toBeVisible({ timeout: 8_000 })
	await closeSidebar(page)
	const put = page.waitForResponse(
		(res) =>
			res.request().method() === 'PUT'
			&& res.url().includes(`/api/dashboard/${dashboard!.id}`)
			&& res.request().postDataJSON()?.draft === true,
		{ timeout: 10_000 },
	)
	await page.getByTestId('launchpad-edit-bar-save').click()
	const res = await put
	expect(res.status(), await res.text()).toBeLessThan(300)
	const added = (await placementsOf(dashboard!.id)).find((p) => !before.has(Number(p.id)) && p.widgetId === 'divider')
	const id = Number(added.id)
	extraPlacements.push(id)
	const body = (res.request().postDataJSON().placements as Array<Record<string, unknown>>)
		.find((p) => p.id === undefined && p.widgetId === 'divider')
	return { body, id }
}

test.describe('REQ-DIV-006 sizing', () => {
	// Expected RED as shipped: every custom widget is created at
	// DEFAULT_W x DEFAULT_H = 4 x 4 (src/stores/dashboard.js:1069,
	// src/composables/useGridManager.js:151-158); the modal has no grid fields
	// and the divider registration declares no size.
	// @e2e divider-widget::divider-defaults-to-gridheight-1
	test('a divider added through the modal is created one row tall', async ({
		page,
	}) => {
		const { body, id } = await addDividerThroughModal(page)
		expect(body.widgetId).toBe('divider')
		expect(body.gridHeight).toBe(1)
		expect(Number((await placement(id)).gridHeight)).toBe(1)
		await expect(gridItem(page, id)).toBeAttached({ timeout: 8_000 })
		expect(await gsSize(gridItem(page, id), 'gs-h')).toBe(1)
	})

	// Expected RED as shipped, same cause as the test above.
	// @e2e divider-widget::divider-defaults-to-full-dashboard-width
	test('a divider added through the modal spans the full dashboard width', async ({
		page,
	}) => {
		const { body, id } = await addDividerThroughModal(page)
		const res = await admin.get(`${API}/dashboard/${dashboard!.id}`)
		const columns = Number((await res.json()).dashboard?.gridColumns || 12)
		expect(body.gridWidth).toBe(columns)
		expect(Number((await placement(id)).gridWidth)).toBe(columns)

		const item = gridItem(page, id)
		await expect(item).toBeAttached({ timeout: 8_000 })
		expect(await gsSize(item, 'gs-w')).toBe(columns)
		const grid = (await page.locator('.grid-stack').first().boundingBox())!
		const box = (await item.boundingBox())!
		expect(box.width).toBeGreaterThanOrEqual(grid.width - 2 * 16)
	})

	// Expected RED as shipped: the move/resize panel clamps height at
	// MIN_CELLS = 2 (src/composables/useGridManager.js:222, 292), so a divider
	// cannot be made one row tall.
	// @e2e divider-widget::user-can-override-defaults
	test('the user resizes a divider to 8 wide by 1 tall, and it persists and renders that way', async ({
		page,
	}) => {
		await openDashboard(page)
		await openContextMenu(page, 'resize')
		await page.locator('[data-testid="ctx-move"]').click()
		const panel = page.locator('[data-test="widget-move-panel"]')
		await expect(panel).toBeVisible({ timeout: 5_000 })
		const readout = panel.locator('[data-test="widget-move-readout"]')
		await expect(readout).toContainText('4 wide by 3 tall')

		for (let i = 0; i < 4; i++) {
			await panel.locator('[data-test="grow-width"]').click()
		}
		for (let i = 0; i < 2; i++) {
			await panel.locator('[data-test="shrink-height"]').click()
		}
		await expect(readout).toContainText('8 wide by 1 tall')
		await panel.locator('[data-test="move-save"]').click()
		await expect(panel).not.toBeVisible({ timeout: 5_000 })
		// REQ-EDIT-002: the resize is in the draft until Save.
		await page.getByTestId('launchpad-edit-bar-save').click()

		// Persisted...
		await expect
			.poll(
				async () => {
					const p = await placement(ids.resize)
					return [Number(p.gridWidth), Number(p.gridHeight)]
				},
				{ timeout: 10_000 },
			)
			.toEqual([8, 1])
		// ...and respected on render after a reload.
		await openDashboard(page)
		const item = gridItem(page, ids.resize)
		await expect(item).toBeAttached({ timeout: 8_000 })
		expect(await gsSize(item, 'gs-w')).toBe(8)
		expect(await gsSize(item, 'gs-h')).toBe(1)
	})
})

// ─── REQ-DIV-007 Theme awareness ─────────────────────────────────────────────

/**
 * Redefine `--color-border` on <body>, the way a theme switch does, and
 * return a function that restores it.
 *
 * @param page The page.
 * @param value The new colour.
 */
async function switchThemeBorder(
	page: Page,
	value: string,
): Promise<() => Promise<void>> {
	await page.evaluate(
		(v) => document.body.style.setProperty('--color-border', v),
		value,
	)
	return () =>
		page.evaluate(() => document.body.style.removeProperty('--color-border'))
}

test.describe('REQ-DIV-007 theme awareness', () => {
	test.beforeEach(async ({ page }) => {
		await openDashboard(page)
	})

	// @e2e divider-widget::default-theme-color
	test('a line without colour uses var(--color-border) and follows a theme change', async ({
		page,
	}) => {
		const root = divider(page, 'lineDefault')
		const line = root.locator('.cn-divider-widget__line')
		await expect(line).toBeVisible()
		expect(await line.getAttribute('style')).toMatch(
			/border-bottom-color:\s*var\(--color-border\)/,
		)
		expect(
			(await styleOf(line, ['border-bottom-color']))['border-bottom-color'],
		).toBe(await themeBorderColor(root))

		const restore = await switchThemeBorder(page, 'rgb(12, 34, 56)')
		try {
			await expect
				.poll(
					async () =>
						(await styleOf(line, ['border-bottom-color']))[
							'border-bottom-color'
						],
				)
				.toBe('rgb(12, 34, 56)')
		} finally {
			await restore()
		}
	})

	// @e2e divider-widget::explicit-color-overrides-theme
	test('lineColor #ff9800 renders orange and ignores --color-border, even when the theme changes', async ({
		page,
	}) => {
		const line = divider(page, 'lineOrange').locator('.cn-divider-widget__line')
		await expect(line).toBeVisible()
		expect(
			(await styleOf(line, ['border-bottom-color']))['border-bottom-color'],
		).toBe('rgb(255, 152, 0)')
		const restore = await switchThemeBorder(page, 'rgb(12, 34, 56)')
		try {
			// The default line follows the change; the explicit one does not.
			await expect
				.poll(
					async () =>
						(
							await styleOf(
								divider(page, 'lineDefault').locator(
									'.cn-divider-widget__line',
								),
								['border-bottom-color'],
							)
						)['border-bottom-color'],
				)
				.toBe('rgb(12, 34, 56)')
			expect(
				(await styleOf(line, ['border-bottom-color']))[
					'border-bottom-color'
				],
			).toBe('rgb(255, 152, 0)')
		} finally {
			await restore()
		}
	})

	// @e2e divider-widget::heading-break-inherits-or-overrides-line-color
	test('heading-break lines use --color-border without a colour, and the explicit colour when set', async ({
		page,
	}) => {
		const plain = headingParts(page, 'headingKey')
		await expect(plain.lines).toHaveCount(2)
		const theme = await themeBorderColor(plain.root)
		for (const line of await plain.lines.all()) {
			expect(await line.getAttribute('style')).toContain('var(--color-border)')
			expect(
				(await styleOf(line, ['border-top-color']))['border-top-color'],
			).toBe(theme)
		}

		const coloured = headingParts(page, 'headingGreen')
		await expect(coloured.lines).toHaveCount(2)
		for (const line of await coloured.lines.all()) {
			expect(
				(await styleOf(line, ['border-top-color']))['border-top-color'],
			).toBe('rgb(0, 255, 0)')
		}
	})
})

// ─── REQ-DIV-008 Print support ───────────────────────────────────────────────

test.describe('REQ-DIV-008 print', () => {
	test.beforeEach(async ({ page }) => {
		await openDashboard(page)
		await page.emulateMedia({ media: 'print' })
	})

	test.afterEach(async ({ page }) => {
		await page.emulateMedia({ media: 'screen' })
	})

	// @e2e divider-widget::line-divider-prints
	test('in print media the line divider is visible and nothing hides it', async ({
		page,
	}) => {
		const line = divider(page, 'lineDefault').locator('.cn-divider-widget__line')
		await expect(line).toBeVisible()
		expect(await hiddenBy(line)).toBeNull()
		const s = await styleOf(line, ['border-bottom-width', 'border-bottom-style'])
		expect(s['border-bottom-width']).toBe('1px')
		expect(s['border-bottom-style']).toBe('solid')
	})

	// @e2e divider-widget::whitespace-divider-prints
	test('in print media the whitespace keeps its configured gap', async ({
		page,
	}) => {
		for (const [key, height] of [
			['wsMedium', 32],
			['wsLarge', 64],
		] as Array<[SeedKey, number]>) {
			const block = divider(page, key).locator(
				'.cn-divider-widget__whitespace',
			)
			await expect(block).toBeVisible()
			expect(await hiddenBy(block)).toBeNull()
			expect((await block.boundingBox())!.height).toBeCloseTo(height, 0)
		}
	})

	// @e2e divider-widget::heading-break-divider-prints
	test('in print media the "Section" heading and its lines are visible and readable', async ({
		page,
	}) => {
		const { h3, lines } = headingParts(page, 'headingSection')
		await expect(h3).toBeVisible()
		await expect(h3).toHaveText('Section')
		expect(await hiddenBy(h3)).toBeNull()
		expect(
			parseFloat((await styleOf(h3, ['font-size']))['font-size']),
		).toBeGreaterThanOrEqual(12)
		await expect(lines).toHaveCount(2)
		for (const line of await lines.all()) {
			await expect(line).toBeVisible()
			expect(await hiddenBy(line)).toBeNull()
		}
	})
})

// ─── REQ-DIV-009 No backend endpoints ────────────────────────────────────────

test.describe('REQ-DIV-009 client-side only', () => {
	// @e2e divider-widget::divider-is-rendered-without-api-calls
	test('rendering the dashboard calls no divider endpoint and draws from the stored content alone', async ({
		page,
	}) => {
		const urls: string[] = []
		page.on('request', (req) => urls.push(req.url()))

		await openDashboard(page)
		const { h3 } = headingParts(page, 'headingNoApi')
		await expect(h3).toHaveText(SEEDS.headingNoApi.content.headingText)
		await expect(
			divider(page, 'lineRed').locator('.cn-divider-widget__line'),
		).toBeVisible()
		// Settle: give anything a widget fetches on mount time to be sent.
		// (A load-state wait for an idle network never settles on Nextcloud,
		// which keeps polling; a short fixed pause is the honest bound.)
		await page.waitForTimeout(3_000)

		const dividerIds = Object.values(ids).map(String)
		const custom = urls.filter((u) => {
			const path = new URL(u).pathname
			if (!path.includes('/api/')) {
				return false
			}
			return (
				/divider/i.test(path)
				|| dividerIds.some((id) =>
					new RegExp(`/api/widgets/(?:[a-z-]+/)?${id}(?:/|$)`).test(path),
				)
			)
		})
		expect(custom, 'no request may address a divider or its placement').toEqual(
			[],
		)
	})
})
