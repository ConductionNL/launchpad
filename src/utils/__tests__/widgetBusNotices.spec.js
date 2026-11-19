/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The widget dialog warns about filter declarations that do not fit the
 * dashboard (drill-down-cross-widget-filter task 9, REQ-CWF-008).
 *
 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-9
 */

import { describe, expect, it, vi } from 'vitest'
import { busNotices } from '../widgetBusNotices.js'

vi.mock('@nextcloud/l10n', () => ({
	translate: (_app, text, vars = {}) => text.replace(/\{(\w+)\}/g, (_m, key) => vars[key]),
}))

const HIERARCHIES = [{ id: 'nl', levels: [{ dimension: 'gemeenteCode' }, { dimension: 'wijkCode' }] }]

describe('busNotices', () => {
	it('warns about a consumed dimension no hierarchy publishes', () => {
		expect(busNotices({ consumes: ['buurtCode'] }, HIERARCHIES)).toEqual([{
			id: 'consumes-unknown-dimension:buurtCode',
			type: 'warning',
			text: 'This widget consumes buurtCode, but no hierarchy on this dashboard publishes it. It shows its data unfiltered.',
		}])
	})

	it('says nothing for a widget that fits or declares nothing', () => {
		expect(busNotices({ consumes: ['wijkCode'], publishes: [{ dimension: 'gemeenteCode', fromField: 'gemeente' }] }, HIERARCHIES)).toEqual([])
		expect(busNotices({ dataSource: {} }, HIERARCHIES)).toEqual([])
		expect(busNotices(null, [])).toEqual([])
	})

	it('reads declarations under props too, and treats a dashboard without hierarchies as having none', () => {
		expect(busNotices({ props: { consumes: ['gemeenteCode'] } }, undefined)).toHaveLength(1)
	})
})
