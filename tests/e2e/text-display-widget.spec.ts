/*
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the `text` widget: HTML sanitisation,
 * styling, the empty placeholder, the add/edit sub-form and the cell layout
 * (REQ-TXT-001..005 of openspec/specs/text-display-widget), written for
 * e2e-coverage-programme.
 *
 * Rendering scenarios seed a placement through the API with exactly the
 * `content` the scenario names and assert the DOM the dashboard draws for it
 * (`.launchpad-grid-item[data-placement-id] .cn-text-widget`). Form scenarios
 * drive the real Add/Edit Widget modal: More → "Add widget" → "Custom widget" for a new
 * widget, edit mode → right-click → Edit for a placed one.
 *
 * Markdown mode lives in text-display-widget-markdown.spec.ts, table mode in
 * text-display-widget-table.spec.ts.
 *
 * Gate-19 @e2e traceability:
 *   @e2e text-display-widget::allow-safe-formatting
 *   @e2e text-display-widget::strip-script-tag
 *   @e2e text-display-widget::strip-event-handler-attribute
 *   @e2e text-display-widget::strip-javascript-url
 *   @e2e text-display-widget::custom-font-size-and-colour
 *   @e2e text-display-widget::theme-aware-default-colour
 *   @e2e text-display-widget::free-form-font-size-accepted
 *   @e2e text-display-widget::empty-content-shows-placeholder
 *   @e2e text-display-widget::whitespace-only-content-treated-as-empty
 *   @e2e text-display-widget::form-rejects-empty-text
 *   @e2e text-display-widget::form-pre-fills-in-edit-mode
 *   @e2e text-display-widget::form-emits-content-updates-reactively
 *   @e2e text-display-widget::overflow-scrolls-within-the-cell
 *   @e2e text-display-widget::short-content-vertically-centred
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
	openAddTextForm,
	openEditTextForm,
	seedText,
	storedContent,
	submitAdd,
	textArea,
	textWidget,
} from './support/textWidget.ts'

let api: APIRequestContext
let dashboard: SeededDashboard | null = null

let restoreWidgetRestriction: (() => Promise<void>) | null = null

test.beforeAll(async () => {
	restoreWidgetRestriction = await ensureDefaultWidgetRestriction()
	api = await adminApi()
	dashboard = await seedActiveDashboard(api, `E2E Text ${Date.now()}`)
})

test.afterAll(async () => {
	await restoreWidgetRestriction?.()
})

test.afterAll(async () => {
	await removeSeededDashboard(api, dashboard)
	await api?.dispose()
})

/**
 * Seed a placement, open the dashboard, and return its rendered widget.
 *
 * @param page the page.
 * @param content the placement content.
 * @param size grid size.
 * @param size.w grid width.
 * @param size.h grid height.
 * @return the widget root and the placement id.
 */
async function render(
	page: Page,
	content: Record<string, unknown>,
	size?: { w: number; h: number },
): Promise<{ widget: Locator; id: number }> {
	const id = await seedText(api, dashboard!.id, content, size)
	await gotoLaunchPad(page)
	const widget = textWidget(page, id)
	await expect(widget).toBeAttached({ timeout: 15_000 })
	return { widget, id }
}

/**
 * Whether an element sits fully inside its text widget's visible box.
 *
 * @param line the element.
 * @return true when inside.
 */
async function insideWidget(line: Locator): Promise<boolean> {
	return line.evaluate((el) => {
		const box = el.getBoundingClientRect()
		const frame = el.closest('.cn-text-widget')!.getBoundingClientRect()
		return box.top >= frame.top - 1 && box.bottom <= frame.bottom + 1
	})
}

/**
 * Assert the placeholder state of a rendered widget.
 *
 * @param widget the widget root.
 * @return nothing.
 */
async function expectPlaceholder(widget: Locator): Promise<void> {
	const placeholder = widget.locator('.cn-text-widget__placeholder')
	await expect(placeholder).toBeVisible()
	await expect(placeholder).toHaveText('No text content')
	await expect(widget.locator('.cn-text-widget__content')).toHaveCount(0)
	const style = await placeholder.evaluate((el) => ({
		fontStyle: el.style.fontStyle,
		color: el.style.color,
	}))
	expect(style.fontStyle).toBe('italic')
	expect(style.color).toBe('var(--color-text-maxcontrast)')
	const wrapper = await widget.evaluate((el) => ({ width: el.style.width, height: el.style.height }))
	expect(wrapper).toEqual({ width: '100%', height: '100%' })
}

test.describe('text widget — HTML sanitisation (REQ-TXT-001)', () => {
	// @e2e text-display-widget::allow-safe-formatting
	test('safe <b> survives as a real bold element', async ({ page }) => {
		const { widget } = await render(page, { text: 'Hello <b>world</b>', contentMode: 'html' })
		const content = widget.locator('.cn-text-widget__content')
		await expect(content).toHaveText('Hello world')
		expect(await content.innerHTML()).toContain('<b>world</b>')
		const bold = content.locator('b')
		await expect(bold).toHaveCount(1)
		await expect(bold).toHaveText('world')
		const weight = await bold.evaluate((el) => Number(getComputedStyle(el).fontWeight))
		expect(weight, 'the <b> must actually render bold').toBeGreaterThanOrEqual(600)
	})

	// @e2e text-display-widget::strip-script-tag
	test('a <script> and its contents are stripped', async ({ page }) => {
		const { widget } = await render(page, {
			text: 'Click <script>window.__lpTextScript = 1</script> me',
			contentMode: 'html',
		})
		const content = widget.locator('.cn-text-widget__content')
		await expect(content).toHaveText(/^Click\s+me$/)
		await expect(widget.locator('script')).toHaveCount(0)
		expect(await content.innerHTML()).not.toContain('__lpTextScript')
		expect(await page.evaluate(() => (window as any).__lpTextScript)).toBeUndefined()
	})

	// @e2e text-display-widget::strip-event-handler-attribute
	test('an onclick attribute is stripped while href survives', async ({ page }) => {
		const { widget } = await render(page, {
			text: '<a href="x" onclick="alert(1)">x</a>',
			contentMode: 'html',
		})
		const link = widget.locator('.cn-text-widget__content a')
		await expect(link).toHaveCount(1)
		expect(await link.getAttribute('onclick')).toBeNull()
		await expect(link).toHaveAttribute('href', 'x')
	})

	// @e2e text-display-widget::strip-javascript-url
	test('a javascript: href is stripped', async ({ page }) => {
		const { widget } = await render(page, {
			text: '<a href="javascript:alert(1)">x</a>',
			contentMode: 'html',
		})
		const link = widget.locator('.cn-text-widget__content a')
		await expect(link).toHaveCount(1)
		const href = await link.getAttribute('href')
		expect(href ?? '', 'the href must not start with javascript:').not.toMatch(/^\s*javascript:/i)
	})
})

test.describe('text widget — styles with theme fallbacks (REQ-TXT-002)', () => {
	// @e2e text-display-widget::custom-font-size-and-colour
	test('custom font size and colour apply inline; background and alignment fall back', async ({
		page,
	}) => {
		const { widget } = await render(page, { text: 'X', fontSize: '24px', color: '#ff0000' })
		const content = widget.locator('.cn-text-widget__content')
		const inline = await content.evaluate((el) => ({
			fontSize: el.style.fontSize,
			color: el.style.color,
			textAlign: el.style.textAlign,
		}))
		expect(inline.fontSize).toBe('24px')
		// The browser normalises the inline #ff0000 to rgb().
		expect(inline.color).toBe('rgb(255, 0, 0)')
		expect(inline.textAlign).toBe('left')
		expect(await widget.evaluate((el) => el.style.backgroundColor)).toBe('transparent')
		expect(await content.evaluate((el) => getComputedStyle(el).fontSize)).toBe('24px')
		expect(await content.evaluate((el) => getComputedStyle(el).color)).toBe('rgb(255, 0, 0)')
	})

	// @e2e text-display-widget::theme-aware-default-colour
	test('an empty colour falls back to the theme main-text variable', async ({ page }) => {
		const { widget } = await render(page, { text: 'X', color: '' })
		const content = widget.locator('.cn-text-widget__content')
		expect(await content.evaluate((el) => el.style.color)).toBe('var(--color-main-text)')
		// And the variable actually resolves to the theme's own text colour.
		const [shown, theme] = await content.evaluate((el) => {
			const probe = document.createElement('span')
			probe.style.color = 'var(--color-main-text)'
			document.body.appendChild(probe)
			const resolved = getComputedStyle(probe).color
			probe.remove()
			return [getComputedStyle(el).color, resolved]
		})
		expect(shown).toBe(theme)
	})

	// @e2e text-display-widget::free-form-font-size-accepted
	test('a non-px font size is applied verbatim', async ({ page }) => {
		const { widget } = await render(page, { text: 'X', fontSize: '1.2em' })
		const content = widget.locator('.cn-text-widget__content')
		expect(await content.evaluate((el) => el.style.fontSize)).toBe('1.2em')
		// Not coerced: 1.2em of the wrapper's size, not 14px or 1.2px.
		const [own, parent] = await content.evaluate((el) => [
			Number.parseFloat(getComputedStyle(el).fontSize),
			Number.parseFloat(getComputedStyle(el.parentElement!).fontSize),
		])
		expect(own).toBeCloseTo(parent * 1.2, 1)
	})
})

test.describe('text widget — empty placeholder (REQ-TXT-003)', () => {
	// @e2e text-display-widget::empty-content-shows-placeholder
	test('empty text shows the italic, muted placeholder in a full-size wrapper', async ({
		page,
	}) => {
		const { widget } = await render(page, { text: '' })
		await expectPlaceholder(widget)
		// The wrapper fills its cell: as tall as the content box it sits in
		// (the renderer's own padding is not part of the cell).
		const [own, cell] = await widget.evaluate((el) => {
			const parent = getComputedStyle(el.parentElement!)
			return [
				Math.round(el.getBoundingClientRect().height),
				Math.round(
					el.parentElement!.clientHeight
					- Number.parseFloat(parent.paddingTop)
					- Number.parseFloat(parent.paddingBottom),
				),
			]
		})
		expect(own).toBeGreaterThan(0)
		expect(own).toBe(cell)
	})

	// @e2e text-display-widget::whitespace-only-content-treated-as-empty
	test('whitespace-only text shows the placeholder', async ({ page }) => {
		const { widget } = await render(page, { text: '   \n  ' })
		await expectPlaceholder(widget)
	})
})

test.describe('text widget — add/edit sub-form (REQ-TXT-004)', () => {
	test.describe.configure({ timeout: 90_000 })

	// @e2e text-display-widget::form-rejects-empty-text
	test('empty text: validate() reports "Text is required" and Add stays disabled', async ({
		page,
	}) => {
		await gotoLaunchPad(page)
		const { dialog, form } = await openAddTextForm(page)
		await expect(textArea(form)).toHaveValue('')
		const add = dialog.getByTestId('add-widget-save')
		await expect(add).toHaveText(/add/i)
		await expect(add).toBeDisabled({ timeout: 5_000 })
		// The modal surfaces validate()'s first error as the button's title.
		await expect(add).toHaveAttribute('title', 'Text is required')
		// Whitespace is still empty.
		await textArea(form).fill('   ')
		await expect(add).toBeDisabled()
		await expect(add).toHaveAttribute('title', 'Text is required')
	})

	// @e2e text-display-widget::form-pre-fills-in-edit-mode
	test('edit mode pre-fills text, font size and colour from the placement', async ({ page }) => {
		const id = await seedText(api, dashboard!.id, {
			text: 'Hi',
			fontSize: '20px',
			color: '#00ff00',
		})
		await gotoLaunchPad(page)
		const { dialog, form } = await openEditTextForm(page, id)
		await expect(dialog.getByTestId('add-widget-save')).toHaveText(/save/i)
		await expect(textArea(form)).toHaveValue('Hi')
		await expect(form.getByLabel('Font size', { exact: true })).toHaveValue('20px')
		// The colour control is CnColorPicker: a swatch painted with the value.
		const swatch = form
			.locator('.cn-text-widget-form__color-label')
			.filter({ hasText: 'Text color' })
			.locator('.cn-color-picker__swatch')
		await expect(swatch).toBeVisible()
		const paint = await swatch.evaluate((el) => getComputedStyle(el).backgroundImage)
		expect(paint, 'the text-colour swatch must show #00ff00').toContain('rgb(0, 255, 0)')
	})

	// @e2e text-display-widget::form-emits-content-updates-reactively
	test('typing Hello updates the modal content and makes the form valid', async ({ page }) => {
		await gotoLaunchPad(page)
		const { dialog, form } = await openAddTextForm(page)
		const add = dialog.getByTestId('add-widget-save')
		await expect(add).toBeDisabled({ timeout: 5_000 })
		await textArea(form).fill('Hello')
		// validate() now returns []: the button enables and loses its error title.
		await expect(add).toBeEnabled({ timeout: 5_000 })
		expect(await add.getAttribute('title')).toBeFalsy()
		// The parent received content.text === 'Hello': it is what gets stored.
		const { id } = await submitAdd(page, dialog, api, dashboard!.id)
		expect((await storedContent(api, dashboard!.id, id)).text).toBe('Hello')
		await expect(textWidget(page, id)).toHaveText(/Hello/)
	})

	test('add → fill → save → reload renders sanitised text and survives round-trip', async ({
		page,
	}) => {
		const marker = `Hello ${Date.now()}`
		await gotoLaunchPad(page)
		const { dialog, form } = await openAddTextForm(page)
		await textArea(form).fill(`${marker} <b>world</b>`)
		const { id } = await submitAdd(page, dialog, api, dashboard!.id)

		const placement = textWidget(page, id)
		await expect(placement).toContainText(marker, { timeout: 8_000 })
		await expect(placement.locator('b')).toHaveCount(1)

		await page.reload()
		await expect(textWidget(page, id)).toContainText(marker, { timeout: 15_000 })
	})
})

test.describe('text widget — cell layout (REQ-TXT-005)', () => {
	// @e2e text-display-widget::overflow-scrolls-within-the-cell
	test('long text scrolls inside the cell and both ends are reachable', async ({ page }) => {
		const lines = Array.from({ length: 60 }, (_, i) => `<p>line ${i + 1}</p>`).join('')
		const { widget } = await render(page, { text: lines, contentMode: 'html' }, { w: 4, h: 2 })
		await widget.scrollIntoViewIfNeeded()
		expect(await widget.evaluate((el) => getComputedStyle(el).overflowY)).toBe('auto')
		const sizes = await widget.evaluate((el) => ({ scroll: el.scrollHeight, client: el.clientHeight }))
		expect(sizes.scroll, 'the text must overflow the cell for this to mean anything').toBeGreaterThan(sizes.client)

		const first = widget.locator('p').first()
		const last = widget.locator('p').last()
		await widget.evaluate((el) => {
			el.scrollTop = 0
		})
		expect(await insideWidget(first), 'the first line must be reachable at the top').toBe(true)
		await widget.evaluate((el) => {
			el.scrollTop = el.scrollHeight
		})
		expect(await widget.evaluate((el) => el.scrollTop)).toBeGreaterThan(0)
		expect(await insideWidget(last), 'the last line must be reachable at the bottom').toBe(true)
	})

	// @e2e text-display-widget::short-content-vertically-centred
	test('one short line sits vertically centred in a tall cell', async ({ page }) => {
		const { widget } = await render(page, { text: 'Short', contentMode: 'html' }, { w: 4, h: 4 })
		await widget.scrollIntoViewIfNeeded()
		const [frame, text] = await widget.evaluate((el) => {
			const outer = el.getBoundingClientRect()
			const inner = el.querySelector('.cn-text-widget__content')!.getBoundingClientRect()
			return [
				{ top: outer.top, height: outer.height },
				{ top: inner.top, height: inner.height },
			]
		})
		expect(frame.height, 'the cell must be much taller than the line').toBeGreaterThan(text.height * 3)
		const frameMid = frame.top + frame.height / 2
		const textMid = text.top + text.height / 2
		expect(Math.abs(frameMid - textMid)).toBeLessThanOrEqual(3)
	})
})
