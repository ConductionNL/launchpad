/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * useDashboardFilterSession: the first dashboard of a page load opens with
 * the URL's filter, a later one starts empty, the bus follows the
 * dashboard's own hierarchies and the chips get their level labels
 * (drill-down-cross-widget-filter task 4; REQ-CWF-003, REQ-CWF-004).
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { serializeFilterState } from '../useDashboardFilter.js'
import { hierarchiesOf, levelLabels, useDashboardFilterSession } from '../useDashboardFilterSession.js'

const ADMINISTRATIVE = {
	id: 'nl-administrative',
	levels: [
		{ dimension: 'provincieCode', label: 'Provincie' },
		{ dimension: 'gemeenteCode', parentDimension: 'provincieCode', label: 'Gemeente' },
		{ dimension: 'wijkCode', parentDimension: 'gemeenteCode', label: 'Wijk' },
	],
}
const ZEIST = { dimension: 'gemeenteCode', operator: 'eq', value: 'GM0355', source: 'widgetClick', sourceWidgetId: 'w-a', pinned: false }

/**
 * A location and history at a URL.
 *
 * @param {string} href The URL.
 * @return {{location: {href: string}, history: object}}
 */
function browserAt(href) {
	const location = { href }
	return { location, history: { state: null, replaceState: vi.fn((_s, _t, url) => { location.href = url }) } }
}

beforeEach(() => vi.useFakeTimers())
afterEach(() => vi.useRealTimers())

describe('useDashboardFilterSession', () => {
	const bookmarked = 'https://cloud.example/apps/launchpad/?f=' + serializeFilterState([ZEIST])

	it('the first dashboard opens with the bookmarked filter', () => {
		const { location, history } = browserAt(bookmarked)
		const session = useDashboardFilterSession({ dashboard: { hierarchies: [ADMINISTRATIVE] }, readUrl: true, location, history })

		expect(session.clauses.value.map((clause) => [clause.dimension, clause.source])).toEqual([['gemeenteCode', 'urlBootstrap']])
	})

	it('a dashboard switched to later starts empty', () => {
		const { location, history } = browserAt(bookmarked)
		const session = useDashboardFilterSession({ dashboard: { hierarchies: [ADMINISTRATIVE] }, readUrl: false, location, history })

		expect(session.clauses.value).toEqual([])
	})

	it('the bus drills down through the dashboard hierarchy', () => {
		const { location, history } = browserAt('https://cloud.example/apps/launchpad/')
		const session = useDashboardFilterSession({ dashboard: { hierarchies: [ADMINISTRATIVE] }, readUrl: true, location, history })

		session.bus.publish(
			{ dimension: 'wijkCode', operator: 'eq', value: 'WK035501', source: 'widgetClick', sourceWidgetId: 'w-wijken' },
			{ row: { wijkCode: 'WK035501', gemeenteCode: 'GM0355', provincieCode: 'PV26' } },
		)

		expect(session.clauses.value.find((clause) => clause.dimension === 'gemeenteCode')).toMatchObject({ value: 'GM0355', source: 'parentScope' })
		expect(session.labels).toEqual({ provincieCode: 'Provincie', gemeenteCode: 'Gemeente', wijkCode: 'Wijk' })
	})

	it('remove and reset reach the bus, stop writes the URL one last time', () => {
		const { location, history } = browserAt(bookmarked)
		const session = useDashboardFilterSession({ dashboard: null, readUrl: true, location, history })

		expect(session.remove('gemeenteCode')).toBe(true)
		expect(session.reset()).toBe(false)
		session.stop()

		expect(new URL(location.href).searchParams.has('f')).toBe(false)
	})
})

describe('hierarchiesOf / levelLabels', () => {
	it('keeps only descriptors with levels and labels only labelled levels', () => {
		const hierarchies = hierarchiesOf({ hierarchies: [ADMINISTRATIVE, null, { id: 'broken' }] })

		expect(hierarchies).toEqual([ADMINISTRATIVE])
		expect(hierarchiesOf({})).toEqual([])
		expect(levelLabels([{ id: 'x', levels: [{ dimension: 'a', label: '' }, { dimension: 'b', label: 'B' }] }])).toEqual({ b: 'B' })
	})
})
