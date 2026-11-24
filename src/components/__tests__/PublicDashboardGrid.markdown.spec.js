/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A text widget on a public link or kiosk screen renders its Markdown,
 * sanitised as on the dashboard (REQ-PSHR-012).
 *
 * @spec openspec/changes/archive/2026-11-24-public-live-widgets/specs/dashboard-public-share/spec.md#requirement-text-in-markdown-on-public-screens-req-pshr-012
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import PublicDashboardGrid from '../PublicDashboardGrid.vue'

// The dashboard's own text widget, not the library stub, so the grid's text is real (REQ-PSHR-012).
vi.mock('@conduction/nextcloud-vue', async (importOriginal) => ({
	...(await importOriginal()),
	CnTextWidget: (await import('@conduction/nextcloud-vue/src/components/CnTextWidget/CnTextWidget.vue')).default,
}))

vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...(await importOriginal()),
	translate: (_app, text) => text,
}))

/**
 * Mount the grid with one text placement.
 *
 * @param {object} content The placement's content.
 * @return {object} The wrapper.
 */
function mountText(content) {
	return mount(PublicDashboardGrid, {
		props: { placements: [{ id: 1, widgetId: 'text', gridX: 0, gridY: 0, content }] },
	})
}

describe('PublicDashboardGrid text widgets', () => {
	it('shows a Markdown list as a list, not as raw text', () => {
		const wrapper = mountText({ contentMode: 'markdown', text: '- Monday 9:00 to 17:00\n- Tuesday *closed*' })
		const items = wrapper.findAll('li')
		expect(items).toHaveLength(2)
		expect(items[0].text()).toBe('Monday 9:00 to 17:00')
		expect(wrapper.find('em').text()).toBe('closed')
		expect(wrapper.text()).not.toContain('- Monday')
	})

	it('renders a Markdown link', () => {
		const wrapper = mountText({ contentMode: 'markdown', text: 'See [the city](https://example.org)' })
		expect(wrapper.find('a[href="https://example.org"]').text()).toBe('the city')
	})

	it('strips scripts and event handlers', () => {
		const wrapper = mountText({ contentMode: 'markdown', text: 'Hi <img src="x" onerror="alert(1)"><script>alert(2)</script>' })
		expect(wrapper.html()).not.toContain('onerror')
		expect(wrapper.html()).not.toContain('<script')
		expect(wrapper.text()).toContain('Hi')
	})

	it('falls back to a legacy body when the text is missing', () => {
		const wrapper = mountText({ body: 'Lunch at noon' })
		expect(wrapper.text()).toContain('Lunch at noon')
	})
})
