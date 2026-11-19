/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for what the links widget stores and what it
 * refuses to store (openspec/specs/links-widget, REQ-LNKS-002, -003 and
 * -007), written for the e2e-coverage-programme change.
 *
 * "Config stores X" is proven the way the user causes it: the widget is added
 * through the Add Widget modal (dashboard row cog → "Add custom widget…" →
 * Links), and the placement is then read back from
 * `GET /api/dashboard/{id}`. Each option is saved twice, once untouched (the
 * default the scenario names) and once changed (that the change is what is
 * stored).
 *
 * URL refusals are probed at both doors the spec names: the form, which must
 * tell the user, and the save endpoint, which must answer HTTP 400 and store
 * nothing. Each refusal test first proves the same endpoint accepts a valid
 * link, so a broken endpoint cannot pass as a refusal.
 *
 * Gate-19 @e2e traceability:
 *   @e2e links-widget::config-stores-sections-array
 *   @e2e links-widget::config-stores-column-count
 *   @e2e links-widget::config-stores-link-layout-mode
 *   @e2e links-widget::config-stores-icon-size-preference
 *   @e2e links-widget::config-stores-open-in-new-tab-preference
 *   @e2e links-widget::config-stores-section-visibility-preference
 *   @e2e links-widget::config-stores-description-visibility-preference
 *   @e2e links-widget::link-has-required-fields
 *   @e2e links-widget::link-has-optional-icon-field
 *   @e2e links-widget::link-has-optional-description-field
 *   @e2e links-widget::valid-https-url-accepted
 *   @e2e links-widget::valid-http-url-accepted
 *   @e2e links-widget::valid-relative-url-accepted
 *   @e2e links-widget::javascript-url-rejected
 *   @e2e links-widget::data-url-rejected
 *   @e2e links-widget::file-url-rejected
 *   @e2e links-widget::empty-url-rejected
 *
 * @spec openspec/specs/links-widget/spec.md
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, test } from '@playwright/test'
import { BASE_URL } from './support/baseUrl.ts'
import {
	addLinksThroughModal,
	adminApi,
	awaitLinksPlacement,
	closeSwitcher,
	fillLinkRow,
	formOption,
	formSections,
	gotoDashboard,
	linksPlacementsOf,
	linksWidget,
	oneSection,
	openEditForm,
	openLinksForm,
	postLinksPlacement,
	removeLinksDashboard,
	saveButton,
	seedLinksDashboard,
	seedLinksPlacement,
	submit,
	typeSection,
} from './support/linksWidget.ts'

const LOGO = `${BASE_URL}/core/img/logo/logo.svg`

const LABEL = 'config'

let api: APIRequestContext
let dashboard: SeededDashboard | null = null

test.describe.configure({ timeout: 150_000 })

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
 * The validation message the links form shows under its fields.
 *
 * @param form the `.cn-links-form` locator.
 * @return the message.
 */
function formError(form: Locator): Locator {
	return form.locator('.cn-links-form__error')
}

/**
 * Open the links form with one section whose only link has this URL.
 *
 * @param page the page.
 * @param url the URL to type.
 * @return the dialog and form.
 */
async function formWithUrl(
	page: Page,
	url: string,
): Promise<{ dialog: Locator; form: Locator }> {
	await gotoDashboard(page)
	const opened = await openLinksForm(page, dashboard!.name)
	await typeSection(opened.form, {
		title: 'Links',
		links: [{ label: 'Target', url }],
	})
	return opened
}

/**
 * Type a URL in the form, save, and return the stored link and its rendering.
 *
 * @param page the page.
 * @param url the URL.
 * @return the stored url and the rendered anchor.
 */
async function saveUrlThroughForm(
	page: Page,
	url: string,
): Promise<{ stored: string; anchor: Locator }> {
	const { dialog, form } = await formWithUrl(page, url)
	await expect(formError(form)).toHaveCount(0)
	await expect(saveButton(dialog)).toBeEnabled()
	await submit(dialog)
	await closeSwitcher(page)
	const placement = await awaitLinksPlacement(api, dashboard!.id)
	const stored = placement.content.sections[0].links[0].url
	const anchor = linksWidget(page, Number(placement.id))
		.locator('a.cn-links-widget__link')
		.filter({ hasText: 'Target' })
	return { stored, anchor }
}

/**
 * Prove the save endpoint accepts a valid link, then that it refuses `url`
 * with HTTP 400 and that nothing of it was stored.
 *
 * @param url the URL that must be refused.
 * @return nothing.
 */
async function expectApiRefuses(url: unknown): Promise<void> {
	const ok = await postLinksPlacement(
		api,
		dashboard!.id,
		oneSection([{ label: 'Valid', url: 'https://example.com/ok' }]),
	)
	expect(
		ok.status(),
		`the valid control was refused: ${await ok.text()}`,
	).toBeLessThan(300)

	const refused = await postLinksPlacement(api, dashboard!.id, {
		sections: [{ title: 'Bad', links: [{ label: 'Bad', url }] }],
	})
	const stored = await linksPlacementsOf(api, dashboard!.id)
	const leaked = stored.filter((p) => JSON.stringify(p.content).includes('"Bad"'))
	expect(
		refused.status(),
		`${JSON.stringify(url)} was answered ${refused.status()}: ${await refused.text()}`,
	).toBe(400)
	expect(leaked, `${JSON.stringify(url)} was stored`).toEqual([])
	expect(stored).toHaveLength(1)
}

// ── REQ-LNKS-002 Placement configuration ─────────────────────────────────

// @e2e links-widget::config-stores-sections-array
test('sections typed in the form are stored as a JSON array of titled sections with link objects', async ({
	page,
}) => {
	await gotoDashboard(page)
	const { dialog, form } = await openLinksForm(page, dashboard!.name)
	await typeSection(form, {
		title: 'Tools',
		links: [
			{ label: 'Docs', url: 'https://example.com/docs' },
			{ label: 'Wiki', url: '/apps/wiki' },
		],
	})
	await typeSection(form, {
		title: 'Team',
		links: [{ label: 'Chat', url: 'https://example.com/chat' }],
	})
	await submit(dialog)
	await closeSwitcher(page)

	const placement = await awaitLinksPlacement(api, dashboard!.id)
	const sections = placement.content.sections
	// An array of objects, not a JSON string or a keyed map.
	expect(Array.isArray(sections), `sections is ${typeof sections}`).toBe(true)
	expect(sections).toHaveLength(2)
	expect(sections[0]).toEqual({
		title: 'Tools',
		links: [
			expect.objectContaining({
				label: 'Docs',
				url: 'https://example.com/docs',
			}),
			expect.objectContaining({ label: 'Wiki', url: '/apps/wiki' }),
		],
	})
	expect(sections[1]).toEqual({
		title: 'Team',
		links: [
			expect.objectContaining({
				label: 'Chat',
				url: 'https://example.com/chat',
			}),
		],
	})
	expect(JSON.parse(JSON.stringify(sections))).toEqual(sections)

	// Deserialises cleanly: the editor reopens on exactly what was typed.
	const edit = await openEditForm(page, Number(placement.id))
	const blocks = formSections(edit.form)
	await expect(blocks).toHaveCount(2)
	await expect(
		blocks.nth(0).getByPlaceholder('Section title', { exact: true }),
	).toHaveValue('Tools')
	await expect(
		blocks.nth(1).getByPlaceholder('Section title', { exact: true }),
	).toHaveValue('Team')
	await expect(
		blocks.nth(0).getByPlaceholder('Label', { exact: true }),
	).toHaveCount(2)
	await expect(
		blocks.nth(0).getByPlaceholder('URL', { exact: true }).nth(0),
	).toHaveValue('https://example.com/docs')
	await expect(
		blocks.nth(0).getByPlaceholder('URL', { exact: true }).nth(1),
	).toHaveValue('/apps/wiki')
	await expect(
		blocks.nth(1).getByPlaceholder('Label', { exact: true }),
	).toHaveValue('Chat')
})

// @e2e links-widget::config-stores-column-count
test('columns default to 3, stay within 1–6 and are stored as a number', async ({
	page,
}) => {
	await gotoDashboard(page)
	const untouched = await addLinksThroughModal(page, api, dashboard!)
	expect(untouched.content.columns).toBe(3)

	const changed = await addLinksThroughModal(
		page,
		api,
		dashboard!,
		async (form) => {
			const columns = formOption(form, 'Columns')
			await expect(columns).toHaveAttribute('min', '1')
			await expect(columns).toHaveAttribute('max', '6')
			await columns.fill('9')
			await expect(columns).toHaveValue('6')
			await columns.fill('0')
			await expect(columns).toHaveValue('1')
			await columns.fill('5')
		},
	)
	expect(changed.content.columns).toBe(5)
	expect(typeof changed.content.columns).toBe('number')
})

// @e2e links-widget::config-stores-link-layout-mode
test('linkLayout defaults to card and stores the chosen mode', async ({ page }) => {
	await gotoDashboard(page)
	const untouched = await addLinksThroughModal(page, api, dashboard!)
	expect(untouched.content.linkLayout).toBe('card')

	const changed = await addLinksThroughModal(
		page,
		api,
		dashboard!,
		async (form) => {
			const layout = formOption(form, 'Layout')
			await expect(layout.locator('option')).toHaveText([
				'Card',
				'Inline',
				'Icon only',
			])
			await layout.selectOption('icon-only')
		},
	)
	expect(changed.content.linkLayout).toBe('icon-only')
})

// @e2e links-widget::config-stores-icon-size-preference
test('iconSize defaults to medium, stores the choice, and maps to 24/40/64 px', async ({
	page,
}) => {
	await gotoDashboard(page)
	const untouched = await addLinksThroughModal(page, api, dashboard!)
	expect(untouched.content.iconSize).toBe('medium')

	const changed = await addLinksThroughModal(
		page,
		api,
		dashboard!,
		async (form) => {
			await formOption(form, 'Icon size').selectOption('large')
		},
	)
	expect(changed.content.iconSize).toBe('large')

	const small = await seedLinksPlacement(
		api,
		dashboard!.id,
		oneSection(
			[{ label: 'Small', url: 'https://example.com/s', icon: 'icon-files' }],
			{ iconSize: 'small' },
		),
		{ gridY: 20 },
	)
	await page.reload()
	await gotoDashboard(page)

	const sizes: Array<[number, number]> = [
		[small, 24],
		[Number(untouched.placement.id), 40],
		[Number(changed.placement.id), 64],
	]
	for (const [id, px] of sizes) {
		const wrap = linksWidget(page, id)
			.locator('.cn-links-widget__icon-wrap')
			.first()
		await expect(wrap).toHaveCount(1, { timeout: 20_000 })
		await expect(wrap).toHaveAttribute('style', new RegExp(`width:\\s*${px}px`))
		await expect(wrap).toHaveAttribute('style', new RegExp(`height:\\s*${px}px`))
		await expect(wrap.locator('svg, img').first()).toHaveAttribute(
			'width',
			String(px),
		)
	}
})

// @e2e links-widget::config-stores-open-in-new-tab-preference
test('openInNewTab defaults to true and stores false when switched off', async ({
	page,
}) => {
	await gotoDashboard(page)
	const untouched = await addLinksThroughModal(page, api, dashboard!)
	expect(untouched.content.openInNewTab).toBe(true)

	const changed = await addLinksThroughModal(
		page,
		api,
		dashboard!,
		async (form) => {
			const box = formOption(form, 'Open in new tab')
			await expect(box).toBeChecked()
			await box.uncheck()
		},
	)
	expect(changed.content.openInNewTab).toBe(false)
})

// @e2e links-widget::config-stores-section-visibility-preference
test('showSectionTitles defaults to true and stores false when switched off', async ({
	page,
}) => {
	await gotoDashboard(page)
	const untouched = await addLinksThroughModal(page, api, dashboard!)
	expect(untouched.content.showSectionTitles).toBe(true)

	const changed = await addLinksThroughModal(
		page,
		api,
		dashboard!,
		async (form) => {
			const box = formOption(form, 'Show section titles')
			await expect(box).toBeChecked()
			await box.uncheck()
		},
	)
	expect(changed.content.showSectionTitles).toBe(false)

	// And the stored value is what the widget obeys.
	await expect(
		linksWidget(page, Number(untouched.placement.id)).locator(
			'.cn-links-widget__section-title',
		),
	).toHaveText(['Tools'])
	await expect(
		linksWidget(page, Number(changed.placement.id)).locator(
			'a.cn-links-widget__link',
		),
	).toHaveCount(1)
	await expect(
		linksWidget(page, Number(changed.placement.id)).locator(
			'.cn-links-widget__section-title',
		),
	).toHaveCount(0)
})

// @e2e links-widget::config-stores-description-visibility-preference
test('showLinkDescriptions defaults to true, stores false, and only applies to card layout', async ({
	page,
}) => {
	await gotoDashboard(page)
	const untouched = await addLinksThroughModal(page, api, dashboard!)
	expect(untouched.content.showLinkDescriptions).toBe(true)

	const changed = await addLinksThroughModal(
		page,
		api,
		dashboard!,
		async (form) => {
			const box = formOption(form, 'Show descriptions')
			const layout = formOption(form, 'Layout')
			// Outside card layout the option has no effect, so it is not offered.
			await layout.selectOption('inline')
			await expect(box).toBeDisabled()
			await layout.selectOption('icon-only')
			await expect(box).toBeDisabled()
			await layout.selectOption('card')
			await expect(box).toBeEnabled()
			await expect(box).toBeChecked()
			await box.uncheck()
		},
	)
	expect(changed.content.showLinkDescriptions).toBe(false)

	// Other layouts ignore it: inline never shows a description even when on.
	const inline = await seedLinksPlacement(
		api,
		dashboard!.id,
		oneSection(
			[
				{
					label: 'Inline',
					url: 'https://example.com/i',
					description: 'Inline description',
				},
			],
			{
				linkLayout: 'inline',
				showLinkDescriptions: true,
			},
		),
		{ gridY: 20 },
	)
	await page.reload()
	await gotoDashboard(page)
	await expect(
		linksWidget(page, inline).locator('a.cn-links-widget__link'),
	).toHaveCount(1, { timeout: 20_000 })
	await expect(
		linksWidget(page, inline).locator('.cn-links-widget__description'),
	).toHaveCount(0)
})

// ── REQ-LNKS-003 Link data structure ─────────────────────────────────────

// @e2e links-widget::link-has-required-fields
test('a link needs a non-empty label and URL before it can be saved', async ({
	page,
}) => {
	await gotoDashboard(page)
	const { dialog, form } = await openLinksForm(page, dashboard!.name)
	await form.getByRole('button', { name: /add section/i }).click()
	const block = formSections(form).first()
	await block.getByPlaceholder('Section title', { exact: true }).fill('Tools')
	if ((await block.locator('.cn-links-form__link-row').count()) === 0) {
		await block.getByRole('button', { name: /add link/i }).click()
	}
	const row = block.locator('.cn-links-form__link-row').first()

	// Blank label: refused.
	await fillLinkRow(row, { label: '   ', url: 'https://example.com/docs' })
	await expect(formError(form)).toHaveText('Link label is required')
	await expect(saveButton(dialog)).toBeDisabled()

	// Label, but a URL that is empty once trimmed: refused.
	await fillLinkRow(row, { label: 'Docs', url: '   ' })
	await expect(formError(form)).toHaveText('Link URL is required')
	await expect(saveButton(dialog)).toBeDisabled()

	// Both: accepted, and stored as strings.
	await fillLinkRow(row, { label: 'Docs', url: 'https://example.com/docs' })
	await expect(formError(form)).toHaveCount(0)
	await submit(dialog)
	const placement = await awaitLinksPlacement(api, dashboard!.id)
	const link = placement.content.sections[0].links[0]
	expect(typeof link.label).toBe('string')
	expect(typeof link.url).toBe('string')
	expect(link.label).toBe('Docs')
	expect(link.url).toBe('https://example.com/docs')
})

// @e2e links-widget::link-has-optional-icon-field
test('a link saves with or without an icon, and a set icon is stored and rendered', async ({
	page,
}) => {
	await gotoDashboard(page)
	const { dialog, form } = await openLinksForm(page, dashboard!.name)
	await typeSection(form, {
		title: 'Tools',
		links: [
			{ label: 'Plain', url: 'https://example.com/plain' },
			{ label: 'Branded', url: 'https://example.com/branded' },
		],
	})
	// Give the second link a URL icon through its icon browser.
	const row = formSections(form).first().locator('.cn-links-form__link-row').nth(1)
	await row.locator('.cn-icon-browser__trigger').click()
	const browser = page.getByRole('dialog', { name: 'Icon browser' })
	await expect(browser).toBeVisible()
	await browser.getByRole('tab', { name: 'Custom' }).click()
	await browser.getByLabel('Image URL', { exact: true }).fill(LOGO)
	await formOption(form, 'Columns').click()
	await expect(formError(form)).toHaveCount(0)
	await submit(dialog)
	await closeSwitcher(page)

	const placement = await awaitLinksPlacement(api, dashboard!.id)
	const [plain, branded] = placement.content.sections[0].links
	expect(plain.icon ?? '').toBe('')
	expect(branded.icon).toBe(LOGO)

	// Resolved per REQ-LNKS-004: the URL icon is an <img>.
	const widget = linksWidget(page, Number(placement.id))
	await expect(widget.locator('a.cn-links-widget__link')).toHaveCount(2, {
		timeout: 20_000,
	})
	await expect(
		widget
			.locator('a.cn-links-widget__link')
			.filter({ hasText: 'Branded' })
			.locator('.cn-links-widget__icon-wrap img'),
	).toHaveAttribute('src', LOGO)
})

// @e2e links-widget::link-has-optional-description-field
test('a description is optional, stored when given, and shown only in card layout', async ({
	page,
}) => {
	await gotoDashboard(page)
	const { dialog, form } = await openLinksForm(page, dashboard!.name)
	await typeSection(form, {
		title: 'Tools',
		links: [
			{
				label: 'Described',
				url: 'https://example.com/d',
				description: 'What this is',
			},
			{ label: 'Bare', url: 'https://example.com/b' },
		],
	})
	await submit(dialog)
	await closeSwitcher(page)

	const placement = await awaitLinksPlacement(api, dashboard!.id)
	const [described, bare] = placement.content.sections[0].links
	expect(described.description).toBe('What this is')
	expect(bare.description ?? '').toBe('')

	// Card (the default) shows it.
	const card = linksWidget(page, Number(placement.id))
	await expect(card.locator('.cn-links-widget__description')).toHaveText(
		['What this is'],
		{ timeout: 20_000 },
	)

	// The same links in inline and icon-only layouts do not.
	const links = placement.content.sections[0].links
	const inline = await seedLinksPlacement(
		api,
		dashboard!.id,
		{ sections: [{ title: 'I', links }], linkLayout: 'inline' },
		{ gridY: 20 },
	)
	const iconOnly = await seedLinksPlacement(
		api,
		dashboard!.id,
		{ sections: [{ title: 'O', links }], linkLayout: 'icon-only' },
		{ gridY: 30 },
	)
	await page.reload()
	await gotoDashboard(page)
	for (const id of [inline, iconOnly]) {
		await expect(
			linksWidget(page, id).locator('a.cn-links-widget__link'),
		).toHaveCount(2, { timeout: 20_000 })
		await expect(
			linksWidget(page, id).locator('.cn-links-widget__description'),
		).toHaveCount(0)
	}
})

// ── REQ-LNKS-007 URL sanitisation ────────────────────────────────────────

// @e2e links-widget::valid-https-url-accepted
test('an https URL is accepted, stored and rendered as the link target', async ({
	page,
}) => {
	const { stored, anchor } = await saveUrlThroughForm(
		page,
		'https://example.com/page',
	)
	expect(stored).toBe('https://example.com/page')
	await expect(anchor).toHaveAttribute('href', 'https://example.com/page', {
		timeout: 20_000,
	})
})

// @e2e links-widget::valid-http-url-accepted
test('an http URL is accepted, stored and rendered as the link target', async ({
	page,
}) => {
	const { stored, anchor } = await saveUrlThroughForm(
		page,
		'http://example.com/page',
	)
	expect(stored).toBe('http://example.com/page')
	await expect(anchor).toHaveAttribute('href', 'http://example.com/page', {
		timeout: 20_000,
	})
})

// @e2e links-widget::valid-relative-url-accepted
test('root-relative and single ../ URLs are accepted, a ../ chain is refused', async ({
	page,
}) => {
	// A root-relative path: accepted end to end.
	const { stored, anchor } = await saveUrlThroughForm(page, '/apps/files/list')
	expect(stored).toBe('/apps/files/list')
	await expect(anchor).toHaveAttribute('href', '/apps/files/list', {
		timeout: 20_000,
	})

	// A path-traversal chain: refused.
	await page.reload()
	const { dialog, form } = await formWithUrl(page, '../../../etc/passwd')
	await expect(formError(form)).toBeVisible()
	await expect(saveButton(dialog)).toBeDisabled()

	// A single ../ step: accepted.
	await fillLinkRow(
		formSections(form).first().locator('.cn-links-form__link-row').first(),
		{
			label: 'Target',
			url: '../other',
		},
	)
	await expect(formError(form), 'a simple ../ path was refused').toHaveCount(0)
	await expect(saveButton(dialog)).toBeEnabled()
	await submit(dialog)
	const placement = await awaitLinksPlacement(api, dashboard!.id, 2)
	expect(placement.content.sections[0].links[0].url).toBe('../other')
})

// @e2e links-widget::javascript-url-rejected
test('a javascript: URL is refused with feedback in the form and HTTP 400 from the API', async ({
	page,
}) => {
	const { dialog, form } = await formWithUrl(page, 'javascript:alert("xss")')
	await expect(formError(form)).toHaveText(/invalid url/i)
	await expect(saveButton(dialog)).toBeDisabled()
	await dialog.getByRole('button', { name: /cancel/i }).click()
	await expect(dialog).toBeHidden()
	expect(await linksPlacementsOf(api, dashboard!.id)).toHaveLength(0)

	await expectApiRefuses('javascript:alert("xss")')
})

// @e2e links-widget::data-url-rejected
test('a data: URL is refused with HTTP 400 and not stored', async () => {
	await expectApiRefuses('data:text/html,<script>alert(1)</script>')
})

// @e2e links-widget::file-url-rejected
test('a file: URL is refused with HTTP 400 and not stored', async () => {
	await expectApiRefuses('file:///etc/passwd')
})

// @e2e links-widget::empty-url-rejected
test('an empty or null URL is refused with HTTP 400, and the form says a URL is required', async ({
	page,
}) => {
	const { dialog, form } = await formWithUrl(page, '')
	await expect(formError(form)).toHaveText('Link URL is required')
	await expect(saveButton(dialog)).toBeDisabled()
	await dialog.getByRole('button', { name: /cancel/i }).click()
	await expect(dialog).toBeHidden()

	await expectApiRefuses('')
	const refusedNull = await postLinksPlacement(api, dashboard!.id, {
		sections: [{ title: 'Null', links: [{ label: 'Null', url: null }] }],
	})
	expect(refusedNull.status(), await refusedNull.text()).toBe(400)
	expect(await linksPlacementsOf(api, dashboard!.id)).toHaveLength(1)
})
