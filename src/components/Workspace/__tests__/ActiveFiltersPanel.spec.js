/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * ActiveFiltersPanel: one chip per clause, the source widget named for a
 * widget click, a lock and no remove button for a pinned clause, absent while
 * empty, and "Reset filters" only when there is something to clear
 * (drill-down-cross-widget-filter tasks 5 and 6; REQ-CWF-006, REQ-CWF-007).
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import ActiveFiltersPanel from '../ActiveFiltersPanel.vue'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app, text, vars = {}) => text.replace(/{(\w+)}/g, (_match, key) => vars[key] ?? `{${key}}`),
}))

vi.mock('@nextcloud/vue', () => ({
	NcButton: { name: 'NcButton', emits: ['click'], template: '<button data-testid="reset-filters" @click="$emit(\'click\')"><slot /></button>' },
}))

const CLICK = { dimension: 'gemeenteCode', operator: 'eq', value: 'GM0355', source: 'widgetClick', sourceWidgetId: 'w-gemeenten', pinned: false }
const BOOKMARK = { dimension: 'status', operator: 'in', value: ['open', 'paused'], source: 'urlBootstrap', sourceWidgetId: null, pinned: false }
const PINNED = { dimension: 'period.month', operator: 'between', value: ['2026-01', '2026-03'], source: 'userExplicit', sourceWidgetId: null, pinned: true }

/**
 * Mount the panel over clauses.
 *
 * @param {Array<object>} clauses The clauses.
 * @return {import('@vue/test-utils').VueWrapper}
 */
function panel(clauses) {
	return mount(ActiveFiltersPanel, {
		props: {
			clauses,
			labels: { gemeenteCode: 'Gemeente', 'period.month': 'Period' },
			widgetNames: { 'w-gemeenten': 'Inwoners per gemeente' },
		},
	})
}

describe('ActiveFiltersPanel', () => {
	it('REQ-CWF-007: renders nothing while the bus is empty', () => {
		expect(panel([]).find('[data-testid="active-filters"]').exists()).toBe(false)
	})

	it('REQ-CWF-007: shows each clause with its label, value and source', () => {
		const wrapper = panel([CLICK, BOOKMARK, PINNED])
		const chips = wrapper.findAll('.active-filters__chip')

		expect(chips).toHaveLength(3)
		expect(chips[0].text()).toContain('Gemeente:')
		expect(chips[0].text()).toContain('GM0355')
		expect(chips[0].find('[data-testid="active-filter-source"]').text()).toBe('(Inwoners per gemeente)')
		// A restored clause names no source; its dimension stands in for a missing label.
		expect(chips[1].text()).toContain('status:')
		expect(chips[1].text()).toContain('open, paused')
		expect(chips[1].find('[data-testid="active-filter-source"]').exists()).toBe(false)
		expect(chips[2].text()).toContain('2026-01 – 2026-03')
	})

	it('REQ-CWF-007: a pinned clause has a lock and no remove button', () => {
		const chips = panel([CLICK, PINNED]).findAll('.active-filters__chip')

		expect(chips[1].find('[data-testid="active-filter-lock"]').exists()).toBe(true)
		expect(chips[1].find('[data-testid="active-filter-remove"]').exists()).toBe(false)
		expect(chips[0].find('[data-testid="active-filter-lock"]').exists()).toBe(false)
		expect(chips[0].find('[data-testid="active-filter-remove"]').attributes('aria-label')).toBe('Remove filter: Gemeente')
	})

	it('REQ-CWF-007: remove names the clause dimension', async () => {
		const wrapper = panel([CLICK, BOOKMARK])

		await wrapper.findAll('[data-testid="active-filter-remove"]')[1].trigger('click')

		expect(wrapper.emitted('remove')).toEqual([['status']])
	})

	it('REQ-CWF-006: Reset filters asks for a reset', async () => {
		const wrapper = panel([CLICK, PINNED])

		await wrapper.find('[data-testid="reset-filters"]').trigger('click')

		expect(wrapper.emitted('reset')).toHaveLength(1)
	})

	it('REQ-CWF-006: no Reset filters when only pinned clauses are left', () => {
		const wrapper = panel([PINNED])

		expect(wrapper.find('[data-testid="active-filters"]').exists()).toBe(true)
		expect(wrapper.find('[data-testid="reset-filters"]').exists()).toBe(false)
	})
})
