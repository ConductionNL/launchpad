/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The allowed websites rows: purpose per site, the stored shape, and adding a
 * host (REQ-ASET-021).
 *
 * @spec openspec/changes/archive/2026-11-24-allowed-websites-list/specs/admin-settings/spec.md#requirement-one-list-of-allowed-websites-with-a-purpose-per-site-req-aset-021
 */

import { describe, expect, it } from 'vitest'
import { addRow, purposeOf, rowsFrom, siteFor } from '../allowedSites.js'

describe('allowed websites rows', () => {
	it('reads the purpose of a site', () => {
		expect(purposeOf({ embed: true, fetch: true })).toBe('both')
		expect(purposeOf({ embed: false, fetch: true })).toBe('fetch')
		expect(purposeOf({ embed: true, fetch: false })).toBe('embed')
		expect(purposeOf(null)).toBe('embed')
	})

	it('writes a site as the server stores it', () => {
		expect(siteFor(' app.powerbi.com ', 'embed')).toEqual({ host: 'app.powerbi.com', embed: true, fetch: false })
		expect(siteFor('kcc-api.zuiddrecht.nl', 'fetch')).toEqual({ host: 'kcc-api.zuiddrecht.nl', embed: false, fetch: true })
		expect(siteFor('www.zuiddrecht.nl', 'both')).toEqual({ host: 'www.zuiddrecht.nl', embed: true, fetch: true })
	})

	it('builds rows from an answer and skips what is not a site', () => {
		expect(rowsFrom({ sites: [{ host: 'app.powerbi.com', embed: true }, { host: '' }, null, { embed: true }] }))
			.toEqual([{ host: 'app.powerbi.com', purpose: 'embed' }])
		expect(rowsFrom(undefined)).toEqual([])
	})

	it('adds a host once and gives a known host the new purpose', () => {
		const rows = [{ host: 'app.powerbi.com', purpose: 'embed' }]
		expect(addRow(rows, '  ', 'fetch')).toBe(rows)
		expect(addRow(rows, 'kcc-api.zuiddrecht.nl', 'fetch')).toEqual([...rows, { host: 'kcc-api.zuiddrecht.nl', purpose: 'fetch' }])
		expect(addRow(rows, 'APP.powerbi.com', 'both')).toEqual([{ host: 'app.powerbi.com', purpose: 'both' }])
	})
})
