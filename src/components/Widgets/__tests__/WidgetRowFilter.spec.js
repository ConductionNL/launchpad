/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * In-widget search (REQ-DWMS-008): the filter narrows the rendered rows, lives
 * in view state only, is gone after a reload, and never touches the widget's
 * stored definition.
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => ({
	translate: (_app, text, vars = {}) =>
		text.replace(/\{(\w+)\}/g, (_m, k) => vars[k] ?? `{${k}}`),
}))
vi.mock('../../../utils/logger.js', () => ({ logger: { error: vi.fn() } }))

const loadAttentionFeed = vi.fn()
vi.mock('../../../services/attentionFeed.js', () => ({
	loadAttentionFeed: (...args) => loadAttentionFeed(...args),
}))

import AttentionWidget from '../Renderers/AttentionWidget.vue'
import WidgetRowFilter from '../WidgetRowFilter.vue'

function item(appId, title) {
	return {
		key: `${appId}/x`,
		id: 'x',
		appId,
		appName: appId,
		title,
		severity: 'warning',
		count: 1,
		reason: '1 thing needs you.',
		actionLabel: 'Open',
		href: `/apps/${appId}`,
	}
}

const FEED = {
	items: [item('dossiq', 'Open zaken'), item('pipelinq', 'Leads'), item('decidiq', 'Open besluiten')],
	failedApps: [],
	sourceCount: 3,
}

async function mountWidget(content) {
	loadAttentionFeed.mockResolvedValue(structuredClone(FEED))
	const wrapper = mount(AttentionWidget, { props: { content } })
	await flushPromises()
	return wrapper
}

function titles(wrapper) {
	// The title starts with a screen-reader severity label; read past it.
	return wrapper.findAll('.attention-widget__title').map((t) => t.text().replace(t.find('.attention-widget__sr').text(), '').trim())
}

beforeEach(() => {
	loadAttentionFeed.mockReset()
})

describe('WidgetRowFilter', () => {
	it('emits what is typed, and Escape empties it', async () => {
		const wrapper = mount(WidgetRowFilter, { props: { modelValue: '' } })
		const input = wrapper.find('input')
		expect(input.attributes('aria-label')).toBe('Filter these results')

		await input.setValue('zaak')
		await input.trigger('keydown', { key: 'Escape' })

		expect(wrapper.emitted('update:modelValue')).toEqual([['zaak'], ['']])
	})
})

describe('in-widget search on a widget that renders rows', () => {
	it('filters the rows before a reload and shows them all after it, leaving the definition alone', async () => {
		const content = Object.freeze({ limit: 5 })
		const before = JSON.stringify(content)

		const first = await mountWidget(content)
		expect(titles(first)).toEqual(['Open zaken', 'Leads', 'Open besluiten'])

		await first.find('[data-testid="widget-row-filter"]').setValue('open')
		expect(titles(first)).toEqual(['Open zaken', 'Open besluiten'])
		expect(first.emitted()).not.toHaveProperty('update:content')
		first.unmount()

		// A reload is a fresh mount: the term was never stored anywhere.
		const reloaded = await mountWidget(content)
		expect(reloaded.find('[data-testid="widget-row-filter"]').element.value).toBe('')
		expect(titles(reloaded)).toEqual(['Open zaken', 'Leads', 'Open besluiten'])
		expect(JSON.stringify(content)).toBe(before)
		expect(loadAttentionFeed).toHaveBeenCalledTimes(2)
		for (const call of loadAttentionFeed.mock.calls) {
			expect(call).toEqual([])
		}
	})

	it('counts the hidden remainder over the filtered rows', async () => {
		const wrapper = await mountWidget({ limit: 1 })
		expect(wrapper.find('[data-testid="attention-more"]').text()).toBe('And 2 more.')

		await wrapper.find('[data-testid="widget-row-filter"]').setValue('open')
		expect(titles(wrapper)).toEqual(['Open zaken'])
		expect(wrapper.find('[data-testid="attention-more"]').text()).toBe('And 1 more.')
	})
})
