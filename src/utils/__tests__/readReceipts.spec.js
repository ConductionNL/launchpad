/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The read confirmations counts, filters and initials (REQ-ACK-010).
 *
 * @spec openspec/changes/read-receipts-dialog/specs/dashboard-acknowledgements/spec.md#requirement-the-read-confirmations-dialog-req-ack-010
 */

import { describe, expect, it } from 'vitest'
import { filterReceipts, initialsOf, receiptCounts, receiptDate } from '../readReceipts.js'

function rows(confirmed, pending) {
	return [
		...Array.from({ length: confirmed }, (_, i) => ({ userId: `c${i}`, status: 'acknowledged' })),
		...Array.from({ length: pending }, (_, i) => ({ userId: `p${i}`, status: 'pending' })),
	]
}

describe('read confirmations', () => {
	it('counts 128 of 164 confirmed with 36 to go', () => {
		expect(receiptCounts({ rows: rows(128, 36) })).toEqual({ confirmed: 128, audience: 164, left: 36, percent: 78 })
		expect(receiptCounts(null)).toEqual({ confirmed: 0, audience: 0, left: 0, percent: 0 })
	})

	it('filters Not confirmed yet to the 36 who have not', () => {
		const all = rows(128, 36)
		expect(filterReceipts(all, 'pending')).toHaveLength(36)
		expect(filterReceipts(all, 'pending').every((row) => row.status === 'pending')).toBe(true)
		expect(filterReceipts(all, 'confirmed')).toHaveLength(128)
		expect(filterReceipts(all, 'everyone')).toBe(all)
		expect(filterReceipts(undefined, 'pending')).toEqual([])
	})

	it('takes the initials of a name', () => {
		expect(initialsOf('Sanne de Vries')).toBe('SV')
		expect(initialsOf('Pieter Jansen')).toBe('PJ')
		expect(initialsOf('admin')).toBe('A')
		expect(initialsOf('jan van der berg')).toBe('JB')
		expect(initialsOf('  ')).toBe('?')
	})

	it('formats a date and leaves a bad one out', () => {
		expect(receiptDate('2026-10-13 10:00:00')).not.toBe('')
		expect(receiptDate('not a date')).toBe('')
		expect(receiptDate(null)).toBe('')
	})
})
