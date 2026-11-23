/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

import { describe, expect, it } from 'vitest'
import { toGroupOptions } from '../groupOptions.js'

describe('toGroupOptions', () => {
	it('turns plain ids into options labelled by the id', () => {
		expect(toGroupOptions(['marketing', 'sales'])).toEqual([
			{ id: 'marketing', displayName: 'marketing' },
			{ id: 'sales', displayName: 'sales' },
		])
	})

	it('keeps a group\'s display name, and falls back to its id', () => {
		expect(toGroupOptions([{ id: 'mkt', displayName: 'Marketing' }, { id: 'ops' }])).toEqual([
			{ id: 'mkt', displayName: 'Marketing' },
			{ id: 'ops', displayName: 'ops' },
		])
	})

	it('adds chosen ids the list does not carry, once', () => {
		expect(toGroupOptions(['sales'], ['sales', 'gone'])).toEqual([
			{ id: 'sales', displayName: 'sales' },
			{ id: 'gone', displayName: 'gone' },
		])
	})

	it('drops entries without an id and survives missing input', () => {
		expect(toGroupOptions([{ displayName: 'x' }, '', null])).toEqual([])
		expect(toGroupOptions(undefined, undefined)).toEqual([])
	})
})
