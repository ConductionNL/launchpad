/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The two add flows and the style editor, driven the way a person drives them
 * (openspec/specs/widgets/spec.md, REQ-WDG-010..013), written for the
 * e2e-coverage-programme:
 *
 *  - "Add custom widget…" in the More menu opens CnAddWidgetModal, the
 *    registry-driven add/edit modal (src/views/Views.vue:375);
 *  - "Add widget" in the More menu opens WidgetPickerModal, the list of
 *    Nextcloud dashboard widgets (src/modals/WidgetPickerModal.vue);
 *  - a placement's edit cog opens the content editor for a registry type and
 *    the style editor (CnWidgetStyleEditorModal) for a Nextcloud widget
 *    (Views.vue handleContextMenuEdit).
 *
 * "Emits submit" / "emits close" are observed where they land: the
 * placement request the host sends on submit, or the absence of one, and the
 * dashboard re-read afterwards.
 *
 * The admin owns a fresh active dashboard per test and the browser runs as
 * the admin (the suite's stored session).
 *
 * Gate-19 @e2e traceability:
 *   @e2e widgets::open-in-create-mode-without-preselected-type
 *   @e2e widgets::open-in-edit-mode
 *   @e2e widgets::switching-type-resets-form-state
 *   @e2e widgets::submit-emits-only-relevant-fields
 *   @e2e widgets::widget-picker-displays-available-widgets
 *   @e2e widgets::widget-picker-filters-installed-widgets
 *   @e2e widgets::widget-selection-creates-placement
 *   @e2e widgets::style-editor-opens-for-a-widget
 *   @e2e widgets::style-editor-supports-background-and-border-options
 *   @e2e widgets::style-changes-saved-via-api
 *   @e2e widgets::required-field-empty-disables-submit
 *   @e2e widgets::filling-required-field-enables-submit
 *   @e2e widgets::backdrop-click-closes-without-submit
 *   @e2e widgets::esc-key-closes-modal
 *   @e2e widgets::cancel-button-closes-without-submit
 *
 * @spec openspec/specs/widgets/spec.md
 */

import type { APIRequestContext, Locator, Page, Request } from '@playwright/test'
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
	overlaps,
	placementsOf,
	readAllowUserDashboards,
	restoreAllowUserDashboards,
	seedPlacement,
} from './support/widgets.ts'

const STAMP = `E2E WidgetPicker ${Date.now()}`
const TYPE_SELECT = '[data-testid="widget-type-select"]'
const PRIMARY = '[data-testid="add-widget-save"]'
const TEXT_FORM_FIELDS = [
	'text',
	'fontSize',
	'color',
	'backgroundColor',
	'textAlign',
	'contentMode',
	'tableMode',
	'tableData',
]

interface CatalogWidget {
	id: string
	title: string
	iconUrl: string | null
	iconClass: string
}

let admin: APIRequestContext
let dashboard: SeededDashboard | null = null
let allowBefore: boolean | null = null
let catalog: CatalogWidget[] = []

/**
 * Open LaunchPad and wait for the More menu.
 *
 * @param page the page.
 * @return nothing.
 */
async function openLaunchPad(page: Page): Promise<void> {
	await page.goto('/index.php/apps/launchpad/')
	await expect(page.locator(MORE_MENU)).toBeVisible({ timeout: 30_000 })
}

/**
 * Choose an item of the More menu.
 *
 * @param page the page.
 * @param id the item id (`add-widget`, `add-custom-widget`).
 * @return nothing.
 */
async function moreMenu(page: Page, id: string): Promise<void> {
	await page
		.locator(`${MORE_MENU} [data-testid="cn-grouped-menu-trigger"]`)
		.click()
	await page.locator(`[data-testid="cn-grouped-menu-item-${id}"]`).click()
}

/**
 * Open the custom-widget modal in create mode.
 *
 * @param page the page.
 * @return the dialog.
 */
async function openCustomModal(page: Page): Promise<Locator> {
	await moreMenu(page, 'add-custom-widget')
	const dialog = page.getByRole('dialog', { name: 'Add Widget', exact: true })
	await expect(dialog).toBeVisible({ timeout: 10_000 })
	return dialog
}

/**
 * Collect every placement write the page sends from now on.
 *
 * @param page the page.
 * @return the live list of `METHOD path` strings.
 */
function placementWrites(page: Page): string[] {
	const writes: string[] = []
	page.on('request', (req: Request) => {
		const path = new URL(req.url()).pathname
		if (
			(req.method() === 'POST'
				&& /\/api\/dashboard\/\d+\/(widgets|tile)$/.test(path))
			|| (req.method() === 'PUT' && /\/api\/widgets\/\d+$/.test(path))
		) {
			writes.push(`${req.method()} ${path}`)
		}
	})
	return writes
}

/**
 * Pick the text type and type into its (markdown) text area.
 *
 * @param dialog the open modal.
 * @param text what to type.
 * @return nothing.
 */
async function fillText(dialog: Locator, text: string): Promise<void> {
	await dialog.locator(TYPE_SELECT).selectOption('text')
	await dialog.locator('[data-testid="cn-markdown-textarea"]').fill(text)
}

/**
 * Open the edit cog of a placement and choose "Edit widget".
 *
 * @param page the page (in edit mode).
 * @param placementId the placement.
 * @return nothing.
 */
async function editViaCog(page: Page, placementId: number): Promise<void> {
	const cog = page.locator(
		`[data-testid="widget-placement-${placementId}"] [data-testid="cn-widget-edit-cog"]`,
	)
	await expect(cog).toBeVisible({ timeout: 10_000 })
	await cog.getByRole('button').first().click()
	await page.locator('[data-testid="cn-widget-edit-cog-edit"]').click()
}

/** A stock Nextcloud widget the style-editor tests place. */
function stockWidget(): CatalogWidget {
	const chosen = catalog.find((w) => w.iconUrl) ?? catalog[0]
	expect(chosen, 'no Nextcloud widget on this server').toBeTruthy()
	return chosen!
}

/** `#rrggbb` as the `rgb(r, g, b)` a browser reports. */
function rgb(hex: string): string {
	const m = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex)
	expect(m, `${hex} is not a six-digit hex colour`).toBeTruthy()
	return `rgb(${parseInt(m![1], 16)}, ${parseInt(m![2], 16)}, ${parseInt(m![3], 16)})`
}

test.describe.configure({ mode: 'serial' })

test.beforeAll(async () => {
	admin = await adminContext()
	allowBefore = await readAllowUserDashboards(admin)
	const list = await admin.get(`${API}/widgets`)
	expect(list.status(), await list.text()).toBe(200)
	catalog = (await list.json()) as CatalogWidget[]
	expect(catalog.length).toBeGreaterThan(0)
})

test.afterAll(async () => {
	await restoreAllowUserDashboards(admin, allowBefore)
	await admin.dispose()
})

test.beforeEach(async () => {
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

test.describe('REQ-WDG-010 the add/edit modal', () => {
	// @e2e widgets::open-in-create-mode-without-preselected-type
	test("create mode shows the type select, the first type's sub-form and an Add button", async ({
		page,
	}) => {
		await openLaunchPad(page)
		const dialog = await openCustomModal(page)

		const select = dialog.locator(TYPE_SELECT)
		await expect(select).toBeVisible()
		const options = await select.locator('option').evaluateAll((els) =>
			els.map((el) => ({
				value: (el as HTMLOptionElement).value,
				label: el.textContent?.trim() ?? '',
			})),
		)
		const values = options.map((o) => o.value)
		for (const type of ['text', 'label', 'image', 'link']) {
			expect(values, `${type} is not offered`).toContain(type)
		}
		for (const option of options) {
			expect(option.label, `${option.value} has no label`).not.toBe('')
		}
		// The form opens on the first registered type.
		await expect(select).toHaveValue(values[0])
		await expect(dialog.locator('.cn-add-widget-modal__form')).toBeVisible()
		await expect(dialog.locator('.cn-add-widget-modal__empty')).toHaveCount(0)
		await expect(page.locator(PRIMARY)).toHaveText('Add')
	})

	// @e2e widgets::open-in-edit-mode
	test('edit mode hides the type select, pre-fills the image form, and reads Save / Edit Widget', async ({
		page,
	}) => {
		const image = await seedPlacement(admin, dashboard!.id, {
			widgetId: 'image',
			gridX: 0,
			gridY: 0,
			gridWidth: 4,
			gridHeight: 4,
			content: { url: '/img/x.png', alt: 'X', fit: 'cover' },
		})

		await openLaunchPad(page)
		await enterEditMode(page)
		await editViaCog(page, image.id)

		const dialog = page.getByRole('dialog', { name: 'Edit Widget', exact: true })
		await expect(dialog).toBeVisible({ timeout: 10_000 })
		await expect(
			page.getByRole('dialog', { name: 'Add Widget', exact: true }),
		).toHaveCount(0)
		await expect(dialog.locator(TYPE_SELECT)).toHaveCount(0)
		await expect(dialog.getByLabel('Image URL')).toHaveValue('/img/x.png')
		await expect(dialog.getByLabel('Alt text')).toHaveValue('X')
		await expect(
			dialog.locator('.vs__selected', { hasText: 'Cover' }),
		).toBeVisible()
		await expect(page.locator(PRIMARY)).toHaveText('Save')
	})

	// @e2e widgets::switching-type-resets-form-state
	test('switching type swaps the sub-form and nothing typed survives the switch', async ({
		page,
	}) => {
		const typed = `${STAMP} leak`
		await openLaunchPad(page)
		const dialog = await openCustomModal(page)
		await fillText(dialog, typed)
		await expect(
			dialog.locator('[data-testid="cn-markdown-textarea"]'),
		).toHaveValue(typed)

		await dialog.locator(TYPE_SELECT).selectOption('image')
		await expect(dialog.getByLabel('Image URL')).toBeVisible()
		await expect(
			dialog.locator('[data-testid="cn-markdown-textarea"]'),
		).toHaveCount(0)
		await expect(dialog.getByLabel('Image URL')).toHaveValue('')
		await expect(dialog.getByLabel('Alt text')).toHaveValue('')
		const values = await dialog
			.locator('input, textarea')
			.evaluateAll((els) => els.map((el) => (el as HTMLInputElement).value))
		expect(values).not.toContain(typed)

		// Back to text: defaults, not the lost input.
		await dialog.locator(TYPE_SELECT).selectOption('text')
		await expect(
			dialog.locator('[data-testid="cn-markdown-textarea"]'),
		).toHaveValue('')
	})

	// @e2e widgets::submit-emits-only-relevant-fields
	test('Add sends the text type with its own fields and none of the image fields', async ({
		page,
	}) => {
		await openLaunchPad(page)
		const dialog = await openCustomModal(page)
		await dialog.locator(TYPE_SELECT).selectOption('text')
		// Font size applies to the HTML mode only (CnTextWidgetForm).
		await dialog.getByLabel('Mode', { exact: true }).click()
		await page.getByRole('option', { name: 'HTML', exact: true }).click()
		await dialog.locator('.cn-text-widget-form__textarea').fill('Hello')
		await dialog.getByLabel('Font size').fill('16px')

		const post = page.waitForRequest(
			(req) =>
				req.method() === 'POST'
				&& new URL(req.url()).pathname.endsWith(
					`/api/dashboard/${dashboard!.id}/widgets`,
				),
		)
		await page.locator(PRIMARY).click()
		const body = (await post).postDataJSON() as {
			widgetId: string
			content: Record<string, unknown>
		}

		expect(body.widgetId).toBe('text')
		expect(body.content.text).toBe('Hello')
		expect(body.content.fontSize).toBe('16px')
		for (const foreign of ['url', 'alt', 'fit', 'link']) {
			expect(
				body.content,
				`content carries the image field ${foreign}`,
			).not.toHaveProperty(foreign)
		}
		for (const key of Object.keys(body.content)) {
			expect(
				TEXT_FORM_FIELDS,
				`content carries ${key}, which the text form does not own`,
			).toContain(key)
		}

		await expect(dialog).toHaveCount(0, { timeout: 10_000 })
		const stored = (await placementsOf(admin, dashboard!.id)).filter(
			(p) => p.widgetId === 'text',
		)
		expect(stored).toHaveLength(1)
		expect(stored[0].content).toMatchObject({ text: 'Hello', fontSize: '16px' })
	})
})

test.describe('REQ-WDG-010 the widget picker', () => {
	/**
	 * Open the picker and return the Nextcloud-widget rows (the sections
	 * below them hold catalogue apps, office tiles and discovered apps).
	 *
	 * @param page the page.
	 * @return the rows.
	 */
	async function openPicker(page: Page): Promise<Locator> {
		await moreMenu(page, 'add-widget')
		const picker = page.locator('.widget-picker')
		await expect(picker).toBeVisible({ timeout: 15_000 })
		return picker.locator('.widget-picker__list > button.widget-picker__widget')
	}

	// @e2e widgets::widget-picker-displays-available-widgets
	test('the picker lists every available widget, each with its icon and title', async ({
		page,
	}) => {
		await openLaunchPad(page)
		const rows = await openPicker(page)
		await expect(rows).toHaveCount(catalog.length)

		for (const widget of catalog) {
			const row = rows
				.filter({
					has: page.locator('.widget-picker__widget-title', {
						hasText: widget.title,
					}),
				})
				.first()
			await expect(row).toBeVisible()
			if (widget.iconUrl) {
				await expect(
					row.locator('img.widget-picker__widget-icon'),
				).toHaveAttribute('src', widget.iconUrl)
			} else {
				await expect(
					row.locator('span.widget-picker__widget-icon'),
				).toHaveCount(1)
			}
		}
	})

	// @e2e widgets::widget-picker-filters-installed-widgets
	test('a widget already on the dashboard is still listed and can be added again', async ({
		page,
	}) => {
		const widget = catalog[0]
		await seedPlacement(admin, dashboard!.id, {
			widgetId: widget.id,
			gridX: 0,
			gridY: 0,
			gridWidth: 4,
			gridHeight: 4,
		})

		await openLaunchPad(page)
		const rows = await openPicker(page)
		await expect(rows).toHaveCount(catalog.length)
		const placed = rows
			.filter({
				has: page.locator('.widget-picker__widget-title', {
					hasText: widget.title,
				}),
			})
			.first()
		await expect(placed).toContainText('Already added')

		const created = page.waitForResponse(
			(res) =>
				res.request().method() === 'POST'
				&& new URL(res.url()).pathname.endsWith(
					`/api/dashboard/${dashboard!.id}/widgets`,
				),
		)
		await placed.click()
		expect((await created).status()).toBe(201)
		const stored = (await placementsOf(admin, dashboard!.id)).filter(
			(p) => p.widgetId === widget.id,
		)
		expect(stored).toHaveLength(2)
	})

	// @e2e widgets::widget-selection-creates-placement
	test('choosing a widget POSTs its id and the new placement lands on a free spot', async ({
		page,
	}) => {
		const existing = await seedPlacement(admin, dashboard!.id, {
			widgetId: catalog[0].id,
			gridX: 0,
			gridY: 0,
			gridWidth: 4,
			gridHeight: 4,
		})
		const pick =
			catalog.find((w) => w.id === 'weather_status')
			?? catalog[catalog.length - 1]

		await openLaunchPad(page)
		const rows = await openPicker(page)
		const row = rows
			.filter({
				has: page.locator('.widget-picker__widget-title', {
					hasText: pick.title,
				}),
			})
			.first()
		const created = page.waitForResponse(
			(res) =>
				res.request().method() === 'POST'
				&& new URL(res.url()).pathname.endsWith(
					`/api/dashboard/${dashboard!.id}/widgets`,
				),
		)
		await row.click()
		const response = await created
		expect(response.status()).toBe(201)
		expect(response.request().postDataJSON().widgetId).toBe(pick.id)

		const placement = (await response.json()) as Placement
		expect(placement.widgetId).toBe(pick.id)
		expect(
			overlaps(placement, existing),
			'the new widget was placed on top of the existing one',
		).toBe(false)
		const stored = await placementsOf(admin, dashboard!.id)
		expect(stored.find((p) => p.id === placement.id)).toMatchObject({
			gridX: placement.gridX,
			gridY: placement.gridY,
		})
		await expect(
			page.locator(`[data-testid="widget-placement-${placement.id}"]`),
		).toBeVisible()
	})
})

test.describe('REQ-WDG-011 the style editor', () => {
	// @e2e widgets::style-editor-opens-for-a-widget
	test('the edit cog of a Nextcloud widget opens the style editor with its current style', async ({
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
		const styled = await admin.put(`${API}/widgets/${placement.id}`, {
			data: {
				customTitle: `${STAMP} styled`,
				styleConfig: { backgroundColor: '#336699' },
			},
		})
		expect(styled.status(), await styled.text()).toBe(200)

		await openLaunchPad(page)
		await enterEditMode(page)
		await editViaCog(page, placement.id)

		const editor = page.locator('[data-testid="cn-widget-style-editor"]')
		await expect(editor).toBeVisible({ timeout: 10_000 })
		await expect(
			editor.getByRole('textbox', { name: 'Custom title' }),
		).toHaveValue(`${STAMP} styled`)
		await expect(
			editor.locator('[data-testid="cn-widget-style-color"]'),
		).toContainText('#336699')
	})

	// @e2e widgets::style-editor-supports-background-and-border-options
	test.fixme('the style editor offers background, border style/width/colour/radius, padding and header colours', async ({
		page,
	}) => {
		// SUSPECTED DEFECT: CnWidgetStyleEditorModal
		// (node_modules/@conduction/nextcloud-vue/src/dialogs/CnWidgetStyleEditorModal.vue:6-100)
		// edits only the title, the background colour and the icon. There is
		// no border, radius, padding or header-colour control, although
		// CnWidgetWrapper renders all of them from styleConfig.
		const widget = stockWidget()
		const placement = await seedPlacement(admin, dashboard!.id, {
			widgetId: widget.id,
			gridX: 0,
			gridY: 0,
			gridWidth: 4,
			gridHeight: 4,
		})
		await openLaunchPad(page)
		await enterEditMode(page)
		await editViaCog(page, placement.id)

		const editor = page.locator('[data-testid="cn-widget-style-editor"]')
		await expect(editor).toBeVisible({ timeout: 10_000 })
		await expect(
			editor.locator('[data-testid="cn-widget-style-color"]'),
		).toBeVisible()
		await expect(editor.getByLabel(/border style/i)).toBeVisible()
		for (const style of ['None', 'Solid', 'Dashed', 'Dotted']) {
			await editor.getByLabel(/border style/i).click()
			await expect(page.getByRole('option', { name: style })).toBeVisible()
			await page.keyboard.press('Escape')
		}
		await expect(editor.getByLabel(/border width/i)).toBeVisible()
		await expect(editor.getByLabel(/border colou?r/i)).toBeVisible()
		await expect(editor.getByLabel(/border radius/i)).toBeVisible()
		for (const side of ['top', 'right', 'bottom', 'left']) {
			await expect(
				editor.getByLabel(new RegExp(`padding.*${side}`, 'i')),
			).toBeVisible()
		}
		await expect(editor.getByLabel(/header background/i)).toBeVisible()
		await expect(editor.getByLabel(/header text/i)).toBeVisible()
	})

	// @e2e widgets::style-changes-saved-via-api
	test('saving a new background PUTs the styleConfig and the widget shows it at once', async ({
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

		await openLaunchPad(page)
		await enterEditMode(page)
		await editViaCog(page, placement.id)
		const editor = page.locator('[data-testid="cn-widget-style-editor"]')
		await expect(editor).toBeVisible({ timeout: 10_000 })

		// The colour picker offers a palette, not a free hex field, so the
		// colour is a palette swatch and the assertion uses whatever it is.
		await editor.locator('[data-testid="cn-widget-style-color"]').click()
		await page.locator('.color-picker__simple-color-circle').first().click()
		await page.getByRole('button', { name: 'Choose', exact: true }).click()
		const picked = (
			await editor.locator('[data-testid="cn-widget-style-color"]').innerText()
		).trim()
		expect(picked).toMatch(/^#[0-9a-f]{6}$/i)

		const put = page.waitForRequest(
			(req) =>
				req.method() === 'PUT'
				&& new URL(req.url()).pathname.endsWith(
					`/api/widgets/${placement.id}`,
				),
		)
		await page.locator('[data-testid="cn-widget-style-save"]').click()
		const body = (await put).postDataJSON() as {
			styleConfig: Record<string, unknown>
		}
		expect(String(body.styleConfig.backgroundColor).toLowerCase()).toBe(
			picked.toLowerCase(),
		)

		// Immediately, without a reload.
		const wrapper = page
			.locator(
				`[data-testid="widget-placement-${placement.id}"] .cn-widget-wrapper`,
			)
			.first()
		await expect
			.poll(() =>
				wrapper.evaluate((el) => (el as HTMLElement).style.backgroundColor),
			)
			.toBe(rgb(picked))
		const stored = (await placementsOf(admin, dashboard!.id)).find(
			(p) => p.id === placement.id,
		)
		expect(String(stored?.styleConfig.backgroundColor).toLowerCase()).toBe(
			picked.toLowerCase(),
		)
	})
})

test.describe('REQ-WDG-012 per-type validation', () => {
	// @e2e widgets::required-field-empty-disables-submit
	test('with the required text empty the Add button is disabled and says why', async ({
		page,
	}) => {
		await openLaunchPad(page)
		const dialog = await openCustomModal(page)
		await dialog.locator(TYPE_SELECT).selectOption('text')
		await expect(
			dialog.locator('[data-testid="cn-markdown-textarea"]'),
		).toHaveValue('')
		await expect(page.locator(PRIMARY)).toBeDisabled()
		await expect(page.locator(PRIMARY)).toHaveAttribute(
			'title',
			'Text is required',
		)
	})

	// @e2e widgets::filling-required-field-enables-submit
	test('typing the required text enables the Add button', async ({ page }) => {
		await openLaunchPad(page)
		const dialog = await openCustomModal(page)
		await dialog.locator(TYPE_SELECT).selectOption('text')
		await expect(page.locator(PRIMARY)).toBeDisabled()
		await dialog.locator('[data-testid="cn-markdown-textarea"]').fill('Hello')
		await expect(page.locator(PRIMARY)).toBeEnabled()
	})
})

test.describe('REQ-WDG-013 modal close discipline', () => {
	/**
	 * Open the modal with a valid text form, the Add button enabled.
	 *
	 * @param page the page.
	 * @return the dialog.
	 */
	async function validModal(page: Page): Promise<Locator> {
		await openLaunchPad(page)
		const dialog = await openCustomModal(page)
		await fillText(dialog, `${STAMP} valid`)
		await expect(page.locator(PRIMARY)).toBeEnabled()
		return dialog
	}

	// @e2e widgets::backdrop-click-closes-without-submit
	test.fixme('a click on the backdrop closes the modal and adds nothing', async ({
		page,
	}) => {
		// SUSPECTED DEFECT: CnAddWidgetModal renders NcDialog without
		// `closeOnClickOutside`
		// (node_modules/@conduction/nextcloud-vue/src/dialogs/CnAddWidgetModal.vue:7-11),
		// and NcModal closes on a wrapper click only when it is set
		// (@nextcloud/vue dist/chunks/NcModal.mjs:212-213).
		const dialog = await validModal(page)
		const writes = placementWrites(page)
		await page.mouse.click(5, 5)
		await expect(dialog).toHaveCount(0, { timeout: 5_000 })
		expect(writes).toEqual([])
		expect(await placementsOf(admin, dashboard!.id)).toHaveLength(0)
	})

	// @e2e widgets::esc-key-closes-modal
	test('Escape closes the modal and focus goes back to the menu that opened it', async ({
		page,
	}) => {
		const dialog = await validModal(page)
		const writes = placementWrites(page)
		await page.keyboard.press('Escape')
		await expect(dialog).toHaveCount(0, { timeout: 5_000 })
		await expect(
			page.locator(`${MORE_MENU} [data-testid="cn-grouped-menu-trigger"]`),
		).toBeFocused()
		expect(writes).toEqual([])
		expect(await placementsOf(admin, dashboard!.id)).toHaveLength(0)
	})

	// @e2e widgets::cancel-button-closes-without-submit
	test('Cancel closes the modal and adds nothing', async ({ page }) => {
		const dialog = await validModal(page)
		const writes = placementWrites(page)
		await dialog.getByRole('button', { name: 'Cancel', exact: true }).click()
		await expect(dialog).toHaveCount(0, { timeout: 5_000 })
		expect(writes).toEqual([])
		expect(await placementsOf(admin, dashboard!.id)).toHaveLength(0)

		// Reopening starts from defaults, not from what was typed.
		const again = await openCustomModal(page)
		await again.locator(TYPE_SELECT).selectOption('text')
		await expect(
			again.locator('[data-testid="cn-markdown-textarea"]'),
		).toHaveValue('')
	})
})
