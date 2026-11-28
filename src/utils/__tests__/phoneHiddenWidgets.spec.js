/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The phone notice names the hidden widgets: three by title, the rest
 * counted (launchpad-mobile-remote-access REQ-MRA-007, board LpMobiel).
 *
 * @spec openspec/changes/archive/2026-11-24-phone-hidden-widgets-named/specs/launchpad-mobile-remote-access/spec.md#requirement-the-phone-notice-names-the-hidden-widgets-req-mra-007
 */

import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app, text, vars = {}) => text.replace(/\{(\w+)\}/g, (_m, k) => String(vars[k])),
}))

import {
	hiddenWidgetTitle,
	isPhoneReady,
	isPhoneWidth,
	namedList,
	phoneHiddenPlacements,
	phoneHiddenSentence,
} from '../phoneHiddenWidgets.js'

describe('isPhoneReady', () => {
	it('hides only a type that says mobileReady false', () => {
		expect(isPhoneReady({ mobileReady: false })).toBe(false)
		expect(isPhoneReady({ mobileReady: true })).toBe(true)
		expect(isPhoneReady({})).toBe(true)
		expect(isPhoneReady(null)).toBe(true)
	})
})

describe('isPhoneWidth', () => {
	it('is a phone at 390 px, not at 1280 px or an unknown width', () => {
		expect(isPhoneWidth(390)).toBe(true)
		expect(isPhoneWidth(1280)).toBe(false)
		expect(isPhoneWidth(0)).toBe(false)
		expect(isPhoneWidth(undefined)).toBe(false)
	})
})

describe('phoneHiddenPlacements', () => {
	it('keeps the placements whose type is not ready for a phone', () => {
		const entries = { assistant: { mobileReady: false }, tile: { mobileReady: true } }
		const placements = [{ id: 1, widgetId: 'assistant' }, { id: 2, widgetId: 'tile' }, { id: 3, widgetId: 'other' }]
		expect(phoneHiddenPlacements(placements, (type) => entries[type] ?? null).map((p) => p.id)).toEqual([1])
		expect(phoneHiddenPlacements(null, () => null)).toEqual([])
	})
})

describe('hiddenWidgetTitle', () => {
	it('prefers the own title, then the content title, the type name and the type', () => {
		expect(hiddenWidgetTitle({ customTitle: 'Snel beginnen (Word)', widgetId: 'tile' }, { displayName: 'Tile' })).toBe('Snel beginnen (Word)')
		expect(hiddenWidgetTitle({ content: { title: 'Nieuws' }, widgetId: 'news' }, { displayName: 'News' })).toBe('Nieuws')
		expect(hiddenWidgetTitle({ customTitle: ' ', widgetId: 'assistant' }, { displayName: 'Assistent' })).toBe('Assistent')
		expect(hiddenWidgetTitle({ widgetId: 'assistant' }, null)).toBe('assistant')
		expect(hiddenWidgetTitle({}, null)).toBe('')
	})
})

describe('namedList', () => {
	it('names one, two and three widgets', () => {
		expect(namedList([])).toBe('')
		expect(namedList(['A'])).toBe('A')
		expect(namedList(['A', 'B'])).toBe('A and B')
		expect(namedList(['A', 'B', 'C'])).toBe('A, B and C')
	})

	it('names three and counts the rest', () => {
		expect(namedList(['A', 'B', 'C', 'D', 'E'])).toBe('A, B, C and 2 more')
	})
})

describe('phoneHiddenSentence', () => {
	it('says nothing when nothing is hidden', () => {
		expect(phoneHiddenSentence([])).toBe('')
		expect(phoneHiddenSentence(undefined)).toBe('')
	})

	it('counts and names two hidden widgets', () => {
		expect(phoneHiddenSentence(['Snel beginnen (Word)', 'Assistent']))
			.toBe('2 widgets do not work on a phone and are hidden: Snel beginnen (Word) and Assistent.')
	})

	it('says one widget in the singular', () => {
		expect(phoneHiddenSentence(['Assistent'])).toBe('One widget does not work on a phone and is hidden: Assistent.')
	})

	it('counts all five but names three', () => {
		expect(phoneHiddenSentence(['A', 'B', 'C', 'D', 'E']))
			.toBe('5 widgets do not work on a phone and are hidden: A, B, C and 2 more.')
	})

	it('offers no desktop layout', () => {
		expect(phoneHiddenSentence(['A', 'B'])).not.toMatch(/desktop/i)
	})
})
