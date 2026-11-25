/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Pure helpers behind the Live data form's unit, refresh choice, colour and
 * preview (REQ-LIVETILE-020). Kept apart from the component so they are
 * tested without mounting it.
 */

import { translate as t } from '@nextcloud/l10n'

/** The refresh choices the form offers, in seconds. */
export const REFRESH_CHOICES = Object.freeze([300, 900, 1800, 3600])

/** The theme colours a live tile can take; '' is the default. */
export const COLOUR_TOKENS = Object.freeze(['', 'primary', 'success', 'warning', 'error'])

/**
 * The stored suffix for a unit: a word gets a space in front so "12 minutes"
 * reads as one; a symbol such as "%" stays attached.
 *
 * @param {string} unit The unit as typed.
 * @return {string} the suffix to store.
 * @spec openspec/changes/archive/2026-11-25-widget-content-forms-board-sync/specs/live-data-tile-widget/spec.md#requirement-the-live-data-form-as-lpwidgetinstellen-draws-it-req-livetile-020
 */
export function unitToSuffix(unit) {
	const trimmed = String(unit ?? '').trim()
	if (trimmed === '') {
		return ''
	}
	return /^\p{L}/u.test(trimmed) ? ' ' + trimmed : trimmed
}

/**
 * The unit shown in the form for a stored suffix.
 *
 * @param {string} suffix The stored suffix.
 * @return {string} the unit.
 * @spec openspec/changes/archive/2026-11-25-widget-content-forms-board-sync/specs/live-data-tile-widget/spec.md#requirement-the-live-data-form-as-lpwidgetinstellen-draws-it-req-livetile-020
 */
export function suffixToUnit(suffix) {
	return String(suffix ?? '').trim()
}

/**
 * "Every 5 minutes" for a whole number of minutes from five up, else seconds.
 *
 * @param {number} seconds The interval.
 * @param {boolean} sentence true for the preview's "Refreshes every …" line.
 * @return {string} the text.
 * @spec openspec/changes/archive/2026-11-25-widget-content-forms-board-sync/specs/live-data-tile-widget/spec.md#requirement-the-live-data-form-as-lpwidgetinstellen-draws-it-req-livetile-020
 */
export function refreshLabel(seconds, sentence = false) {
	const value = Number(seconds)
	if (value >= 300 && value % 60 === 0) {
		const n = value / 60
		return sentence
			? t('launchpad', 'Refreshes every {n} minutes', { n })
			: t('launchpad', 'Every {n} minutes', { n })
	}
	return sentence
		? t('launchpad', 'Refreshes every {n} seconds', { n: value })
		: t('launchpad', 'Every {n} seconds', { n: value })
}

/**
 * The refresh choices, with a stored value that is not one of them kept as
 * its own choice so editing a tile never changes its interval silently.
 *
 * @param {number} current The stored interval in seconds.
 * @return {Array<{value: number, label: string}>} the choices.
 * @spec openspec/changes/archive/2026-11-25-widget-content-forms-board-sync/specs/live-data-tile-widget/spec.md#requirement-the-live-data-form-as-lpwidgetinstellen-draws-it-req-livetile-020
 */
export function refreshOptions(current) {
	const values = [...REFRESH_CHOICES]
	const stored = Number(current)
	if (Number.isFinite(stored) && stored > 0 && !values.includes(stored)) {
		values.push(stored)
		values.sort((a, b) => a - b)
	}
	return values.map((value) => ({ value, label: refreshLabel(value) }))
}

/**
 * A stored colour token, or '' for an unknown one.
 *
 * @param {string} token The stored token.
 * @return {string} a known token.
 * @spec openspec/changes/archive/2026-11-25-widget-content-forms-board-sync/specs/live-data-tile-widget/spec.md#requirement-the-live-data-form-as-lpwidgetinstellen-draws-it-req-livetile-020
 */
export function knownColour(token) {
	return COLOUR_TOKENS.includes(token) ? token : ''
}

/**
 * The value the preview shows: the reading's formatted value when there is
 * one, else a dash; with the prefix and the unit around it.
 *
 * @param {object|null} reading The last reading of the tile, if any.
 * @param {{prefix: string, suffix: string, thousands: boolean}} format The format fields.
 * @return {string} the preview value.
 * @spec openspec/changes/archive/2026-11-25-widget-content-forms-board-sync/specs/live-data-tile-widget/spec.md#requirement-the-live-data-form-as-lpwidgetinstellen-draws-it-req-livetile-020
 */
export function previewValue(reading, format) {
	const raw = reading?.value
	let body = '—'
	if (typeof raw === 'number' && Number.isFinite(raw)) {
		body = format.thousands ? raw.toLocaleString() : String(raw)
	} else if (typeof raw === 'string' && raw !== '') {
		body = raw
	}
	return `${format.prefix ?? ''}${body}${format.suffix ?? ''}`
}
