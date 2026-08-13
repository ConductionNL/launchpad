/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `NewsWidget.vue`'s summary truncation
 * (REQ-NEWS-004 / REQ-NEWS-005).
 *
 * Feed summaries are rendered through `v-html`, so how they are shortened
 * is a security property, not a formatting detail. The original
 * implementation sliced the sanitised HTML string by raw offset, which can
 * cut through a tag or an attribute — dropping the `rel="noopener
 * noreferrer"` that REQ-NEWS-005 forces onto every anchor, and leaving
 * unbalanced markup behind. These tests pin the corrected behaviour:
 * budget on VISIBLE characters, keep the markup well-formed, and
 * re-sanitise before render.
 */

import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'

vi.mock('@nextcloud/axios', () => ({
	default: { get: vi.fn().mockResolvedValue({ data: { items: [] } }) },
}))
vi.mock('@nextcloud/router', () => ({ generateUrl: (p) => `/index.php${p}` }))

let NewsWidget

beforeEach(async () => {
	globalThis.t = (_app, key) => key
	NewsWidget = (await import('../NewsWidget.vue')).default
})

/**
 * Mount the widget with a summary budget and read back the rendered
 * summary for a single item.
 *
 * @param {string} summary Server-sanitised summary HTML.
 * @param {number} summaryMaxChars Visible-character budget.
 * @return {string} The HTML the widget would hand to `v-html`.
 */
function renderSummary(summary, summaryMaxChars) {
	const wrapper = mount(NewsWidget, {
		propsData: {
			content: { showSummary: true, summaryMaxChars },
			placement: { id: 1 },
		},
	})
	return wrapper.vm.formattedSummary({ summary })
}

describe('NewsWidget — summary truncation (REQ-NEWS-004/005)', () => {
	it('budgets on visible characters, not markup bytes', () => {
		// 20 visible chars wrapped in markup far longer than the budget.
		const summary = '<p><strong>Budget</strong> counts text only</p>'
		const out = renderSummary(summary, 13)

		// The markup must not eat into the text allowance.
		const text = out.replace(/<[^>]*>/g, '').replace(/…$/, '')
		expect(text).toBe('Budget counts')
	})

	it('never emits a truncated tag or attribute', () => {
		// Visible text is "See the announcement now" (24 chars). A budget of
		// 10 falls INSIDE the anchor, which is exactly where a raw
		// `slice(0, 10)` would cut through `<a href="https://exa…`.
		const summary =
			'<p>See <a href="https://example.com/a/very/long/path">the announcement</a> now</p>'
		const out = renderSummary(summary, 10)

		// Every '<' that opens a tag must have a matching '>'.
		expect(out).not.toMatch(/<[^>]*$/)
		// No dangling attribute fragment.
		expect(out).not.toMatch(/href="[^"]*$/)
		// And the anchor that did survive is still complete.
		expect(out).toContain('</a>')
	})

	it('preserves rel="noopener noreferrer" on an anchor cut short by truncation', () => {
		// Budget 10 lands inside the anchor's own text, so the anchor is
		// kept but shortened — the hardening must survive that.
		const summary =
			'<p>See <a href="https://example.com">the announcement</a> and more after it</p>'
		const out = renderSummary(summary, 10)

		expect(out).toContain('<a')
		expect(out).toContain('rel="noopener noreferrer"')
	})

	it('adds rel to an anchor the server did not harden', () => {
		// Short enough that no truncation happens — rel is still enforced.
		const summary = '<a href="https://example.com">x</a>'
		const out = renderSummary(summary, 500)

		expect(out).toContain('rel="noopener noreferrer"')
	})

	it('strips tags outside the allow-list on the client too', () => {
		const summary =
			'<p>ok</p><script>alert(1)</script><img src=x onerror="alert(1)">'
		const out = renderSummary(summary, 500)

		expect(out).not.toContain('<script')
		expect(out).not.toContain('onerror')
		expect(out).not.toContain('<img')
		expect(out).toContain('ok')
	})

	it('leaves a summary shorter than the budget untouched apart from rel', () => {
		const summary = '<p>Short</p>'
		const out = renderSummary(summary, 500)

		expect(out).toContain('Short')
		expect(out).not.toContain('…')
	})

	it('returns the empty string for a missing summary', () => {
		expect(renderSummary('', 100)).toBe('')
	})
})
