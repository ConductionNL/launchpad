// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.

/**
 * The read confirmations dialog's numbers and rows (REQ-ACK-010). Pure, so
 * the counts and the filters run under vitest with nothing mocked.
 *
 * @spec openspec/changes/archive/2026-11-24-read-receipts-dialog/specs/dashboard-acknowledgements/spec.md#requirement-the-read-confirmations-dialog-req-ack-010
 */

/** @type {string[]} The filters, in the order the dialog offers them. */
export const FILTERS = ['everyone', 'confirmed', 'pending']

/**
 * The counts of a report: confirmed, the audience, the number still to go
 * and the confirmed share as a whole percentage.
 *
 * @param {object|null} report The report.
 * @return {{confirmed: number, audience: number, left: number, percent: number}} The counts.
 * @spec openspec/changes/archive/2026-11-24-read-receipts-dialog/specs/dashboard-acknowledgements/spec.md#requirement-the-read-confirmations-dialog-req-ack-010
 */
export function receiptCounts(report) {
	const rows = Array.isArray(report?.rows) ? report.rows : []
	const confirmed = rows.filter((row) => row?.status === 'acknowledged').length
	const audience = rows.length
	return {
		confirmed,
		audience,
		left: audience - confirmed,
		percent: audience === 0 ? 0 : Math.round((confirmed / audience) * 100),
	}
}

/**
 * The rows a filter shows: everyone, the confirmed ones or those who have
 * not confirmed yet.
 *
 * @param {Array<object>} rows The report rows.
 * @param {string} filter `everyone`, `confirmed` or `pending`.
 * @return {Array<object>} The rows to show.
 * @spec openspec/changes/archive/2026-11-24-read-receipts-dialog/specs/dashboard-acknowledgements/spec.md#requirement-the-read-confirmations-dialog-req-ack-010
 */
export function filterReceipts(rows, filter) {
	const list = Array.isArray(rows) ? rows : []
	if (filter === 'confirmed') {
		return list.filter((row) => row?.status === 'acknowledged')
	}
	if (filter === 'pending') {
		return list.filter((row) => row?.status !== 'acknowledged')
	}
	return list
}

/**
 * Up to two initials of a name: "Sanne de Vries" → "SV".
 *
 * @param {string} name The display name.
 * @return {string} The initials, upper case.
 * @spec openspec/changes/archive/2026-11-24-read-receipts-dialog/specs/dashboard-acknowledgements/spec.md#requirement-the-read-confirmations-dialog-req-ack-010
 */
export function initialsOf(name) {
	const words = String(name ?? '').trim().split(/\s+/).filter((word) => word !== '')
	if (words.length === 0) {
		return '?'
	}
	const capitals = words.filter((word) => word[0] === word[0].toUpperCase() && /\p{L}/u.test(word[0]))
	const picked = capitals.length >= 2 ? [capitals[0], capitals[capitals.length - 1]] : [words[0], words.length > 1 ? words[words.length - 1] : '']
	return picked.map((word) => (word[0] ?? '').toUpperCase()).join('')
}

/**
 * A date for the dialog, in the reader's language; '' for no or a bad date.
 *
 * @param {string|null} value The stored date.
 * @param {object} [options] Intl options.
 * @return {string} The date.
 * @spec openspec/changes/archive/2026-11-24-read-receipts-dialog/specs/dashboard-acknowledgements/spec.md#requirement-the-read-confirmations-dialog-req-ack-010
 */
export function receiptDate(value, options = { day: 'numeric', month: 'long', year: 'numeric' }) {
	if (!value) {
		return ''
	}
	const date = new Date(String(value).replace(' ', 'T'))
	return Number.isNaN(date.getTime()) ? '' : date.toLocaleDateString(undefined, options)
}
