/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the links widget's edit form
 * (openspec/specs/links-widget, REQ-LNKS-008), written for the
 * e2e-coverage-programme change.
 *
 * A scenario that starts from "a section exists" seeds a `links` placement
 * with those sections through the API, then opens it the way a person does:
 * More → Edit, the widget's cog → "Edit widget". The change is made in the
 * form, saved, and proven twice: in the placement read back from the API
 * (what persisted) and in the rendered widget (what the user sees).
 *
 * REORDERING. The scenarios say "drag the drag handle". `CnLinksWidgetForm`
 * has no drag handle: it reorders with "Move up" / "Move down" buttons on each
 * section and each link row (its own docblock: "Uses up/down arrow buttons for
 * reorder (keyboard-accessible by default)"), and the scenario's note calls the
 * handle an implementation detail. The reorder tests therefore drive those
 * buttons and assert what the scenarios require of the result: the new
 * position, persisted, and other sections untouched.
 *
 * Gate-19 @e2e traceability:
 *   @e2e links-widget::add-a-new-section
 *   @e2e links-widget::edit-section-title
 *   @e2e links-widget::add-link-to-section
 *   @e2e links-widget::edit-link-details
 *   @e2e links-widget::delete-link
 *   @e2e links-widget::delete-section
 *   @e2e links-widget::drag-to-reorder-sections
 *   @e2e links-widget::drag-to-reorder-links-within-a-section
 *   @e2e links-widget::icon-preview-in-edit-form
 *
 * @spec openspec/specs/links-widget/spec.md
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, test } from '@playwright/test'
import * as path from 'path'
import {
	adminApi,
	fillLinkRow,
	formSections,
	gotoDashboard,
	linksWidget,
	openEditForm,
	openLinksForm,
	removeLinksDashboard,
	seedLinksDashboard,
	seedLinksPlacement,
	storedContent,
	submit,
} from './support/linksWidget.ts'

const TINY_PNG = path.join(__dirname, 'fixtures', 'tiny.png')
const ICON_URL = 'https://example.com/logo.png'

const LABEL = 'editor'

let api: APIRequestContext
let dashboard: SeededDashboard | null = null

test.describe.configure({ timeout: 120_000 })

test.beforeEach(async () => {
	api = await adminApi()
	dashboard = await seedLinksDashboard(api, LABEL)
})

test.afterEach(async () => {
	await removeLinksDashboard(api, dashboard)
	dashboard = null
	await api.dispose()
})

/**
 * Seed a placement with these sections, open the dashboard and the widget's
 * edit form.
 *
 * @param page the page.
 * @param sections the sections.
 * @return the placement id, the dialog and the form.
 */
async function editSeeded(
	page: Page,
	sections: Array<{ title: string; links: Array<Record<string, string>> }>,
): Promise<{ id: number; dialog: Locator; form: Locator }> {
	const id = await seedLinksPlacement(api, dashboard!.id, { sections })
	await gotoDashboard(page)
	await expect(linksWidget(page, id)).toBeVisible({ timeout: 20_000 })
	const { dialog, form } = await openEditForm(page, id)
	return { id, dialog, form }
}

/**
 * A link object with every field.
 *
 * @param label the label; the URL is derived from it.
 * @return the link.
 */
function link(label: string): Record<string, string> {
	return {
		label,
		url: `https://example.com/${label.toLowerCase()}`,
		icon: '',
		description: '',
	}
}

/**
 * The link rows of one form section.
 *
 * @param section the section block.
 * @return the rows.
 */
function rows(section: Locator): Locator {
	return section.locator('.cn-links-form__link-row')
}

/**
 * Labels of the links the widget renders, in order.
 *
 * @param widget the widget root.
 * @return a locator over the labels.
 */
function renderedLabels(widget: Locator): Locator {
	return widget.locator('.cn-links-widget__label')
}

// @e2e links-widget::add-a-new-section
test('"Add section" appends a section with a title field, a links table and a delete button', async ({
	page,
}) => {
	await gotoDashboard(page)
	const { form } = await openLinksForm(page, dashboard!.name)
	await expect(formSections(form)).toHaveCount(0)

	await form.getByRole('button', { name: /add section/i }).click()
	await expect(formSections(form)).toHaveCount(1)
	const section = formSections(form).first()

	const title = section.getByPlaceholder('Section title', { exact: true })
	await expect(title).toBeVisible()
	await expect(title).toBeEditable()
	await expect(title).toHaveValue('')
	await expect(section.locator('button[title="Delete section"]')).toBeVisible()
	await expect(section.locator('.cn-links-form__links')).toBeVisible()
	await expect(section.getByRole('button', { name: /add link/i })).toBeVisible()

	// Appended, not prepended: a second one lands after the first.
	await title.fill('First')
	await form.getByRole('button', { name: /add section/i }).click()
	await expect(formSections(form)).toHaveCount(2)
	await expect(
		formSections(form).nth(0).getByPlaceholder('Section title', { exact: true }),
	).toHaveValue('First')
	await expect(
		formSections(form).nth(1).getByPlaceholder('Section title', { exact: true }),
	).toHaveValue('')

	// The nested links table starts empty.
	await expect(
		rows(formSections(form).nth(1)),
		'a new section starts with a link row',
	).toHaveCount(0)
})

// @e2e links-widget::edit-section-title
test('editing a section title updates the stored config and the rendered heading', async ({
	page,
}) => {
	const { id, dialog, form } = await editSeeded(page, [
		{ title: 'Old title', links: [link('Docs')] },
	])
	const title = formSections(form)
		.first()
		.getByPlaceholder('Section title', { exact: true })
	await expect(title).toHaveValue('Old title')
	await title.fill('New title')
	await submit(dialog)

	await expect
		.poll(
			async () =>
				(await storedContent(api, dashboard!.id, id)).sections[0].title,
		)
		.toBe('New title')
	const widget = linksWidget(page, id)
	await expect(widget.locator('.cn-links-widget__section-title')).toHaveText([
		'New title',
	])
	await expect(widget.getByText('Old title')).toHaveCount(0)
})

// @e2e links-widget::add-link-to-section
test('"Add link" appends a row with label, URL, icon with preview, description and delete', async ({
	page,
}) => {
	const { id, dialog, form } = await editSeeded(page, [
		{ title: 'Tools', links: [link('Docs')] },
	])
	const section = formSections(form).first()
	await expect(rows(section)).toHaveCount(1)

	await section.getByRole('button', { name: /add link/i }).click()
	await expect(rows(section)).toHaveCount(2)
	const row = rows(section).nth(1)
	await expect(row.getByPlaceholder('Label', { exact: true })).toHaveValue('')
	await expect(row.getByPlaceholder('URL', { exact: true })).toHaveValue('')
	await expect(row.locator('.cn-icon-browser__trigger')).toBeVisible()
	await expect(
		row.getByPlaceholder('Description (optional)', { exact: true }),
	).toBeVisible()
	await expect(row.locator('button[title="Delete link"]')).toBeVisible()
	// The existing row is untouched.
	await expect(
		rows(section).nth(0).getByPlaceholder('Label', { exact: true }),
	).toHaveValue('Docs')

	await fillLinkRow(row, { label: 'Wiki', url: 'https://example.com/wiki' })
	await submit(dialog)
	await expect
		.poll(async () =>
			(await storedContent(api, dashboard!.id, id)).sections[0].links.map(
				(l: { label: string }) => l.label,
			),
		)
		.toEqual(['Docs', 'Wiki'])
	await expect(renderedLabels(linksWidget(page, id))).toHaveText(['Docs', 'Wiki'])
})

// @e2e links-widget::edit-link-details
test('editing label, URL, icon and description is stored and re-rendered', async ({
	page,
}) => {
	await page.route(ICON_URL, (route) =>
		route.fulfill({ path: TINY_PNG, contentType: 'image/png' }),
	)
	const { id, dialog, form } = await editSeeded(page, [
		{
			title: 'Tools',
			links: [
				{
					label: 'Old',
					url: 'https://example.com/old',
					icon: '',
					description: 'old text',
				},
			],
		},
	])
	const row = rows(formSections(form).first()).first()
	await fillLinkRow(row, {
		label: 'New',
		url: 'https://example.com/new',
		description: 'new text',
	})
	await row.locator('.cn-icon-browser__trigger').click()
	const browser = page.getByRole('dialog', { name: 'Icon browser' })
	await browser.getByRole('tab', { name: 'Custom' }).click()
	await browser.getByLabel('Image URL', { exact: true }).fill(ICON_URL)
	await row.getByPlaceholder('Label', { exact: true }).click()
	await submit(dialog)

	await expect
		.poll(
			async () =>
				(await storedContent(api, dashboard!.id, id)).sections[0].links[0],
		)
		.toEqual({
			label: 'New',
			url: 'https://example.com/new',
			icon: ICON_URL,
			description: 'new text',
		})

	const anchor = linksWidget(page, id).locator('a.cn-links-widget__link')
	await expect(anchor).toHaveCount(1)
	await expect(anchor.locator('.cn-links-widget__label')).toHaveText('New')
	await expect(anchor).toHaveAttribute('href', 'https://example.com/new')
	await expect(anchor.locator('.cn-links-widget__description')).toHaveText(
		'new text',
	)
	await expect(anchor.locator('.cn-links-widget__icon-wrap img')).toHaveAttribute(
		'src',
		ICON_URL,
	)
	await expect(
		linksWidget(page, id).getByText('Old', { exact: true }),
	).toHaveCount(0)
})

// @e2e links-widget::delete-link
test('deleting the only link of a section keeps the section in the config', async ({
	page,
}) => {
	const { id, dialog, form } = await editSeeded(page, [
		{ title: 'Solo', links: [link('Lonely')] },
		{ title: 'Other', links: [link('Kept')] },
	])
	const solo = formSections(form).first()
	await solo.locator('button[title="Delete link"]').click()
	await expect(rows(solo)).toHaveCount(0)
	// The section itself is still in the form.
	await expect(formSections(form)).toHaveCount(2)
	await expect(
		solo.getByPlaceholder('Section title', { exact: true }),
	).toHaveValue('Solo')
	await submit(dialog)

	await expect
		.poll(async () => (await storedContent(api, dashboard!.id, id)).sections)
		.toEqual([
			{ title: 'Solo', links: [] },
			{ title: 'Other', links: [expect.objectContaining({ label: 'Kept' })] },
		])
	// Rendered: the link is gone, and so is the now-empty section (REQ-LNKS-003).
	const widget = linksWidget(page, id)
	await expect(renderedLabels(widget)).toHaveText(['Kept'])
	await expect(widget.locator('.cn-links-widget__section-title')).toHaveText([
		'Other',
	])
})

// @e2e links-widget::delete-section
test('deleting a section removes it and all of its links from the config', async ({
	page,
}) => {
	const { id, dialog, form } = await editSeeded(page, [
		{ title: 'Doomed', links: [link('Gone1'), link('Gone2')] },
		{ title: 'Survivor', links: [link('Stays')] },
	])
	await formSections(form)
		.first()
		.locator('button[title="Delete section"]')
		.click()
	await expect(formSections(form)).toHaveCount(1)
	await expect(
		formSections(form)
			.first()
			.getByPlaceholder('Section title', { exact: true }),
	).toHaveValue('Survivor')
	await submit(dialog)

	await expect
		.poll(async () => (await storedContent(api, dashboard!.id, id)).sections)
		.toEqual([
			{
				title: 'Survivor',
				links: [expect.objectContaining({ label: 'Stays' })],
			},
		])
	const widget = linksWidget(page, id)
	await expect(widget.locator('.cn-links-widget__section-title')).toHaveText([
		'Survivor',
	])
	await expect(renderedLabels(widget)).toHaveText(['Stays'])
})

// @e2e links-widget::drag-to-reorder-sections
test('moving a section down puts it in the new position and the order persists', async ({
	page,
}) => {
	const { id, dialog, form } = await editSeeded(page, [
		{ title: 'First', links: [link('A1')] },
		{ title: 'Second', links: [link('B1')] },
		{ title: 'Third', links: [link('C1')] },
	])
	await formSections(form)
		.first()
		.locator('.cn-links-form__section-actions button[title="Move down"]')
		.click()
	const titles = formSections(form).getByPlaceholder('Section title', {
		exact: true,
	})
	await expect(titles.nth(0)).toHaveValue('Second')
	await expect(titles.nth(1)).toHaveValue('First')
	await expect(titles.nth(2)).toHaveValue('Third')
	await submit(dialog)

	await expect
		.poll(async () =>
			(await storedContent(api, dashboard!.id, id)).sections.map(
				(s: { title: string }) => s.title,
			),
		)
		.toEqual(['Second', 'First', 'Third'])
	// The links travelled with their section.
	const stored = await storedContent(api, dashboard!.id, id)
	expect(stored.sections[1].links[0].label).toBe('A1')
	await expect(
		linksWidget(page, id).locator('.cn-links-widget__section-title'),
	).toHaveText(['Second', 'First', 'Third'])
})

// @e2e links-widget::drag-to-reorder-links-within-a-section
test('moving a link down reorders only its own section and the order persists', async ({
	page,
}) => {
	const { id, dialog, form } = await editSeeded(page, [
		{ title: 'Moving', links: [link('A1'), link('A2'), link('A3')] },
		{ title: 'Still', links: [link('B1'), link('B2')] },
	])
	const moving = formSections(form).first()
	await rows(moving).first().locator('button[title="Move down"]').click()
	const labels = moving.getByPlaceholder('Label', { exact: true })
	await expect(labels.nth(0)).toHaveValue('A2')
	await expect(labels.nth(1)).toHaveValue('A1')
	await expect(labels.nth(2)).toHaveValue('A3')
	await submit(dialog)

	await expect
		.poll(async () =>
			(await storedContent(api, dashboard!.id, id)).sections.map(
				(s: { links: Array<{ label: string }> }) =>
					s.links.map((l) => l.label),
			),
		)
		.toEqual([
			['A2', 'A1', 'A3'],
			['B1', 'B2'],
		])
	await expect(renderedLabels(linksWidget(page, id))).toHaveText([
		'A2',
		'A1',
		'A3',
		'B1',
		'B2',
	])
})

// @e2e links-widget::icon-preview-in-edit-form
test('the icon field previews a picked icon as svg and a URL as img', async ({
	page,
}) => {
	await page.route(ICON_URL, (route) =>
		route.fulfill({ path: TINY_PNG, contentType: 'image/png' }),
	)
	await gotoDashboard(page)
	const { form } = await openLinksForm(page, dashboard!.name)
	await form.getByRole('button', { name: /add section/i }).click()
	const section = formSections(form).first()
	if ((await rows(section).count()) === 0) {
		await section.getByRole('button', { name: /add link/i }).click()
	}
	const trigger = rows(section).first().locator('.cn-icon-browser__trigger')

	// Nothing chosen yet: no preview image or glyph.
	await expect(trigger.locator('svg, img')).toHaveCount(0)

	// A named icon: the preview is the resolved svg.
	await trigger.click()
	const browser = page.getByRole('dialog', { name: 'Icon browser' })
	await expect(browser).toBeVisible()
	await browser
		.getByRole('searchbox', { name: 'Search icons' })
		.first()
		.fill('file')
	await browser.locator('.cn-icon-browser-panel__cell').first().click()
	await expect(trigger.locator('svg')).toHaveCount(1)
	await expect(trigger.locator('img')).toHaveCount(0)

	// A URL: the preview is an <img> of that URL.
	await trigger.click()
	await expect(browser).toBeVisible()
	await browser.getByRole('tab', { name: 'Custom' }).click()
	await browser.getByLabel('Image URL', { exact: true }).fill(ICON_URL)
	await expect(trigger.locator('img')).toHaveAttribute('src', ICON_URL)
	await expect(trigger.locator('svg')).toHaveCount(0)
})
