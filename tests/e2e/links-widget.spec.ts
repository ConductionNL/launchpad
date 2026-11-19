/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the links widget: where it is offered,
 * how its icons resolve, its three layout modes and its empty state
 * (openspec/specs/links-widget, REQ-LNKS-001, -003, -004, -005 and -009),
 * written for the e2e-coverage-programme change.
 *
 * Each test seeds its own dashboard and, where the scenario starts from a
 * configured widget, a `links` placement through
 * `POST /api/dashboard/{id}/widgets` with exactly the content the scenario's
 * GIVEN names. What is asserted is the DOM `CnLinksWidget` renders inside
 * that placement's grid item, as LaunchPad styles it. The dashboard is
 * deleted afterwards, which takes its placements with it.
 *
 * The rest of the spec lives in `links-widget-config.spec.ts` (stored
 * configuration and URL validation), `links-widget-editor.spec.ts` (the edit
 * form) and `links-widget-grid-navigation.spec.ts` (columns and link
 * navigation).
 *
 * Gate-19 @e2e traceability:
 *   @e2e links-widget::widget-appears-in-picker
 *   @e2e links-widget::multiple-instances-allowed
 *   @e2e links-widget::widget-registration-survives-reload
 *   @e2e links-widget::widget-metadata-is-complete
 *   @e2e links-widget::empty-sections-are-hidden-at-render-time
 *   @e2e links-widget::nextcloud-icon-name-resolved-to-svg-class
 *   @e2e links-widget::url-icon-resolved-to-img-element
 *   @e2e links-widget::empty-icon-defaults-to-generic-link-icon
 *   @e2e links-widget::unrecognized-icon-format-defaults-gracefully
 *   @e2e links-widget::card-layout-with-full-details
 *   @e2e links-widget::inline-layout-without-descriptions
 *   @e2e links-widget::icon-only-layout-with-hover-tooltip
 *   @e2e links-widget::description-visibility-toggle
 *   @e2e links-widget::empty-state-appears-with-no-sections
 *   @e2e links-widget::empty-state-appears-when-all-sections-are-empty
 *   @e2e links-widget::gear-icon-navigates-to-edit
 *
 * @spec openspec/specs/links-widget/spec.md
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, test } from '@playwright/test'
import * as path from 'path'
import {
	adminApi,
	API,
	awaitLinksPlacement,
	closeSwitcher,
	gotoDashboard,
	linksPlacementsOf,
	linksWidget,
	oneSection,
	openAddWidget,
	openEditForm,
	removeLinksDashboard,
	seedLinksDashboard,
	seedLinksPlacement,
	storedContent,
	submit,
	typeSection,
} from './support/linksWidget.ts'

const EMPTY_STATE = 'No links yet — click the gear icon to add some.'
const TINY_PNG = path.join(__dirname, 'fixtures', 'tiny.png')

const LABEL = 'widget'

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
 * Seed a placement and open the dashboard on it.
 *
 * @param page the page.
 * @param content the placement content.
 * @return the rendered widget root.
 */
async function renderLinks(
	page: Page,
	content: Record<string, unknown>,
): Promise<Locator> {
	const id = await seedLinksPlacement(api, dashboard!.id, content)
	await gotoDashboard(page)
	const widget = linksWidget(page, id)
	await expect(widget).toBeVisible({ timeout: 20_000 })
	return widget
}

/**
 * One rendered link, by its label (or its title attribute in icon-only mode).
 *
 * @param widget the widget root.
 * @param label the link label.
 * @return the anchor.
 */
function linkNamed(widget: Locator, label: string): Locator {
	return widget.locator(
		`a.cn-links-widget__link:has-text("${label}"), a.cn-links-widget__link[title="${label}"]`,
	)
}

// ── REQ-LNKS-001 Widget registration ─────────────────────────────────────

// @e2e links-widget::widget-appears-in-picker
test('the widget picker offers Links with a title and an icon, and it can be placed', async ({
	page,
}) => {
	await gotoDashboard(page)
	const dialog = await openAddWidget(page, dashboard!.name)
	const select = dialog.locator('[data-testid="widget-type-select"]')

	// Listed, with its title.
	const option = select.locator('option[value="links"]')
	await expect(option).toHaveCount(1)
	await expect(option).toHaveText('Links')

	// Selectable for placement: picking it opens its form, and saving places it.
	await select.selectOption('links')
	const form = dialog.locator('.cn-links-form')
	await expect(form).toBeVisible()
	await typeSection(form, {
		title: 'Picked',
		links: [{ label: 'Docs', url: 'https://example.com/docs' }],
	})

	// The scenario also requires an icon for the entry. The type picker is
	// the place the entry is listed, so the icon has to be shown there.
	const pickerIcon = dialog
		.locator('.cn-add-widget-modal__type')
		.locator('svg, img')
	const iconShown = await pickerIcon.count()

	await submit(dialog)
	const placement = await awaitLinksPlacement(api, dashboard!.id)
	expect(placement.widgetId).toBe('links')

	expect(
		iconShown,
		'the Links entry in the widget picker shows no icon',
	).toBeGreaterThan(0)
})

// @e2e links-widget::multiple-instances-allowed
test('a second links widget on the same dashboard keeps its own sections and links', async ({
	page,
}) => {
	const first = await seedLinksPlacement(
		api,
		dashboard!.id,
		{
			sections: [
				{
					title: 'Alpha',
					links: [
						{ label: 'Alpha link', url: 'https://example.com/alpha' },
					],
				},
			],
		},
		{ gridY: 0, gridHeight: 4 },
	)
	await gotoDashboard(page)
	await expect(linksWidget(page, first)).toBeVisible({ timeout: 20_000 })

	const dialog = await openAddWidget(page, dashboard!.name)
	await dialog.locator('[data-testid="widget-type-select"]').selectOption('links')
	await typeSection(dialog.locator('.cn-links-form'), {
		title: 'Beta',
		links: [{ label: 'Beta link', url: 'https://example.com/beta' }],
	})
	await submit(dialog)
	await closeSwitcher(page)

	const second = await awaitLinksPlacement(api, dashboard!.id, 2)
	expect(second.id).not.toBe(first)

	// Independent configurations on the server.
	const all = await linksPlacementsOf(api, dashboard!.id)
	expect(all).toHaveLength(2)
	expect(all[0].content.sections[0].title).toBe('Alpha')
	expect(all[1].content.sections[0].title).toBe('Beta')

	// Each renders its own sections and links, and only its own.
	const a = linksWidget(page, first)
	const b = linksWidget(page, Number(second.id))
	await expect(b).toBeVisible({ timeout: 15_000 })
	await expect(a.locator('.cn-links-widget__section-title')).toHaveText(['Alpha'])
	await expect(a.locator('a.cn-links-widget__link')).toHaveText([/Alpha link/])
	await expect(b.locator('.cn-links-widget__section-title')).toHaveText(['Beta'])
	await expect(b.locator('a.cn-links-widget__link')).toHaveText([/Beta link/])
})

// @e2e links-widget::widget-registration-survives-reload
test('Links is still offered in the picker after the app is reloaded', async ({
	page,
}) => {
	await gotoDashboard(page)
	let dialog = await openAddWidget(page, dashboard!.name)
	await expect(
		dialog.locator('[data-testid="widget-type-select"] option[value="links"]'),
	).toHaveCount(1)
	await dialog.getByRole('button', { name: /cancel/i }).click()
	await expect(dialog).toBeHidden()

	// A full reload rebuilds the registry from scratch.
	await page.reload()
	await gotoDashboard(page)
	dialog = await openAddWidget(page, dashboard!.name)
	const select = dialog.locator('[data-testid="widget-type-select"]')
	await expect(select.locator('option[value="links"]')).toHaveCount(1)
	await select.selectOption('links')
	await expect(dialog.locator('.cn-links-form')).toBeVisible()
})

// @e2e links-widget::widget-metadata-is-complete
test('the widget list the picker fetches describes the links widget with id, title and icon url', async () => {
	const res = await api.get(`${API}/widgets`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	const widgets: Array<Record<string, unknown>> = Array.isArray(body)
		? body
		: (body.data ?? body.widgets ?? [])
	expect(Array.isArray(widgets), JSON.stringify(body).slice(0, 300)).toBe(true)

	const links = widgets.find((w) => w.id === 'launchpad_links')
	expect(
		links,
		`no launchpad_links widget among ${widgets.map((w) => w.id).join(', ')}`,
	).toBeTruthy()
	expect(String(links!.title ?? '')).not.toBe('')
	expect(String(links!.icon_url ?? links!.iconUrl ?? '')).not.toBe('')
})

// ── REQ-LNKS-003 Empty sections ──────────────────────────────────────────

// @e2e links-widget::empty-sections-are-hidden-at-render-time
test('a section without links is not rendered but stays in the stored config', async ({
	page,
}) => {
	const content = {
		sections: [
			{ title: 'Empty for now', links: [] },
			{
				title: 'Filled',
				links: [{ label: 'Docs', url: 'https://example.com/docs' }],
			},
		],
	}
	const id = await seedLinksPlacement(api, dashboard!.id, content)
	await gotoDashboard(page)
	const widget = linksWidget(page, id)
	await expect(widget).toBeVisible({ timeout: 20_000 })

	await expect(widget.locator('.cn-links-widget__section')).toHaveCount(1)
	await expect(widget.locator('.cn-links-widget__section-title')).toHaveText([
		'Filled',
	])
	await expect(widget.getByText('Empty for now')).toHaveCount(0)

	const stored = await storedContent(api, dashboard!.id, id)
	expect(stored.sections.map((s: { title: string }) => s.title)).toEqual([
		'Empty for now',
		'Filled',
	])
	expect(stored.sections[0].links).toEqual([])
})

// ── REQ-LNKS-004 Icon resolution ─────────────────────────────────────────

// @e2e links-widget::nextcloud-icon-name-resolved-to-svg-class
test('a Nextcloud icon name renders as an MDI svg at the configured size', async ({
	page,
}) => {
	const widget = await renderLinks(
		page,
		oneSection([{ label: 'Files', url: '/apps/files', icon: 'icon-files' }], {
			iconSize: 'large',
		}),
	)
	const link = linkNamed(widget, 'Files')
	await expect(link).toHaveCount(1)

	const glyph = link.locator('.cn-links-widget__icon-wrap span[role="img"]')
	await expect(glyph).toHaveCount(1)
	// Resolved to the files glyph, not to a fallback.
	await expect(glyph).toHaveClass(/file/)
	await expect(glyph).not.toHaveClass(/help-circle|view-dashboard/)

	const svg = glyph.locator('svg')
	await expect(svg).toHaveAttribute('width', '64')
	await expect(svg).toHaveAttribute('height', '64')
	await expect(
		svg,
		'the resolved icon is in the DOM but not displayed',
	).toBeVisible()
	const box = await svg.boundingBox()
	expect(Math.round(box!.width)).toBe(64)
	expect(Math.round(box!.height)).toBe(64)
})

// @e2e links-widget::url-icon-resolved-to-img-element
test('a URL icon renders as an img with that src, the label as alt and the configured size', async ({
	page,
}) => {
	// Served locally so the image loads without outbound internet.
	await page.route('https://example.com/logo.png', (route) =>
		route.fulfill({ path: TINY_PNG, contentType: 'image/png' }),
	)
	const widget = await renderLinks(
		page,
		oneSection([
			{
				label: 'Custom',
				url: 'https://example.com',
				icon: 'https://example.com/logo.png',
			},
		]),
	)
	const img = linkNamed(widget, 'Custom').locator(
		'.cn-links-widget__icon-wrap img',
	)
	await expect(img).toHaveCount(1)
	await expect(img).toHaveAttribute('src', 'https://example.com/logo.png')
	// Default iconSize is medium = 40 px.
	await expect(img).toHaveAttribute('width', '40')
	await expect(img).toHaveAttribute('height', '40')
	await expect(img).toHaveAttribute('alt', 'Custom')
	await expect(img, 'the icon image is in the DOM but not displayed').toBeVisible()
	const box = await img.boundingBox()
	expect(Math.round(box!.width)).toBe(40)
})

// @e2e links-widget::empty-icon-defaults-to-generic-link-icon
test('a link with an empty icon shows the generic link glyph', async ({ page }) => {
	const widget = await renderLinks(
		page,
		oneSection([{ label: 'Document', url: '/docs/readme.pdf', icon: '' }]),
	)
	const glyph = linkNamed(widget, 'Document').locator(
		'.cn-links-widget__icon-wrap span[role="img"]',
	)
	await expect(glyph).toHaveCount(1)
	await expect(glyph, 'an empty icon must fall back to a link glyph').toHaveClass(
		/(^|\s)link(-variant)?-icon(\s|$)/,
	)
	await expect(glyph.locator('svg')).toBeVisible()
})

// @e2e links-widget::unrecognized-icon-format-defaults-gracefully
test('an unrecognised icon value falls back to the generic link glyph without breaking the widget', async ({
	page,
}) => {
	const errors: string[] = []
	page.on('pageerror', (err) => errors.push(err.message))

	const widget = await renderLinks(
		page,
		oneSection([
			{
				label: 'Bad Icon',
				url: 'https://example.com',
				icon: 'not-a-real-icon-name',
			},
			{ label: 'Neighbour', url: 'https://example.com/n' },
		]),
	)
	// Not crashed: both links render with their labels.
	await expect(linkNamed(widget, 'Bad Icon')).toContainText('Bad Icon')
	await expect(linkNamed(widget, 'Neighbour')).toContainText('Neighbour')
	expect(errors, 'rendering an unknown icon threw').toEqual([])

	const glyph = linkNamed(widget, 'Bad Icon').locator(
		'.cn-links-widget__icon-wrap span[role="img"]',
	)
	await expect(glyph).toHaveCount(1)
	await expect(
		glyph,
		'an unknown icon must fall back to a link glyph',
	).toHaveClass(/(^|\s)link(-variant)?-icon(\s|$)/)
	await expect(glyph.locator('svg')).toBeVisible()
})

// ── REQ-LNKS-005 Layout modes ────────────────────────────────────────────

// @e2e links-widget::card-layout-with-full-details
test('card layout shows icon, prominent label, description and a bordered card in a grid', async ({
	page,
}) => {
	const widget = await renderLinks(
		page,
		oneSection(
			[
				{
					label: 'Docs',
					url: 'https://example.com/docs',
					icon: 'icon-files',
					description: 'Read the docs',
				},
				{
					label: 'Wiki',
					url: 'https://example.com/wiki',
					icon: 'icon-link',
					description: 'Team wiki',
				},
			],
			{ linkLayout: 'card', showLinkDescriptions: true },
		),
	)
	await expect(widget).toHaveClass(/cn-links-widget--card/)
	const card = linkNamed(widget, 'Docs')
	await expect(card).toHaveClass(/cn-links-widget__link--card/)

	// Label, prominent.
	const label = card.locator('.cn-links-widget__label')
	await expect(label).toHaveText('Docs')
	const weight = Number(
		await label.evaluate((el) => getComputedStyle(el).fontWeight),
	)
	expect(weight).toBeGreaterThanOrEqual(500)

	// Description.
	await expect(card.locator('.cn-links-widget__description')).toHaveText(
		'Read the docs',
	)
	await expect(
		linkNamed(widget, 'Wiki').locator('.cn-links-widget__description'),
	).toHaveText('Team wiki')

	// Card border and padding.
	const style = await card.evaluate((el) => {
		const s = getComputedStyle(el)
		return {
			border: parseFloat(s.borderTopWidth),
			padding: parseFloat(s.paddingTop),
		}
	})
	expect(style.border).toBeGreaterThan(0)
	expect(style.padding).toBeGreaterThanOrEqual(8)

	// Hover effect: the card's background changes under the pointer.
	const before = await card.evaluate((el) => getComputedStyle(el).backgroundColor)
	await card.hover()
	await expect
		.poll(() => card.evaluate((el) => getComputedStyle(el).backgroundColor), {
			message: 'hovering a card changes nothing',
		})
		.not.toBe(before)

	// Icon, displayed.
	await expect(
		card.locator('.cn-links-widget__icon-wrap svg'),
		'the card icon is not displayed',
	).toBeVisible()

	// Arranged in a CSS grid.
	const display = await widget
		.locator('.cn-links-widget__items')
		.evaluate((el) => getComputedStyle(el).display)
	expect(display).toBe('grid')
})

// @e2e links-widget::inline-layout-without-descriptions
test('inline layout shows icon and label in a flat row and never the description', async ({
	page,
}) => {
	const widget = await renderLinks(
		page,
		oneSection(
			[
				{
					label: 'Docs',
					url: 'https://example.com/docs',
					icon: 'icon-files',
					description: 'Hidden in inline',
				},
			],
			{ linkLayout: 'inline', showLinkDescriptions: true },
		),
	)
	await expect(widget).toHaveClass(/cn-links-widget--inline/)
	const row = linkNamed(widget, 'Docs')
	await expect(row).toHaveClass(/cn-links-widget__link--inline/)
	await expect(row.locator('.cn-links-widget__label')).toHaveText('Docs')
	await expect(row.locator('.cn-links-widget__description')).toHaveCount(0)
	await expect(widget.getByText('Hidden in inline')).toHaveCount(0)

	// No card styling: no border.
	const border = await row.evaluate((el) =>
		parseFloat(getComputedStyle(el).borderTopWidth),
	)
	expect(border, 'an inline row is drawn with a card border').toBe(0)

	// Icon beside the label, on the same line.
	const icon = row.locator('.cn-links-widget__icon-wrap')
	await expect(icon, 'the inline icon is not displayed').toBeVisible()
	const iconBox = await icon.boundingBox()
	const labelBox = await row.locator('.cn-links-widget__label').boundingBox()
	expect(iconBox!.x + iconBox!.width).toBeLessThanOrEqual(labelBox!.x + 1)
	expect(
		Math.abs(
			iconBox!.y + iconBox!.height / 2 - (labelBox!.y + labelBox!.height / 2),
		),
	).toBeLessThan(iconBox!.height)
})

// @e2e links-widget::icon-only-layout-with-hover-tooltip
test('icon-only layout shows just the icon, the label as a tooltip, in an equal-cell grid', async ({
	page,
}) => {
	const widget = await renderLinks(
		page,
		oneSection(
			[
				{
					label: 'Docs',
					url: 'https://example.com/docs',
					icon: 'icon-files',
					description: 'not shown',
				},
				{
					label: 'Mail',
					url: 'https://example.com/mail',
					icon: 'icon-mail',
				},
				{
					label: 'Talk',
					url: 'https://example.com/talk',
					icon: 'icon-talk',
				},
			],
			{ linkLayout: 'icon-only', columns: 3 },
		),
	)
	await expect(widget).toHaveClass(/cn-links-widget--icon-only/)
	const links = widget.locator('a.cn-links-widget__link')
	await expect(links).toHaveCount(3)

	// No visible label, the label as the hover tooltip, no description.
	await expect(widget.locator('.cn-links-widget__label')).toHaveCount(0)
	await expect(widget.locator('.cn-links-widget__description')).toHaveCount(0)
	await expect(links.nth(0)).toHaveAttribute('title', 'Docs')
	await expect(links.nth(1)).toHaveAttribute('title', 'Mail')
	await expect(links.nth(2)).toHaveAttribute('title', 'Talk')
	await expect(links.nth(0)).not.toContainText('Docs')

	// The icon is what is shown.
	await expect(
		links.nth(0).locator('.cn-links-widget__icon-wrap svg'),
		'icon-only shows no icon',
	).toBeVisible()

	// A CSS grid with equal-sized cells.
	const grid = await widget.locator('.cn-links-widget__items').evaluate((el) => {
		const s = getComputedStyle(el)
		return {
			display: s.display,
			tracks: s.gridTemplateColumns.split(' ').filter(Boolean),
		}
	})
	expect(grid.display).toBe('grid')
	expect(grid.tracks).toHaveLength(3)
	expect(new Set(grid.tracks.map((t) => Math.round(parseFloat(t)))).size).toBe(1)
})

// @e2e links-widget::description-visibility-toggle
test('card layout with descriptions switched off shows none and reserves no space for them', async ({
	page,
}) => {
	const widget = await renderLinks(
		page,
		oneSection(
			[
				{
					label: 'With',
					url: 'https://example.com/a',
					icon: 'icon-files',
					description: 'A long description that would take room',
				},
				{
					label: 'Without',
					url: 'https://example.com/b',
					icon: 'icon-files',
				},
			],
			{ linkLayout: 'card', showLinkDescriptions: false, columns: 2 },
		),
	)
	await expect(widget.locator('.cn-links-widget__description')).toHaveCount(0)
	await expect(
		widget.getByText('A long description that would take room'),
	).toHaveCount(0)

	// The card with a configured description is no taller than the one without.
	const withBox = await linkNamed(widget, 'With').boundingBox()
	const withoutBox = await linkNamed(widget, 'Without').boundingBox()
	expect(Math.round(withBox!.height)).toBe(Math.round(withoutBox!.height))
})

// ── REQ-LNKS-009 Empty state ─────────────────────────────────────────────

// @e2e links-widget::empty-state-appears-with-no-sections
test('a widget with no sections shows the empty-state message and no grid', async ({
	page,
}) => {
	const widget = await renderLinks(page, { sections: [] })
	await expect(widget.locator('.cn-links-widget__empty')).toHaveText(EMPTY_STATE)
	await expect(widget.locator('.cn-links-widget__items')).toHaveCount(0)
	await expect(widget.locator('.cn-links-widget__section')).toHaveCount(0)
})

// @e2e links-widget::empty-state-appears-when-all-sections-are-empty
test('a widget whose only section is empty hides it and shows the empty-state message', async ({
	page,
}) => {
	const widget = await renderLinks(page, {
		sections: [{ title: 'Tools', links: [] }],
	})
	await expect(widget.locator('.cn-links-widget__section')).toHaveCount(0)
	await expect(widget.getByText('Tools', { exact: true })).toHaveCount(0)
	await expect(widget.locator('.cn-links-widget__empty')).toHaveText(EMPTY_STATE)
})

// @e2e links-widget::gear-icon-navigates-to-edit
test('from the empty state, the widget gear opens the widget edit modal', async ({
	page,
}) => {
	const id = await seedLinksPlacement(api, dashboard!.id, { sections: [] })
	await gotoDashboard(page)
	await expect(
		linksWidget(page, id).locator('.cn-links-widget__empty'),
	).toHaveText(EMPTY_STATE, { timeout: 20_000 })

	const { dialog, form } = await openEditForm(page, id)
	await expect(dialog).toBeVisible()
	// It is the editor of THIS widget: the links form, with nothing configured yet.
	await expect(form.locator('.cn-links-form__section')).toHaveCount(0)
	await expect(form.getByRole('button', { name: /add section/i })).toBeVisible()
})
