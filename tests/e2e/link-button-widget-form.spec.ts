/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the link-button widget's add/edit form
 * (openspec/specs/link-button-widget, REQ-LBN-006, REQ-LBLM-001, 002, 006,
 * 007 and 009), written for the e2e-coverage-programme change.
 *
 * New widgets are made the way a person makes them: the dashboard row's cog,
 * "Add widget" → "Custom widget", the Link Button type. Existing ones are opened with
 * right-click, Edit. What the form saved is read back from the API, because
 * a form that looks right and saves something else is the defect these
 * scenarios are about.
 *
 * Gate-19 @e2e traceability:
 *   @e2e link-button-widget::validation-requires-both-label-and-url
 *   @e2e link-button-widget::placeholder-swaps-with-actiontype
 *   @e2e link-button-widget::display-mode-field-exists-with-button-default
 *   @e2e link-button-widget::display-mode-can-be-set-to-list
 *   @e2e link-button-widget::links-array-stored-on-placement
 *   @e2e link-button-widget::display-mode-toggle-in-edit-form
 *   @e2e link-button-widget::add-link-button-appends-to-array
 *   @e2e link-button-widget::edit-link-in-its-row
 *   @e2e link-button-widget::remove-link-deletes-from-array
 *   @e2e link-button-widget::reorder-list-items
 *   @e2e link-button-widget::list-mode-requires-non-empty-links-array
 *   @e2e link-button-widget::each-link-requires-label-and-url
 *   @e2e link-button-widget::edit-form-detects-legacy-format
 *   @e2e link-button-widget::upgrade-from-button-to-list-preserves-existing-link
 *
 * @spec openspec/specs/link-button-widget/spec.md
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, test } from '@playwright/test'
import { gotoLaunchPad } from './fixtures/widget-flow.ts'
import { removeSeededDashboard, seedActiveDashboard } from './support/dashboardFixture.ts'
import { saveEditDraft } from './support/dashboardSwitcher.ts'
import {
	ACTION,
	adminApi,
	DISPLAY,
	expectSelected,
	openAddLinkForm,
	openEditForm,
	openWithWidget,
	pickOption,
	readPlacement,
	removePlacements,
	seedLink,
} from './support/linkButton.ts'

const RUN = Date.now()
const NAME = `E2E Link Form ${RUN}`
const ROW = '.cn-link-button-widget-form__link-row'
const SAVE = '[data-testid="add-widget-save"]'

let api: APIRequestContext
let dashboard: SeededDashboard | null = null
const seeded: number[] = []

test.beforeAll(async () => {
	api = await adminApi()
	dashboard = await seedActiveDashboard(api, NAME)
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
 * @return the placement id.
 */
async function place(content: Record<string, unknown>): Promise<number> {
	const id = await seedLink(api, dashboard!.id, content, { gridHeight: 4 })
	seeded.push(id)
	return id
}

/** A list placement's links, labelled as given. */
function linksNamed(...labels: string[]): Array<Record<string, string>> {
	return labels.map((label) => ({
		label,
		url: `https://example.com/${label.toLowerCase().replace(/\W+/g, '-')}`,
		actionType: 'external',
		icon: '',
		value: '',
	}))
}

/** The top-level (single-link) text field of the form, by its label. */
function topField(dialog: Locator, label: string): Locator {
	return dialog
		.locator('.cn-link-button-widget-form > .input-field')
		.getByLabel(label, { exact: true })
}

/** The list editor's rows. */
function rows(dialog: Locator): Locator {
	return dialog.locator(ROW)
}

/** The label values of every list editor row, in order. */
async function rowLabels(dialog: Locator): Promise<string[]> {
	const out: string[] = []
	const all = rows(dialog)
	for (let i = 0; i < (await all.count()); i++) {
		out.push(await all.nth(i).getByLabel('Label', { exact: true }).inputValue())
	}
	return out
}

/**
 * Click the modal's primary button, then Save the edit-mode draft: a new
 * widget and an edit both land in the draft and are stored by the edit bar's
 * Save (REQ-EDIT-001/002).
 *
 * @param page the page.
 * @param dialog the Add/Edit Widget dialog.
 * @param method POST for a new widget, PUT for an edit.
 * @return the placement id (for a new widget; NaN for an edit) and the dashboard it landed on.
 */
async function submit(
	page: Page,
	dialog: Locator,
	method: 'POST' | 'PUT',
): Promise<{ placementId: number; dashboardId: number }> {
	await expect(dialog.locator(SAVE)).toBeEnabled()
	await dialog.locator(SAVE).click()
	await expect(dialog).toBeHidden({ timeout: 10_000 })
	const saved = page.waitForResponse((r) => r.request().method() === 'PUT'
		&& /\/api\/dashboard\/\d+$/.test(new URL(r.url()).pathname)
		&& r.request().postDataJSON()?.draft === true)
	expect(await saveEditDraft(page)).toBeLessThan(300)
	const res = await saved
	const dashboardId = Number(/dashboard\/(\d+)$/.exec(new URL(res.url()).pathname)![1])
	if (method === 'PUT') {
		return { placementId: Number.NaN, dashboardId }
	}
	const sent = res.request().postDataJSON() as { placements: Array<{ id?: number }> }
	const kept = new Set(sent.placements.filter((p) => p.id !== undefined).map((p) => Number(p.id)))
	const body = await res.json()
	const stored = ((body.data ?? body).placements ?? []) as Array<{ id: number }>
	const placementId = Number(stored.find((p) => !kept.has(Number(p.id)))?.id)
	seeded.push(placementId)
	return { placementId, dashboardId }
}

test.describe('link button form: fields (REQ-LBN-006)', () => {
	test.beforeEach(async ({ page }) => {
		await gotoLaunchPad(page)
	})

	// @e2e link-button-widget::validation-requires-both-label-and-url
	test('a label without a URL fails validation and keeps Add disabled', async ({ page }) => {
		const dialog = await openAddLinkForm(page, NAME)
		const add = dialog.locator(SAVE)
		await topField(dialog, 'Label').fill('X')
		await expect(topField(dialog, 'URL')).toHaveValue('')

		await expect(add).toBeDisabled()
		// The modal surfaces validate()'s first error on the button.
		await expect(add).toHaveAttribute('title', 'URL is required')

		// A URL as well makes the form valid, so the refusal was the URL's.
		await topField(dialog, 'URL').fill('https://example.com')
		await expect(add).toBeEnabled()
		await expect(add).not.toHaveAttribute('title', /required/)

		// And the label is required in the same way.
		await topField(dialog, 'Label').fill('')
		await expect(add).toBeDisabled()
		await expect(add).toHaveAttribute('title', 'Label is required')
	})

	// @e2e link-button-widget::placeholder-swaps-with-actiontype
	test('the URL placeholder follows the action type', async ({ page }) => {
		const dialog = await openAddLinkForm(page, NAME)
		const url = topField(dialog, 'URL')
		await expect(url).toHaveAttribute('placeholder', 'https://...')

		await pickOption(page, dialog, 'Action type', ACTION.createFile)
		await expectSelected(dialog, 'Action type', 'Create file')
		await expect(url).toHaveAttribute('placeholder', 'docx')

		await pickOption(page, dialog, 'Action type', ACTION.external)
		await expect(url).toHaveAttribute('placeholder', 'https://...')
	})
})

test.describe('link button form: display mode (REQ-LBLM-001, 002)', () => {
	test.beforeEach(async ({ page }) => {
		await gotoLaunchPad(page)
	})

	// @e2e link-button-widget::display-mode-field-exists-with-button-default
	test('a widget added without touching display mode is saved as a button', async ({ page }) => {
		const dialog = await openAddLinkForm(page, NAME)
		await expectSelected(dialog, 'Display mode', 'Single button')
		await topField(dialog, 'Label').fill(`Default mode ${RUN}`)
		await topField(dialog, 'URL').fill('https://example.com/default')

		const { placementId, dashboardId } = await submit(page, dialog, 'POST')
		const read = await readPlacement(api, dashboardId, placementId)
		expect(read.widgetId).toBe('link')
		expect(read.content.displayMode).toBe('button')
		expect(read.content).toMatchObject({ label: `Default mode ${RUN}`, url: 'https://example.com/default' })
	})

	// @e2e link-button-widget::display-mode-can-be-set-to-list
	test('switching an existing button to list mode and saving stores displayMode list', async ({ page }) => {
		const id = await place({ displayMode: 'button', label: 'Switch me', url: 'https://example.com/switch', actionType: 'external' })
		await openWithWidget(page, id)
		const dialog = await openEditForm(page, id)

		await pickOption(page, dialog, 'Display mode', DISPLAY.list)
		await expectSelected(dialog, 'Display mode', 'List of links')
		await submit(page, dialog, 'PUT')

		const read = await readPlacement(api, dashboard!.id, id)
		expect(read.content.displayMode).toBe('list')
		// And the dashboard now draws it as a list.
		await expect(page.locator(`[data-placement-id="${id}"] [role="list"]`)).toHaveCount(1)
	})

	// @e2e link-button-widget::links-array-stored-on-placement
	test('a list made in the form is read back with its whole links array', async ({ page }) => {
		const dialog = await openAddLinkForm(page, NAME)
		await pickOption(page, dialog, 'Display mode', DISPLAY.list)
		await expect(rows(dialog)).toHaveCount(1)
		await rows(dialog).nth(0).getByLabel('Label', { exact: true }).fill('Docs')
		await rows(dialog).nth(0).getByLabel('URL', { exact: true }).fill('https://example.com/docs')

		await dialog.getByRole('button', { name: 'Add link' }).click()
		await expect(rows(dialog)).toHaveCount(2)
		await rows(dialog).nth(1).getByLabel('Label', { exact: true }).fill('New report')
		await pickOption(page, rows(dialog).nth(1), 'Action type', ACTION.createFile)
		await rows(dialog).nth(1).getByLabel('URL', { exact: true }).fill('docx')
		await rows(dialog).nth(1).getByLabel('File extension', { exact: true }).fill('docx')

		const { placementId, dashboardId } = await submit(page, dialog, 'POST')
		const read = await readPlacement(api, dashboardId, placementId)
		expect(read.content.displayMode).toBe('list')
		expect(read.content.links).toEqual([
			{ label: 'Docs', url: 'https://example.com/docs', icon: '', actionType: 'external', value: '' },
			{ label: 'New report', url: 'docx', icon: '', actionType: 'createFile', value: 'docx' },
		])
	})
})

test.describe('link button form: list editor (REQ-LBLM-006)', () => {
	// @e2e link-button-widget::display-mode-toggle-in-edit-form
	test('toggling to list shows the list editor and hides the single-link fields', async ({ page }) => {
		const id = await place({ displayMode: 'button', label: 'Toggle', url: 'https://example.com/toggle', actionType: 'external' })
		await openWithWidget(page, id)
		const dialog = await openEditForm(page, id)
		const singleFields = dialog.locator('.cn-link-button-widget-form > .input-field')
		await expect(singleFields).toHaveCount(2)
		await expect(topField(dialog, 'Label')).toHaveValue('Toggle')
		await expect(dialog.locator('.cn-link-button-widget-form__list-editor')).toHaveCount(0)

		await pickOption(page, dialog, 'Display mode', DISPLAY.list)

		await expect(dialog.locator('.cn-link-button-widget-form__list-editor')).toBeVisible()
		await expect(singleFields).toHaveCount(0)
		await expect(dialog.getByRole('button', { name: 'Add link' })).toBeVisible()
	})

	// @e2e link-button-widget::add-link-button-appends-to-array
	test('Add link appends an empty entry as a row of its own', async ({ page }) => {
		const id = await place({ displayMode: 'list', links: linksNamed('First', 'Second') })
		await openWithWidget(page, id)
		const dialog = await openEditForm(page, id)
		await expect(rows(dialog)).toHaveCount(2)

		await dialog.getByRole('button', { name: 'Add link' }).click()

		await expect(rows(dialog)).toHaveCount(3)
		expect(await rowLabels(dialog)).toEqual(['First', 'Second', ''])
		// The new entry's details are filled in its own row.
		await expect(rows(dialog).nth(2).getByLabel('Label', { exact: true })).toHaveValue('')
		await expect(rows(dialog).nth(2).getByLabel('URL', { exact: true })).toHaveValue('')
		await expectSelected(rows(dialog).nth(2), 'Action type', 'External link')
	})

	// @e2e link-button-widget::edit-link-in-its-row
	test('the second row holds the second link, and a change there saves to it', async ({ page }) => {
		const links = linksNamed('Alpha', 'Bravo', 'Charlie')
		const id = await place({ displayMode: 'list', links })
		await openWithWidget(page, id)
		const dialog = await openEditForm(page, id)
		await expect(rows(dialog)).toHaveCount(3)

		const second = rows(dialog).nth(1)
		await expect(second.getByLabel('Label', { exact: true })).toHaveValue('Bravo')
		await expect(second.getByLabel('URL', { exact: true })).toHaveValue(links[1].url)
		await expectSelected(second, 'Action type', 'External link')
		// The widget's colour pickers apply to every item.
		await expect(dialog.getByText('Background color')).toBeVisible()
		await expect(dialog.getByText('Text color')).toBeVisible()

		await second.getByLabel('Label', { exact: true }).fill('Bravo two')
		await submit(page, dialog, 'PUT')
		const read = await readPlacement(api, dashboard!.id, id)
		expect((read.content.links as Array<{ label: string }>).map((l) => l.label)).toEqual(['Alpha', 'Bravo two', 'Charlie'])
	})

	// @e2e link-button-widget::remove-link-deletes-from-array
	test('Remove on the first link deletes it and the rest move up', async ({ page }) => {
		const id = await place({ displayMode: 'list', links: linksNamed('One', 'Two', 'Three') })
		await openWithWidget(page, id)
		const dialog = await openEditForm(page, id)
		await expect(rows(dialog)).toHaveCount(3)

		await rows(dialog).nth(0).getByRole('button', { name: 'Remove link' }).click()

		await expect(rows(dialog)).toHaveCount(2)
		expect(await rowLabels(dialog)).toEqual(['Two', 'Three'])
		await submit(page, dialog, 'PUT')
		const read = await readPlacement(api, dashboard!.id, id)
		expect((read.content.links as Array<{ label: string }>).map((l) => l.label)).toEqual(['Two', 'Three'])
	})

	// @e2e link-button-widget::reorder-list-items
	test('Move link up on B reorders the links to B, A, C', async ({ page }) => {
		const id = await place({ displayMode: 'list', links: linksNamed('A', 'B', 'C') })
		await openWithWidget(page, id)
		const dialog = await openEditForm(page, id)
		await expect(rows(dialog)).toHaveCount(3)
		// The first row cannot move up and the last cannot move down.
		await expect(rows(dialog).nth(0).getByRole('button', { name: 'Move link up' })).toBeDisabled()
		await expect(rows(dialog).nth(2).getByRole('button', { name: 'Move link down' })).toBeDisabled()

		await rows(dialog).nth(1).getByRole('button', { name: 'Move link up' }).click()

		await expect.poll(() => rowLabels(dialog)).toEqual(['B', 'A', 'C'])
		await submit(page, dialog, 'PUT')
		const read = await readPlacement(api, dashboard!.id, id)
		expect((read.content.links as Array<{ label: string }>).map((l) => l.label)).toEqual(['B', 'A', 'C'])
	})
})

test.describe('link button form: validation (REQ-LBLM-007)', () => {
	test.beforeEach(async ({ page }) => {
		await gotoLaunchPad(page)
	})

	// @e2e link-button-widget::list-mode-requires-non-empty-links-array
	test('a list with no links cannot be saved and says why', async ({ page }) => {
		const dialog = await openAddLinkForm(page, NAME)
		await pickOption(page, dialog, 'Display mode', DISPLAY.list)
		await expect(rows(dialog)).toHaveCount(1)
		await rows(dialog).nth(0).getByRole('button', { name: 'Remove link' }).click()
		await expect(rows(dialog)).toHaveCount(0)

		const add = dialog.locator(SAVE)
		await expect(add).toBeDisabled()
		await expect(add).toHaveAttribute('title', 'At least one link is required for list mode')

		let posts = 0
		page.on('request', (r) => {
			if (r.method() === 'POST' && /\/api\/dashboard\/\d+\/widgets$/.test(new URL(r.url()).pathname)) {
				posts += 1
			}
		})
		await add.click({ force: true })
		await page.waitForTimeout(500)
		expect(posts).toBe(0)
		await expect(dialog).toBeVisible()

		// One complete link makes it savable, so the refusal was the empty list's.
		await dialog.getByRole('button', { name: 'Add link' }).click()
		await rows(dialog).nth(0).getByLabel('Label', { exact: true }).fill('Only')
		await rows(dialog).nth(0).getByLabel('URL', { exact: true }).fill('https://example.com/only')
		await expect(add).toBeEnabled()
	})

	// @e2e link-button-widget::each-link-requires-label-and-url
	test('a link without a label blocks saving and its row is highlighted', async ({ page }) => {
		const dialog = await openAddLinkForm(page, NAME)
		await pickOption(page, dialog, 'Display mode', DISPLAY.list)
		await rows(dialog).nth(0).getByLabel('Label', { exact: true }).fill('Complete')
		await rows(dialog).nth(0).getByLabel('URL', { exact: true }).fill('https://example.com/complete')
		await dialog.getByRole('button', { name: 'Add link' }).click()
		await rows(dialog).nth(1).getByLabel('URL', { exact: true }).fill('https://example.com/nameless')

		const add = dialog.locator(SAVE)
		await expect(add).toBeDisabled()
		await expect(add).toHaveAttribute('title', 'Each link requires a label and a URL')
		await expect(rows(dialog).nth(1)).toHaveClass(/cn-link-button-widget-form__link-row--invalid/)
		await expect(rows(dialog).nth(0)).not.toHaveClass(/--invalid/)

		await rows(dialog).nth(1).getByLabel('Label', { exact: true }).fill('Named now')
		await expect(rows(dialog).nth(1)).not.toHaveClass(/--invalid/)
		await expect(add).toBeEnabled()
	})
})

test.describe('link button form: legacy placements (REQ-LBLM-009)', () => {
	// @e2e link-button-widget::edit-form-detects-legacy-format
	test('a legacy placement opens in the single-link form, not the list editor', async ({ page }) => {
		const id = await place({ url: 'https://example.com', icon: 'external', label: 'Go' })
		await openWithWidget(page, id)
		const dialog = await openEditForm(page, id)

		await expectSelected(dialog, 'Display mode', 'Single button')
		await expect(topField(dialog, 'Label')).toHaveValue('Go')
		await expect(topField(dialog, 'URL')).toHaveValue('https://example.com')
		await expect(dialog.locator('.cn-link-button-widget-form__list-editor')).toHaveCount(0)
		await expect(rows(dialog)).toHaveCount(0)
		await expect(dialog.getByRole('button', { name: 'Add link' })).toHaveCount(0)
	})

	// @e2e link-button-widget::upgrade-from-button-to-list-preserves-existing-link
	test('switching a legacy placement to list seeds the first link from it and saves', async ({ page }) => {
		const id = await place({ url: 'https://example.com', icon: 'external', label: 'Go' })
		await openWithWidget(page, id)
		const dialog = await openEditForm(page, id)

		await pickOption(page, dialog, 'Display mode', DISPLAY.list)
		await expect(rows(dialog)).toHaveCount(1)
		await expect(rows(dialog).nth(0).getByLabel('Label', { exact: true })).toHaveValue('Go')
		await expect(rows(dialog).nth(0).getByLabel('URL', { exact: true })).toHaveValue('https://example.com')

		await submit(page, dialog, 'PUT')
		const read = await readPlacement(api, dashboard!.id, id)
		expect(read.content.displayMode).toBe('list')
		expect(read.content.links).toEqual([
			{ label: 'Go', url: 'https://example.com', icon: 'external', actionType: 'external', value: '' },
		])
		// The dashboard draws the upgraded placement as a one-item list.
		const items = page.locator(`[data-placement-id="${id}"] .cn-link-button-widget__list-item`)
		await expect(items).toHaveCount(1)
		await expect(items).toHaveText('Go')
	})
})
