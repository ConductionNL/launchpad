/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for ADDING and EDITING a quicklinks widget
 * (openspec/specs/quicklinks-widget), written for e2e-coverage-programme.
 *
 * Everything a person does goes through the product's own controls: the
 * dashboard's row menu -> "Add custom widget" -> the Add Widget modal's type
 * picker and the quicklinks sub-form, or edit mode -> the widget's cog ->
 * "Edit widget". The API is used to seed placements and to read back what a
 * save actually persisted, never to perform the action under test.
 *
 * Two scenarios ask for a server-side refusal (HTTP 400) of a dangerous URL.
 * The form never sends one (its Add button stays disabled), so those tests
 * assert the form's inline refusal AND post the same payload to the
 * placement endpoint directly, the way any other client could.
 *
 * Gate-19 @e2e traceability:
 *   @e2e quicklinks-widget::widget-registered-and-appears-in-picker
 *   @e2e quicklinks-widget::default-sizing-is-compact
 *   @e2e quicklinks-widget::default-widget-size-is-compact
 *   @e2e quicklinks-widget::new-widget-receives-all-defaults
 *   @e2e quicklinks-widget::config-round-trip-preserves-all-fields
 *   @e2e quicklinks-widget::valid-http-url-accepted
 *   @e2e quicklinks-widget::relative-nextcloud-url-accepted
 *   @e2e quicklinks-widget::javascript-protocol-rejected
 *   @e2e quicklinks-widget::data-url-rejected
 *   @e2e quicklinks-widget::empty-url-rejected
 *   @e2e quicklinks-widget::clicking-empty-state-affordance-opens-edit-form
 *
 * @spec openspec/specs/quicklinks-widget/spec.md
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, test } from '@playwright/test'
import { ensureDefaultWidgetRestriction } from './fixtures/role-feature-permissions.ts'
import { removeSeededDashboard, seedActiveDashboard } from './support/dashboardFixture.ts'
import { enterEditMode, MORE_MENU } from './support/dashboardSwitcher.ts'
import {
	adminApi,
	API,
	clearPlacements,
	formSelect,
	openAddQuicklinks,
	openEditForm,
	openWidget,
	QUICKLINKS_DEFAULTS,
	quicklinksDialog,
	quicklinksOn,
	quicklinksWidget,
	resolveColor,
	seedQuicklinks,
} from './support/quicklinks.ts'

const SAVE = '[data-testid="add-widget-save"]'
const ROW = '.cn-quicklinks-widget-form__row'
const INVALID = 'cn-quicklinks-widget-form__input--invalid'

let api: APIRequestContext
let dashboard: SeededDashboard | null = null

test.setTimeout(120_000)

test.beforeAll(async () => {
	await ensureDefaultWidgetRestriction()
	api = await adminApi()
	dashboard = await seedActiveDashboard(api, `E2E Quicklinks form ${Date.now()}`)
})

test.afterEach(async () => {
	await clearPlacements(api, dashboard?.id ?? 0)
})

test.afterAll(async () => {
	await removeSeededDashboard(api, dashboard)
	await api?.dispose()
})

/**
 * Open LaunchPad and the Add Widget modal with Quicklinks picked.
 *
 * @param page the page.
 * @return the dialog.
 */
async function startAdding(page: Page): Promise<Locator> {
	await page.goto('/index.php/apps/launchpad/')
	await expect(page.locator(MORE_MENU)).toBeVisible({ timeout: 30_000 })
	return openAddQuicklinks(page, dashboard!.name)
}

/**
 * Append a link row through the form's own "Add link" button and fill it.
 *
 * @param dialog the dialog.
 * @param label the label to type.
 * @param url the URL to type ('' leaves it empty).
 * @return the URL input of the new row.
 */
async function addLinkRow(dialog: Locator, label: string, url: string): Promise<Locator> {
	const before = await dialog.locator(ROW).count()
	await dialog.locator('.cn-quicklinks-widget-form__add').click()
	await expect(dialog.locator(ROW)).toHaveCount(before + 1)
	const row = dialog.locator(ROW).nth(before)
	await row.getByPlaceholder('Label').fill(label)
	const input = row.getByPlaceholder('https://...')
	if (url !== '') {
		await input.fill(url)
	}
	return input
}

/**
 * Wait for the Add/Edit modal to close and for exactly one quicklinks
 * placement to be readable from the server.
 *
 * @param dialog the dialog that was submitted.
 * @return the persisted placement.
 */
async function savedPlacement(dialog: Locator) {
	await expect(dialog, 'the modal closes on a successful save').toBeHidden({ timeout: 15_000 })
	await expect.poll(async () => (await quicklinksOn(api, dashboard!.id)).length, {
		message: 'one quicklinks placement is persisted',
		timeout: 15_000,
	}).toBe(1)
	return (await quicklinksOn(api, dashboard!.id))[0]
}

/**
 * The URL the form accepts end to end: no invalid styling, Add enabled, the
 * saved placement carries it verbatim, and the rendered link points at it.
 *
 * @param page the page.
 * @param url the URL.
 * @return nothing.
 */
async function expectUrlAccepted(page: Page, url: string): Promise<void> {
	const dialog = await startAdding(page)
	const input = await addLinkRow(dialog, 'Target', url)

	await expect(input, 'no red border').not.toHaveClass(new RegExp(INVALID))
	await expect(dialog.locator('.cn-quicklinks-widget-form__inline-error'), 'no error text').toHaveCount(0)
	const save = dialog.locator(SAVE)
	await expect(save, 'the form may be submitted').toBeEnabled()
	await expect(save).not.toHaveAttribute('title', /\S/)
	await save.click()

	const placement = await savedPlacement(dialog)
	const links = placement.content.links as Array<Record<string, unknown>>
	expect(links).toHaveLength(1)
	expect(links[0].url, 'saved exactly as typed').toBe(url)
	expect(links[0].label).toBe('Target')

	const rendered = quicklinksWidget(page, placement.id).locator('a.cn-quicklinks-widget__link')
	await expect(rendered).toHaveAttribute('href', url, { timeout: 15_000 })
}

/**
 * The URL the form and the server both refuse.
 *
 * @param page the page.
 * @param urls the dangerous URLs to try.
 * @return nothing.
 */
async function expectUrlRejected(page: Page, urls: string[]): Promise<void> {
	const dialog = await startAdding(page)
	const input = await addLinkRow(dialog, 'Danger', urls[0])
	const error = await resolveColor(input, 'var(--color-error, #d32f2f)')

	for (const url of urls) {
		await input.fill(url)
		await expect(input, `${url} is flagged as invalid`).toHaveClass(new RegExp(INVALID))
		const border = await input.evaluate((el) => getComputedStyle(el).borderTopColor)
		expect(border, `${url} gets a red border`).toBe(error)
		const message = dialog.locator(ROW).first().locator('.cn-quicklinks-widget-form__inline-error')
		await expect(message, `${url} shows the inline error`).toBeVisible()
		await expect(message).toHaveText('Invalid URL')
		await expect(dialog.locator(SAVE), `${url} cannot be submitted`).toBeDisabled()
	}
	await page.keyboard.press('Escape')
	await expect(dialog).toBeHidden({ timeout: 10_000 })

	for (const url of urls) {
		const res = await api.post(`${API}/dashboard/${dashboard!.id}/widgets`, {
			data: {
				widgetId: 'quicklinks',
				gridX: 0,
				gridY: 0,
				gridWidth: 4,
				gridHeight: 2,
				content: { ...QUICKLINKS_DEFAULTS, links: [{ label: 'Danger', url, icon: '' }] },
			},
		})
		expect(res.status(), `the server refuses ${url}: ${await res.text()}`).toBe(400)
		expect((await res.json()).error).toBe('Invalid URL')
	}
	const stored = (await quicklinksOn(api, dashboard!.id))
		.flatMap((p) => (p.content.links as Array<Record<string, unknown>> | undefined) ?? [])
		.filter((l) => urls.includes(String(l.url)))
	expect(stored, 'no dangerous URL was persisted').toEqual([])
}

test.describe('quicklinks widget: registration and sizing (REQ-QLNK-001, REQ-QLNK-011)', () => {
	// @e2e quicklinks-widget::widget-registered-and-appears-in-picker
	test('the type picker offers Quicklinks and adding it saves the default config', async ({ page }) => {
		// openAddQuicklinks asserts the picker entry reads "Quicklinks".
		const dialog = await startAdding(page)
		const save = dialog.locator(SAVE)
		await expect(save, 'the defaults are a valid widget').toBeEnabled()
		await save.click()

		const placement = await savedPlacement(dialog)
		expect(placement.content, 'the new placement carries the default config').toEqual(QUICKLINKS_DEFAULTS)
		await expect(
			quicklinksWidget(page, placement.id).locator('.cn-quicklinks-widget__empty'),
			'and renders on the dashboard',
		).toBeVisible({ timeout: 15_000 })
	})

	// @e2e quicklinks-widget::default-sizing-is-compact
	// @e2e quicklinks-widget::default-widget-size-is-compact
	test('a newly added quicklinks widget is 4 columns by 2 rows and resizable', async ({ page }) => {
		const dialog = await startAdding(page)
		await dialog.locator(SAVE).click()
		const placement = await savedPlacement(dialog)

		expect.soft(placement.gridWidth, 'default gridWidth').toBe(4)
		expect.soft(placement.gridHeight, 'default gridHeight').toBe(2)

		await expect(quicklinksWidget(page, placement.id)).toBeVisible({ timeout: 15_000 })
		await enterEditMode(page)
		const item = page
			.locator('.grid-stack-item')
			.filter({ has: page.locator(`[data-testid="widget-placement-${placement.id}"]`) })
		await expect(item).toHaveAttribute('gs-w', '4')
		await expect(
			item.locator('.ui-resizable-handle, .gs-resize-handle').first(),
			'edit mode offers a resize handle on the widget',
		).toBeAttached({ timeout: 10_000 })
	})
})

test.describe('quicklinks widget: configuration shape (REQ-QLNK-002)', () => {
	// @e2e quicklinks-widget::new-widget-receives-all-defaults
	test('content with only links renders with every default and the form pre-fills them', async ({ page }) => {
		const empty = await seedQuicklinks(api, dashboard!.id, { links: [] }, { gridY: 0 })
		const one = await seedQuicklinks(
			api,
			dashboard!.id,
			{ links: [{ label: 'Docs', url: 'https://example.com', icon: 'Star' }] },
			{ gridY: 4 },
		)

		// The renderer applies the defaults to a bare blob ...
		const root = await openWidget(page, empty)
		await expect(root).toHaveClass(/cn-quicklinks-widget--hover-lift/)
		await expect(root).toHaveClass(/cn-quicklinks-widget--bg-transparent/)
		await expect(root).toHaveClass(/cn-quicklinks-widget--shape-rounded/)
		const vars = await root.evaluate((el) => ({
			size: getComputedStyle(el).getPropertyValue('--cn-quicklinks-icon-size').trim(),
			radius: getComputedStyle(el).getPropertyValue('--cn-quicklinks-icon-radius').trim(),
		}))
		expect(vars).toEqual({ size: '48px', radius: '8px' })

		// ... and to a link rendered from one.
		const withLink = quicklinksWidget(page, one)
		await withLink.scrollIntoViewIfNeeded()
		await expect(withLink).toHaveClass(/cn-quicklinks-widget--hover-lift/)
		const icon = withLink.locator('.cn-quicklinks-widget__icon').first()
		const box = (await icon.boundingBox())!
		expect(box.width, 'iconSize medium').toBeCloseTo(48, 0)
		expect(await icon.evaluate((el) => getComputedStyle(el).borderTopLeftRadius), 'iconShape rounded').toBe('8px')
		await expect(withLink.locator('.cn-quicklinks-widget__label--below'), 'showLabels + labelPosition below').toBeVisible()
		expect(
			await withLink.locator('.cn-quicklinks-widget__list').evaluate((el) => getComputedStyle(el).flexWrap),
			'columns auto',
		).toBe('wrap')

		// The form pre-fills every field with its default.
		const dialog = await openEditForm(page, empty)
		const expected: Record<string, string> = {
			'Icon size': 'Medium',
			'Icon shape': 'Rounded',
			'Label position': 'Below',
			Columns: 'Auto',
			'Tile background': 'Transparent',
			'Hover effect': 'Lift',
		}
		for (const [label, value] of Object.entries(expected)) {
			await expect(formSelect(dialog, label).locator('.vs__selected'), `${label} pre-fills`).toHaveText(value)
		}
		await expect(dialog.getByRole('checkbox', { name: 'Show labels' })).toBeChecked()
		await expect(dialog.locator(ROW), 'no links yet').toHaveCount(0)
	})

	// @e2e quicklinks-widget::config-round-trip-preserves-all-fields
	test('opening and saving the form without touching its fields persists all eight exactly', async ({ page }) => {
		const content = {
			links: [{ label: 'Docs', url: 'https://docs.example.com', icon: 'Star' }],
			iconSize: 'large',
			iconShape: 'circle',
			showLabels: false,
			labelPosition: 'overlay',
			columns: 6,
			tileBackgroundStyle: 'solid',
			hoverEffect: 'fade',
		}
		const id = await seedQuicklinks(api, dashboard!.id, content)
		await openWidget(page, id)

		const dialog = await openEditForm(page, id)
		const expected: Record<string, string> = {
			'Icon size': 'Large',
			'Icon shape': 'Circle',
			'Label position': 'Overlay',
			Columns: '6',
			'Tile background': 'Solid',
			'Hover effect': 'Fade',
		}
		for (const [label, value] of Object.entries(expected)) {
			await expect(formSelect(dialog, label).locator('.vs__selected'), `${label} loads the saved value`).toHaveText(value)
		}
		await expect(dialog.getByRole('checkbox', { name: 'Show labels' })).not.toBeChecked()
		await expect(dialog.locator(ROW)).toHaveCount(1)
		await expect(dialog.locator(ROW).getByPlaceholder('Label')).toHaveValue('Docs')
		await expect(dialog.locator(ROW).getByPlaceholder('https://...')).toHaveValue('https://docs.example.com')

		// The modal only enables Save once something changed, so change the
		// widget's chrome (its title switch) and nothing in the quicklinks form.
		await dialog.getByText('Show title', { exact: true }).click()
		const save = dialog.locator(SAVE)
		await expect(save).toBeEnabled()
		await save.click()
		await expect(dialog).toBeHidden({ timeout: 15_000 })

		await expect.poll(async () => (await quicklinksOn(api, dashboard!.id))[0]?.content, {
			message: 'every field persists exactly, no default overrides a choice',
			timeout: 15_000,
		}).toEqual(content)
	})
})

test.describe('quicklinks widget: URL validation (REQ-QLNK-008)', () => {
	// @e2e quicklinks-widget::valid-http-url-accepted
	test('an https URL with a query string is accepted and saved', async ({ page }) => {
		await expectUrlAccepted(page, 'https://example.com/page?param=value')
	})

	// @e2e quicklinks-widget::relative-nextcloud-url-accepted
	test('a relative Nextcloud URL is accepted and saved', async ({ page }) => {
		await expectUrlAccepted(page, '/apps/files/')
	})

	// @e2e quicklinks-widget::javascript-protocol-rejected
	test('a javascript: URL is flagged inline and refused by the server with 400', async ({ page }) => {
		await expectUrlRejected(page, ['javascript:void(0)', 'javascript:alert("xss")'])
	})

	// @e2e quicklinks-widget::data-url-rejected
	test('a data: URL is flagged inline and refused by the server with 400', async ({ page }) => {
		await expectUrlRejected(page, ['data:text/html,<img src=x onerror=alert(1)>'])
	})

	// @e2e quicklinks-widget::empty-url-rejected
	test('a link with an empty or null URL shows an error and is never saved', async ({ page }) => {
		// url: '' typed into a new link row.
		const dialog = await startAdding(page)
		await addLinkRow(dialog, 'No address', '')
		const save = dialog.locator(SAVE)
		await expect(save, 'an empty URL cannot be submitted').toBeDisabled()
		await expect(save, 'and the reason is shown on the submit control').toHaveAttribute(
			'title',
			'Invalid URL in one or more links',
		)
		await page.keyboard.press('Escape')
		await expect(dialog).toBeHidden({ timeout: 10_000 })
		expect(await quicklinksOn(api, dashboard!.id), 'nothing was saved').toEqual([])

		// url: null on an existing placement: the form validates it the same
		// way and will not save it back, even once something else changed.
		const id = await seedQuicklinks(api, dashboard!.id, { links: [{ label: 'Null', url: null, icon: '' }] })
		await openWidget(page, id)
		const edit = await openEditForm(page, id)
		await edit.getByText('Show title', { exact: true }).click()
		await expect(edit.locator(SAVE), 'a null URL cannot be saved').toBeDisabled()
		await expect(edit.locator(SAVE)).toHaveAttribute('title', 'Invalid URL in one or more links')
	})
})

test.describe('quicklinks widget: empty state affordance (REQ-QLNK-011)', () => {
	// @e2e quicklinks-widget::clicking-empty-state-affordance-opens-edit-form
	test('clicking the empty state gear opens the edit form with an empty links table', async ({ page }) => {
		const id = await seedQuicklinks(api, dashboard!.id, { links: [] })
		const widget = await openWidget(page, id)
		await enterEditMode(page)

		await widget.locator('.cn-quicklinks-widget__empty').getByRole('button', { name: 'Open quicklinks settings' }).click()

		const dialog = quicklinksDialog(page)
		await expect(dialog, 'the gear opens the widget edit form').toBeVisible({ timeout: 10_000 })
		await expect(dialog.locator(SAVE), 'in edit mode, not a new widget').toHaveText('Save')
		await expect(dialog.locator('.cn-quicklinks-widget-form__table')).toBeVisible()
		await expect(dialog.locator(ROW), 'the links table is empty').toHaveCount(0)
		await expect(dialog.locator('.cn-quicklinks-widget-form__add'), 'ready to add a link').toBeVisible()
	})
})
