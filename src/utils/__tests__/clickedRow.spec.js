/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * clickedRow: a click on a hierarchy level reads the levels above it from one
 * record of the clicked category, so the bus can infer them
 * (drill-down-cross-widget-filter task 23; REQ-CWF-004).
 */

import axios from '@nextcloud/axios'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { clickedRow, missingAncestors } from '../clickedRow.js'

vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn() } }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path, vars = {}) => path.replace(/{(\w+)}/g, (_m, key) => vars[key]) }))

const NL = { id: 'nl', levels: [{ dimension: 'provincieCode' }, { dimension: 'gemeenteCode' }, { dimension: 'wijkCode' }] }
const WIJK_CLICK = [{ dimension: 'wijkCode' }]
const SELECTION = { field: 'wijkCode', value: 'WK035501' }
const SOURCE = { register: 'cwf', schema: 'melding', filter: { gemeenteCode: 'GM0355' } }

beforeEach(() => {
	axios.get.mockReset()
})

describe('missingAncestors', () => {
	it('names the levels above a published level that the row does not hold', () => {
		expect(missingAncestors([NL], WIJK_CLICK, { wijkCode: 'WK035501' })).toEqual(['provincieCode', 'gemeenteCode'])
		expect(missingAncestors([NL], WIJK_CLICK, { wijkCode: 'WK035501', gemeenteCode: 'GM0355', provincieCode: '' })).toEqual(['provincieCode'])
		expect(missingAncestors([NL], [{ dimension: 'provincieCode' }], {})).toEqual([])
		expect(missingAncestors([NL], [{ dimension: 'status' }], {})).toEqual([])
	})
})

describe('clickedRow', () => {
	it('reads the ancestors from the first record of the clicked category', async () => {
		axios.get.mockResolvedValue({ data: { results: [{ wijkCode: 'WK035501', gemeenteCode: 'GM0355', provincieCode: 'PV26', status: 'open' }] } })

		const row = await clickedRow({ selection: SELECTION, clauses: WIJK_CLICK, hierarchies: [NL], dataSource: SOURCE })

		expect(row).toEqual({ wijkCode: 'WK035501', gemeenteCode: 'GM0355', provincieCode: 'PV26' })
		expect(axios.get).toHaveBeenCalledWith('/apps/openregister/api/objects/cwf/melding', { params: { wijkCode: 'WK035501', _limit: 1 } })
	})

	it('asks nothing when no ancestor is missing or the chart has no register', async () => {
		expect(await clickedRow({ selection: { field: 'provincieCode', value: 'PV26' }, clauses: [{ dimension: 'provincieCode' }], hierarchies: [NL], dataSource: SOURCE }))
			.toEqual({ provincieCode: 'PV26' })
		expect(await clickedRow({ selection: SELECTION, clauses: WIJK_CLICK, hierarchies: [NL], dataSource: { url: '/x' } }))
			.toEqual({ wijkCode: 'WK035501' })
		expect(axios.get).not.toHaveBeenCalled()
	})

	it('leaves the row as it was when the record is missing, lacks a level or cannot be read', async () => {
		axios.get.mockResolvedValueOnce({ data: { results: [] } })
		expect(await clickedRow({ selection: SELECTION, clauses: WIJK_CLICK, hierarchies: [NL], dataSource: SOURCE })).toEqual({ wijkCode: 'WK035501' })

		axios.get.mockResolvedValueOnce({ data: { results: [{ gemeenteCode: 'GM0355', provincieCode: null }] } })
		expect(await clickedRow({ selection: SELECTION, clauses: WIJK_CLICK, hierarchies: [NL], dataSource: SOURCE })).toEqual({ wijkCode: 'WK035501', gemeenteCode: 'GM0355' })

		axios.get.mockRejectedValueOnce(new Error('403'))
		expect(await clickedRow({ selection: SELECTION, clauses: WIJK_CLICK, hierarchies: [NL], dataSource: SOURCE })).toEqual({ wijkCode: 'WK035501' })
	})
})
