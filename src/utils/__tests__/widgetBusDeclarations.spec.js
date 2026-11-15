/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * validateBusDeclarations: a widget consuming a dimension no hierarchy on the
 * dashboard has gets a warning and stays out of the bus; one publishing from
 * a field its data lacks gets an error and stays out; matching declarations
 * pass (drill-down-cross-widget-filter task 21; REQ-CWF-008).
 */

import { describe, expect, it } from 'vitest'
import { validateBusDeclarations } from '../widgetBusDeclarations.js'

const HIERARCHIES = [{
	id: 'nl-administrative',
	levels: [
		{ dimension: 'provincieCode' },
		{ dimension: 'gemeenteCode', parentDimension: 'provincieCode' },
	],
}]

describe('validateBusDeclarations', () => {
	it('REQ-CWF-008: a consumed dimension outside every hierarchy warns and keeps the widget out', () => {
		const result = validateBusDeclarations({ widget: { consumes: ['wijkCode'] }, hierarchies: HIERARCHIES })

		expect(result.participates).toBe(false)
		expect(result.findings).toEqual([{ level: 'warning', code: 'consumes-unknown-dimension', dimension: 'wijkCode' }])
	})

	it('REQ-CWF-008: publishing from a field the data lacks is an error and keeps the widget out', () => {
		const result = validateBusDeclarations({
			widget: { publishes: [{ dimension: 'gemeenteCode', operator: 'eq', fromField: 'missingField' }] },
			hierarchies: HIERARCHIES,
			sampleRow: { gemeente: 'GM0355', inwoners: 65000 },
		})

		expect(result.participates).toBe(false)
		expect(result.findings).toEqual([{ level: 'error', code: 'publishes-missing-field', dimension: 'gemeenteCode', field: 'missingField' }])
	})

	it('REQ-CWF-008: matching declarations pass', () => {
		const result = validateBusDeclarations({
			widget: {
				consumes: [{ dimension: 'provincieCode', operator: 'eq', intoFilter: 'provincie' }, 'gemeenteCode'],
				publishes: [{ dimension: 'gemeenteCode', fromField: 'code' }, { dimension: 'provincieCode' }],
			},
			hierarchies: HIERARCHIES,
			sampleRow: { code: 'GM0355', provincieCode: 'PV26' },
		})

		expect(result).toEqual({ participates: true, findings: [] })
	})

	it('without a sample row the published fields are not judged', () => {
		const result = validateBusDeclarations({ widget: { publishes: [{ dimension: 'gemeenteCode', fromField: 'x' }] }, hierarchies: HIERARCHIES })

		expect(result.participates).toBe(true)
	})

	it('a widget without declarations, or with malformed ones, passes and declares nothing', () => {
		expect(validateBusDeclarations({ widget: {}, hierarchies: [] })).toEqual({ participates: true, findings: [] })
		expect(validateBusDeclarations({ widget: { consumes: [null, 7, { dimension: '' }] }, hierarchies: null }).participates).toBe(true)
	})
})
