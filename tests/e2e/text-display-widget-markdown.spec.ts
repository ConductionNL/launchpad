/*
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the `text` widget's markdown mode
 * (REQ-TXMD-001..007 of openspec/specs/text-display-widget), written for
 * e2e-coverage-programme.
 *
 * Rendering scenarios seed a placement with the exact `text` and
 * `contentMode` the scenario names and assert the DOM the dashboard draws —
 * the real marked + DOMPurify pipeline in CnTextWidget, inside LaunchPad, with
 * LaunchPad's own translate pass in front of it. Mode scenarios drive the Mode
 * select of the real Add/Edit Widget modal and read the stored `contentMode`
 * back through the API.
 *
 * Gate-19 @e2e traceability:
 *   @e2e text-display-widget::existing-widgets-retain-html-mode
 *   @e2e text-display-widget::new-widget-receives-the-configured-default
 *   @e2e text-display-widget::widget-can-be-manually-switched-to-html-mode
 *   @e2e text-display-widget::explicit-html-mode-overrides-the-default
 *   @e2e text-display-widget::heading-shortcuts-render-to-semantic-heading-tags
 *   @e2e text-display-widget::emphasis-marks-render-to-inline-tags
 *   @e2e text-display-widget::inline-code-renders-to-code-tag
 *   @e2e text-display-widget::links-preserve-href-and-render-anchor-tag
 *   @e2e text-display-widget::bullet-lists-render-as-ul-li
 *   @e2e text-display-widget::ordered-lists-render-as-ol-li
 *   @e2e text-display-widget::block-quotes-render-as-blockquote
 *   @e2e text-display-widget::tables-render-to-semantic-table-markup
 *   @e2e text-display-widget::script-tags-in-markdown-are-stripped
 *   @e2e text-display-widget::event-handlers-are-removed-from-parsed-elements
 *   @e2e text-display-widget::data-attributes-are-removed-from-links
 *   @e2e text-display-widget::target-blank-links-get-rel-attribute
 *   @e2e text-display-widget::html-mode-is-shown-by-default-for-existing-widgets
 *   @e2e text-display-widget::markdown-mode-is-shown-for-markdown-mode-widgets
 *   @e2e text-display-widget::toggling-mode-preserves-text-content
 *   @e2e text-display-widget::mode-change-is-saved-with-other-form-fields
 *   @e2e text-display-widget::new-widgets-default-to-the-configured-mode
 *   @e2e text-display-widget::default-is-markdown-when-nothing-else-applies
 *   @e2e text-display-widget::invalid-mode-writes-are-rejected
 *   @e2e text-display-widget::existing-widget-renders-unchanged
 *   @e2e text-display-widget::html-mode-ignores-markdown-syntax
 *   @e2e text-display-widget::existing-sanitisation-rules-apply-to-html-mode
 *   @e2e text-display-widget::single-hash-produces-h1
 *   @e2e text-display-widget::double-hash-produces-h2
 *   @e2e text-display-widget::up-to-six-hashes-for-h6
 *   @e2e text-display-widget::seven-or-more-hashes-treated-as-literal-text
 *   @e2e text-display-widget::heading-followed-by-content
 *
 * @spec openspec/specs/text-display-widget/spec.md
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, test } from '@playwright/test'
import { ensureDefaultWidgetRestriction } from './fixtures/role-feature-permissions.ts'
import { gotoLaunchPad } from './fixtures/widget-flow.ts'
import {
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'
import {
	adminApi,
	chooseOption,
	ncSelect,
	openAddTextForm,
	openEditTextForm,
	seedText,
	selectedOption,
	storedContent,
	submitAdd,
	submitEdit,
	textArea,
	textWidget,
} from './support/textWidget.ts'

let api: APIRequestContext
let dashboard: SeededDashboard | null = null

test.beforeAll(async () => {
	await ensureDefaultWidgetRestriction()
	api = await adminApi()
	dashboard = await seedActiveDashboard(api, `E2E Text Markdown ${Date.now()}`)
})

test.afterAll(async () => {
	await removeSeededDashboard(api, dashboard)
	await api?.dispose()
})

/**
 * Seed placements, open the dashboard, and return their rendered content.
 *
 * @param page the page.
 * @param contents one content blob per placement.
 * @return the `.cn-text-widget__content` of each, in order, plus the ids.
 */
async function render(
	page: Page,
	...contents: Array<Record<string, unknown>>
): Promise<{ bodies: Locator[]; ids: number[] }> {
	const ids: number[] = []
	for (const content of contents) {
		ids.push(await seedText(api, dashboard!.id, content))
	}
	await gotoLaunchPad(page)
	const bodies = ids.map((id) => textWidget(page, id).locator('.cn-text-widget__content'))
	for (const body of bodies) {
		await expect(body).toBeAttached({ timeout: 15_000 })
	}
	return { bodies, ids }
}

/**
 * A markdown placement's rendered content.
 *
 * @param page the page.
 * @param text the markdown text.
 * @return the `.cn-text-widget__content`.
 */
async function markdown(page: Page, text: string): Promise<Locator> {
	const { bodies } = await render(page, { text, contentMode: 'markdown' })
	await expect(bodies[0]).toHaveClass(/cn-text-widget__content--markdown/)
	return bodies[0]
}

/**
 * The tag names of an element's children, upper-case.
 *
 * @param body the element.
 * @return the child tag names.
 */
async function childTags(body: Locator): Promise<string[]> {
	return body.evaluate((el) => Array.from(el.children).map((c) => c.tagName))
}

test.describe('text widget — contentMode field (REQ-TXMD-001)', () => {
	test.describe.configure({ timeout: 90_000 })

	// @e2e text-display-widget::existing-widgets-retain-html-mode
	test('a placement with no contentMode renders as HTML, and again after reload', async ({
		page,
	}) => {
		const { bodies, ids } = await render(page, { text: '# Legacy <b>bold</b>' })
		const body = bodies[0]
		await expect(body).toHaveClass(/cn-text-widget__content--html/)
		await expect(body.locator('b')).toHaveText('bold')
		await expect(body.locator('h1')).toHaveCount(0)
		await expect(body).toHaveText('# Legacy bold')
		expect((await storedContent(api, dashboard!.id, ids[0])).contentMode).toBeUndefined()

		await page.reload()
		const again = textWidget(page, ids[0]).locator('.cn-text-widget__content')
		await expect(again).toHaveText('# Legacy bold', { timeout: 15_000 })
		await expect(again.locator('b')).toHaveCount(1)
		await expect(again.locator('h1')).toHaveCount(0)
	})

	// @e2e text-display-widget::new-widget-receives-the-configured-default
	// @e2e text-display-widget::new-widgets-default-to-the-configured-mode
	// @e2e text-display-widget::default-is-markdown-when-nothing-else-applies
	test('a new text widget opens in Markdown, is stored as markdown and renders parsed', async ({
		page,
	}) => {
		await gotoLaunchPad(page)
		const { dialog, form } = await openAddTextForm(page)
		// No override is configured: the Mode select defaults to Markdown.
		await expect.poll(() => selectedOption(ncSelect(form, 'Mode'))).toBe('Markdown')
		await textArea(form).fill('# Fresh heading')
		const { id } = await submitAdd(page, dialog, api, dashboard!.id)

		expect((await storedContent(api, dashboard!.id, id)).contentMode).toBe('markdown')
		const body = textWidget(page, id).locator('.cn-text-widget__content')
		await expect(body.locator('h1')).toHaveText('Fresh heading', { timeout: 10_000 })

		// Subsequent renders parse it as markdown too.
		await page.reload()
		await expect(
			textWidget(page, id).locator('.cn-text-widget__content h1'),
		).toHaveText('Fresh heading', { timeout: 15_000 })
	})

	// @e2e text-display-widget::widget-can-be-manually-switched-to-html-mode
	// @e2e text-display-widget::toggling-mode-preserves-text-content
	test('switching a markdown widget to HTML keeps the text and stops parsing it', async ({
		page,
	}) => {
		const id = await seedText(api, dashboard!.id, { text: '# Heading', contentMode: 'markdown' })
		await gotoLaunchPad(page)
		const before = textWidget(page, id).locator('.cn-text-widget__content')
		await expect(before.locator('h1')).toHaveText('Heading', { timeout: 15_000 })

		const { dialog, form } = await openEditTextForm(page, id)
		const mode = ncSelect(form, 'Mode')
		await expect.poll(() => selectedOption(mode)).toBe('Markdown')
		await chooseOption(mode, 'HTML')
		// The text field is untouched by the switch.
		await expect(textArea(form)).toHaveValue('# Heading')

		const { body } = await submitEdit(page, dialog, id)
		expect(body.content?.contentMode).toBe('html')
		expect(body.content?.text).toBe('# Heading')
		const stored = await storedContent(api, dashboard!.id, id)
		expect(stored.contentMode).toBe('html')
		expect(stored.text).toBe('# Heading')

		// Re-rendered as HTML: the `#` is literal and no heading appears.
		const after = textWidget(page, id).locator('.cn-text-widget__content')
		await expect(after).toHaveText('# Heading', { timeout: 10_000 })
		await expect(after.locator('h1')).toHaveCount(0)
		await page.reload()
		const reloaded = textWidget(page, id).locator('.cn-text-widget__content')
		await expect(reloaded).toHaveText('# Heading', { timeout: 15_000 })
		await expect(reloaded.locator('h1')).toHaveCount(0)
	})

	// @e2e text-display-widget::explicit-html-mode-overrides-the-default
	test('choosing HTML before saving a new widget stores html', async ({ page }) => {
		await gotoLaunchPad(page)
		const { dialog, form } = await openAddTextForm(page)
		const mode = ncSelect(form, 'Mode')
		await expect.poll(() => selectedOption(mode)).toBe('Markdown')
		await chooseOption(mode, 'HTML')
		await textArea(form).fill('# Not a heading')
		const { id } = await submitAdd(page, dialog, api, dashboard!.id)

		expect((await storedContent(api, dashboard!.id, id)).contentMode).toBe('html')
		const body = textWidget(page, id).locator('.cn-text-widget__content')
		await expect(body).toHaveText('# Not a heading', { timeout: 10_000 })
		await expect(body.locator('h1')).toHaveCount(0)
	})
})

test.describe('text widget — CommonMark parsing (REQ-TXMD-002)', () => {
	// @e2e text-display-widget::heading-shortcuts-render-to-semantic-heading-tags
	test('#, ## and ### become h1, h2, h3 in order, and h4..h6 are supported too', async ({
		page,
	}) => {
		const { bodies } = await render(
			page,
			{ text: '# Main\n## Sub\n### Deep', contentMode: 'markdown' },
			{ text: '#### Four\n##### Five\n###### Six', contentMode: 'markdown' },
		)
		expect(await childTags(bodies[0])).toEqual(['H1', 'H2', 'H3'])
		await expect(bodies[0].locator('h1')).toHaveText('Main')
		await expect(bodies[0].locator('h2')).toHaveText('Sub')
		await expect(bodies[0].locator('h3')).toHaveText('Deep')
		expect(await childTags(bodies[1])).toEqual(['H4', 'H5', 'H6'])
		await expect(bodies[1].locator('h4')).toHaveText('Four')
		await expect(bodies[1].locator('h5')).toHaveText('Five')
		await expect(bodies[1].locator('h6')).toHaveText('Six')
	})

	// @e2e text-display-widget::emphasis-marks-render-to-inline-tags
	test('**, * and *** become strong, em and nested strong/em, styled as such', async ({
		page,
	}) => {
		const body = await markdown(page, '**bold** and *italic* and ***both***')
		const html = await body.innerHTML()
		expect(html).toContain('<strong>bold</strong>')
		expect(html).toContain('<em>italic</em>')
		const both = body.locator('strong em, em strong')
		await expect(both).toHaveText('both')
		await expect(body).toHaveText('bold and italic and both')

		const weight = (l: Locator) => l.evaluate((el) => Number(getComputedStyle(el).fontWeight))
		const style = (l: Locator) => l.evaluate((el) => getComputedStyle(el).fontStyle)
		expect(await weight(body.locator('strong').first())).toBeGreaterThanOrEqual(600)
		expect(await style(body.locator('em').first())).toBe('italic')
		expect(await weight(both)).toBeGreaterThanOrEqual(600)
		expect(await style(both)).toBe('italic')
	})

	// @e2e text-display-widget::inline-code-renders-to-code-tag
	test('backticks become a styled <code> that is inert text', async ({ page }) => {
		const body = await markdown(page, 'Use `npm install` to set up')
		const code = body.locator('code')
		await expect(code).toHaveCount(1)
		await expect(code).toHaveText('npm install')
		await expect(body).toHaveText('Use npm install to set up')
		const font = await code.evaluate((el) => getComputedStyle(el).fontFamily.toLowerCase())
		expect(font).toMatch(/mono/)
		await expect(body.locator('script')).toHaveCount(0)
	})

	// @e2e text-display-widget::links-preserve-href-and-render-anchor-tag
	test('[label](url) becomes a clickable anchor with that href', async ({ page }) => {
		const body = await markdown(page, '[OpenRegister](https://openregister.nl)')
		const link = body.locator('a')
		await expect(link).toHaveCount(1)
		await expect(link).toHaveText('OpenRegister')
		await expect(link).toHaveAttribute('href', 'https://openregister.nl')
		await link.scrollIntoViewIfNeeded()
		await expect(link).toBeVisible()
		// Actionability without navigating away: the link receives the click.
		await link.click({ trial: true })
	})

	// @e2e text-display-widget::bullet-lists-render-as-ul-li
	test('- items become a ul with three li, and indentation nests a list', async ({ page }) => {
		const { bodies } = await render(
			page,
			{ text: '- Item A\n- Item B\n- Item C', contentMode: 'markdown' },
			{ text: '- Parent\n  - Child\n- Sibling', contentMode: 'markdown' },
		)
		const list = bodies[0].locator(':scope > ul')
		await expect(list).toHaveCount(1)
		await expect(list.locator(':scope > li')).toHaveText(['Item A', 'Item B', 'Item C'])
		const nested = bodies[1].locator(':scope > ul > li > ul > li')
		await expect(nested).toHaveText(['Child'])
		await expect(bodies[1].locator(':scope > ul > li')).toHaveCount(2)
	})

	// @e2e text-display-widget::ordered-lists-render-as-ol-li
	test('1. 2. 3. become an ol with three li in sequence', async ({ page }) => {
		const body = await markdown(page, '1. First\n2. Second\n3. Third')
		const list = body.locator(':scope > ol')
		await expect(list).toHaveCount(1)
		await expect(list.locator(':scope > li')).toHaveText(['First', 'Second', 'Third'])
	})

	// @e2e text-display-widget::block-quotes-render-as-blockquote
	test('> lines become a blockquote holding the quoted text', async ({ page }) => {
		const body = await markdown(page, '> This is a quote\n> from someone')
		const quote = body.locator('blockquote')
		await expect(quote).toHaveCount(1)
		await expect(quote).toHaveText(/This is a quote\s+from someone/)
		await expect(body).not.toContainText('>')
	})

	// @e2e text-display-widget::tables-render-to-semantic-table-markup
	test('a GFM table becomes table/thead/tbody/tr/th/td', async ({ page }) => {
		const body = await markdown(page, '| Header A | Header B |\n|---|---|\n| Cell A1 | Cell B1 |')
		const table = body.locator('table')
		await expect(table).toHaveCount(1)
		await expect(table.locator('thead tr th')).toHaveText(['Header A', 'Header B'])
		await expect(table.locator('tbody tr td')).toHaveText(['Cell A1', 'Cell B1'])
		await expect(table.locator('tr')).toHaveCount(2)
	})
})

test.describe('text widget — sanitising parsed markdown (REQ-TXMD-003)', () => {
	// @e2e text-display-widget::script-tags-in-markdown-are-stripped
	test('a <script> is stripped and the markdown bold after it is parsed', async ({ page }) => {
		const body = await markdown(page, '<script>window.__lpMdScript = 1</script> and **bold**')
		await expect(body.locator('script')).toHaveCount(0)
		await expect(body).toHaveText('and bold')
		await expect(body.locator('strong')).toHaveText('bold')
		expect(await body.innerHTML()).not.toContain('__lpMdScript')
		expect(await page.evaluate(() => (window as any).__lpMdScript)).toBeUndefined()
	})

	// @e2e text-display-widget::event-handlers-are-removed-from-parsed-elements
	test('a javascript: markdown link loses its href', async ({ page }) => {
		const body = await markdown(page, '[Click me](javascript:alert(1))')
		await expect(body).toContainText('Click me')
		await expect(body.locator('a[href^="javascript:" i]')).toHaveCount(0)
		for (const link of await body.locator('a').all()) {
			expect(await link.getAttribute('href') ?? '').not.toMatch(/^\s*javascript:/i)
		}
	})

	// @e2e text-display-widget::data-attributes-are-removed-from-links
	test('a data-* attribute on an inline HTML link is stripped, the href kept', async ({ page }) => {
		const body = await markdown(
			page,
			"<a href='http://example.com' data-malicious='xss'>link</a>",
		)
		const link = body.locator('a')
		await expect(link).toHaveCount(1)
		await expect(link).toHaveText('link')
		await expect(link).toHaveAttribute('href', 'http://example.com')
		expect(
			await link.getAttribute('data-malicious'),
			'the sanitiser must remove data-* attributes',
		).toBeNull()
	})

	// @e2e text-display-widget::target-blank-links-get-rel-attribute
	test('a target="_blank" link keeps target and gains rel="noopener noreferrer"', async ({
		page,
	}) => {
		const body = await markdown(page, '<a href="https://example.com" target="_blank">x</a>')
		const link = body.locator('a')
		await expect(link).toHaveCount(1)
		await expect(link).toHaveAttribute('target', '_blank')
		await expect(link).toHaveAttribute('rel', 'noopener noreferrer')
	})
})

test.describe('text widget — Mode toggle in the form (REQ-TXMD-004/005)', () => {
	test.describe.configure({ timeout: 90_000 })

	// @e2e text-display-widget::html-mode-is-shown-by-default-for-existing-widgets
	test('the edit form shows HTML for a widget with no contentMode or with html', async ({
		page,
	}) => {
		const legacy = await seedText(api, dashboard!.id, { text: 'legacy' })
		const explicit = await seedText(api, dashboard!.id, { text: 'explicit', contentMode: 'html' })
		await gotoLaunchPad(page)

		for (const id of [legacy, explicit]) {
			const { dialog, form } = await openEditTextForm(page, id)
			await expect.poll(() => selectedOption(ncSelect(form, 'Mode'))).toBe('HTML')
			await dialog.getByRole('button', { name: 'Cancel', exact: true }).click()
			await expect(dialog).toBeHidden({ timeout: 5_000 })
		}
	})

	// @e2e text-display-widget::markdown-mode-is-shown-for-markdown-mode-widgets
	test('the edit form shows Markdown for a markdown widget', async ({ page }) => {
		const id = await seedText(api, dashboard!.id, { text: 'md', contentMode: 'markdown' })
		await gotoLaunchPad(page)
		const { form } = await openEditTextForm(page, id)
		await expect.poll(() => selectedOption(ncSelect(form, 'Mode'))).toBe('Markdown')
	})

	// @e2e text-display-widget::mode-change-is-saved-with-other-form-fields
	test('HTML → Markdown is sent in the PUT with the other fields and renders on reload', async ({
		page,
	}) => {
		const id = await seedText(api, dashboard!.id, {
			text: '**strong** words',
			contentMode: 'html',
			backgroundColor: '',
			textAlign: 'center',
		})
		await gotoLaunchPad(page)
		const before = textWidget(page, id).locator('.cn-text-widget__content')
		await expect(before).toHaveText('**strong** words', { timeout: 15_000 })

		const { dialog, form } = await openEditTextForm(page, id)
		await chooseOption(ncSelect(form, 'Mode'), 'Markdown')
		const { body } = await submitEdit(page, dialog, id)
		expect(body.content?.contentMode).toBe('markdown')
		// Saved WITH the other form fields, not as a lone patch.
		expect(body.content?.text).toBe('**strong** words')
		expect(body.content?.textAlign).toBe('center')
		expect(body.content).toHaveProperty('fontSize')
		expect(body.content).toHaveProperty('backgroundColor')

		await page.reload()
		const after = textWidget(page, id).locator('.cn-text-widget__content')
		await expect(after).toHaveClass(/cn-text-widget__content--markdown/, { timeout: 15_000 })
		await expect(after.locator('strong')).toHaveText('strong')
		await expect(after).toHaveText('strong words')
	})

	// @e2e text-display-widget::invalid-mode-writes-are-rejected
	test('an unsupported mode can neither be chosen nor saved', async ({ page }) => {
		await gotoLaunchPad(page)
		const { dialog, form } = await openAddTextForm(page)
		const mode = ncSelect(form, 'Mode')
		await expect.poll(() => selectedOption(mode)).toBe('Markdown')
		// Try to write 'rst' through the select: it offers no such value.
		await mode.getByRole('combobox').first().click()
		await page.keyboard.type('rst')
		await expect(
			page.locator('.vs__dropdown-menu .vs__dropdown-option').filter({ hasText: /rst/i }),
		).toHaveCount(0)
		await page.keyboard.press('Enter')
		await textArea(form).click()
		await expect.poll(() => selectedOption(mode)).toBe('Markdown')
		await textArea(form).fill('typed after the attempt')
		const { id } = await submitAdd(page, dialog, api, dashboard!.id)
		expect((await storedContent(api, dashboard!.id, id)).contentMode).toBe('markdown')

		// A placement that already carries an invalid mode is not saved back with it.
		const bad = await seedText(api, dashboard!.id, { text: 'odd', contentMode: 'rst' })
		await gotoLaunchPad(page)
		const edit = await openEditTextForm(page, bad)
		await textArea(edit.form).fill('odd, edited')
		const { body } = await submitEdit(page, edit.dialog, bad)
		expect(['html', 'markdown']).toContain(body.content?.contentMode)
		expect(['html', 'markdown']).toContain(
			(await storedContent(api, dashboard!.id, bad)).contentMode,
		)
	})
})

test.describe('text widget — HTML mode unchanged (REQ-TXMD-006)', () => {
	// @e2e text-display-widget::existing-widget-renders-unchanged
	test('a legacy {text: "<b>bold</b>"} renders exactly <b>bold</b>, in bold', async ({ page }) => {
		const { bodies } = await render(page, { text: '<b>bold</b>' })
		const body = bodies[0]
		expect((await body.innerHTML()).trim()).toBe('<b>bold</b>')
		const weight = await body
			.locator('b')
			.evaluate((el) => Number(getComputedStyle(el).fontWeight))
		expect(weight).toBeGreaterThanOrEqual(600)
	})

	// @e2e text-display-widget::html-mode-ignores-markdown-syntax
	test('html mode shows "# Heading" literally, with no h1', async ({ page }) => {
		const { bodies } = await render(page, { text: '# Heading', contentMode: 'html' })
		await expect(bodies[0]).toHaveText('# Heading')
		await expect(bodies[0].locator('h1')).toHaveCount(0)
	})

	// @e2e text-display-widget::existing-sanitisation-rules-apply-to-html-mode
	test('html mode strips a <script>', async ({ page }) => {
		const id = await seedText(api, dashboard!.id, {
			text: '<script>window.__lpHtmlScript = 1</script>',
			contentMode: 'html',
		})
		await gotoLaunchPad(page)
		// Nothing but the script: once it is stripped the widget may render an
		// empty body or the empty placeholder; either way, no script.
		const widget = textWidget(page, id)
		await expect(widget).toBeAttached({ timeout: 15_000 })
		await expect(widget.locator('script')).toHaveCount(0)
		expect(await widget.innerHTML()).not.toContain('__lpHtmlScript')
		expect(await page.evaluate(() => (window as any).__lpHtmlScript)).toBeUndefined()
	})
})

test.describe('text widget — heading levels (REQ-TXMD-007)', () => {
	// @e2e text-display-widget::single-hash-produces-h1
	test('# Main Title is exactly <h1>Main Title</h1>', async ({ page }) => {
		const body = await markdown(page, '# Main Title')
		expect((await body.innerHTML()).trim()).toBe('<h1>Main Title</h1>')
	})

	// @e2e text-display-widget::double-hash-produces-h2
	test('## Subsection is exactly <h2>Subsection</h2>', async ({ page }) => {
		const body = await markdown(page, '## Subsection')
		expect((await body.innerHTML()).trim()).toBe('<h2>Subsection</h2>')
	})

	// @e2e text-display-widget::up-to-six-hashes-for-h6
	test('###### Tiny is exactly <h6>Tiny</h6>', async ({ page }) => {
		const body = await markdown(page, '###### Tiny')
		expect((await body.innerHTML()).trim()).toBe('<h6>Tiny</h6>')
	})

	// @e2e text-display-widget::seven-or-more-hashes-treated-as-literal-text
	test('####### Too many is a paragraph, not a heading', async ({ page }) => {
		const body = await markdown(page, '####### Too many')
		expect(await childTags(body)).toEqual(['P'])
		await expect(body.locator('p')).toHaveText('####### Too many')
		await expect(body.locator('h1, h2, h3, h4, h5, h6')).toHaveCount(0)
	})

	// @e2e text-display-widget::heading-followed-by-content
	test('headings and paragraphs interleave as h1, p, h2, p', async ({ page }) => {
		const body = await markdown(page, '# Title\nThis is content.\n## Subsection\nMore content.')
		expect(await childTags(body)).toEqual(['H1', 'P', 'H2', 'P'])
		await expect(body.locator(':scope > *')).toHaveText([
			'Title',
			'This is content.',
			'Subsection',
			'More content.',
		])
	})
})
