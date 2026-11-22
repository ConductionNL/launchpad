/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * FilterBreadcrumb: the drill-down path of each hierarchy, broadest level
 * first, the levels above the deepest as buttons that go back up, nothing
 * while no level is active (drill-down-cross-widget-filter task 23; REQ-CWF-004).
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import FilterBreadcrumb from '../FilterBreadcrumb.vue'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app, text, vars = {}) => text.replace(/{(\w+)}/g, (_match, key) => vars[key] ?? `{${key}}`),
}))

const NL = {
	id: 'nl-administrative',
	levels: [
		{ dimension: 'provincieCode', label: 'Province' },
		{ dimension: 'gemeenteCode', label: 'Municipality' },
		{ dimension: 'wijkCode', label: 'District' },
		{ dimension: 'buurtCode', label: 'Neighbourhood' },
	],
}
const LABELS = { provincieCode: 'Provincie', gemeenteCode: 'Gemeente', wijkCode: 'Wijk' }

/**
 * A clause.
 *
 * @param {string} dimension The dimension.
 * @param {string} value The value.
 * @param {string} source The source.
 * @return {object}
 */
function clause(dimension, value, source = 'parentScope') {
	return { dimension, operator: 'eq', value, source, sourceWidgetId: null, pinned: false }
}

const DRILLED = [clause('wijkCode', 'WK035501', 'widgetClick'), clause('status', 'open', 'widgetClick'), clause('gemeenteCode', 'GM0355'), clause('provincieCode', 'PV26')]

/**
 * Mount the breadcrumb.
 *
 * @param {Array<object>} clauses The clauses.
 * @param {object} [labels] The level labels.
 * @return {import('@vue/test-utils').VueWrapper}
 */
function mountCrumbs(clauses, labels = LABELS) {
	return mount(FilterBreadcrumb, { props: { hierarchies: [NL], clauses, labels } })
}

describe('FilterBreadcrumb', () => {
	it('shows the path broadest first in the reader\'s labels, the deepest level as the current one', () => {
		const w = mountCrumbs(DRILLED)
		const steps = w.findAll('[data-testid="filter-breadcrumb-step"]')
		expect(steps.map((step) => step.text())).toEqual(['Provincie PV26', 'Gemeente GM0355', 'Wijk WK035501'])
		expect(steps[2].element.tagName).toBe('SPAN')
		expect(steps[2].attributes('aria-current')).toBe('location')
		expect(steps[0].element.tagName).toBe('BUTTON')
		expect(steps[0].attributes('aria-label')).toBe('Back to Provincie PV26')
		expect(w.find('nav').attributes('aria-label')).toBe('Drill-down path')
		expect(w.findAll('.filter-breadcrumb__separator')).toHaveLength(2)
	})

	it('goes back up to a level when its step is chosen', async () => {
		const w = mountCrumbs(DRILLED)
		await w.findAll('button[data-testid="filter-breadcrumb-step"]')[0].trigger('click')
		await w.findAll('button[data-testid="filter-breadcrumb-step"]')[1].trigger('click')
		expect(w.emitted('pop')).toEqual([['provincieCode'], ['gemeenteCode']])
	})

	it('falls back to the stored level label, and renders nothing without an active level', () => {
		expect(mountCrumbs([clause('provincieCode', 'PV26', 'widgetClick')], {}).text()).toBe('Province PV26')
		expect(mountCrumbs([clause('status', 'open')]).find('[data-testid="filter-breadcrumbs"]').exists()).toBe(false)
		expect(mountCrumbs([]).html()).not.toContain('filter-breadcrumb')
	})
})
