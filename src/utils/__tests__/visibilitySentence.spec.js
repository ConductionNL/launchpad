/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The words of "Who sees this widget?" (board LpZichtbaarheid): each rule's
 * kind and value, and the View as sentence for a person who does and who
 * does not see the widget (conditional-visibility-editor REQ-CVUI-006).
 *
 * @spec openspec/changes/archive/2026-11-24-visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md#requirement-the-rules-dialog-as-lpzichtbaarheid-draws-it-req-cvui-006
 */

import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app, text, vars = {}) => text.replace(/\{(\w+)\}/g, (_m, k) => String(vars[k])),
}))

import {
	dayRanges,
	decidingRules,
	formatMoment,
	joinList,
	ruleKindLabel,
	ruleValue,
	visibilitySentence,
} from '../visibilitySentence.js'

const KCC = { id: 1, ruleType: 'group', ruleConfig: { groups: ['kcc'] }, isInclude: true }
const OFFICE_HOURS = {
	id: 2,
	ruleType: 'time',
	ruleConfig: { startTime: '08:00', endTime: '17:00', days: ['mon', 'tue', 'wed', 'thu', 'fri'] },
	isInclude: true,
}
const HOLIDAY = { id: 3, ruleType: 'date', ruleConfig: { startDate: '2026-12-24', endDate: '2026-12-26' }, isInclude: false }
const GROUP_NAMES = { kcc: 'KCC' }
const TUESDAY = 'Tuesday 13 October 10:00'

describe('the View as sentence (scenario: View as Sanne)', () => {
	it('says that Sanne sees the widget, because Sanne is in KCC and the moment is within the times', () => {
		expect(visibilitySentence({
			name: 'Sanne de Vries',
			moment: TUESDAY,
			rules: [KCC, OFFICE_HOURS],
			result: { visible: true, matchedIncludeRuleIds: [1, 2], matchedExcludeRuleIds: [] },
			groupNames: GROUP_NAMES,
		})).toBe('Sanne de Vries sees this widget, because Sanne de Vries is in KCC and Tuesday 13 October 10:00 is within the times.')
	})

	it('says that a person outside KCC does not see the widget when all rules must match', () => {
		expect(visibilitySentence({
			name: 'Piet Jansen',
			moment: TUESDAY,
			rules: [KCC, OFFICE_HOURS],
			result: { visible: false, matchedIncludeRuleIds: [2], matchedExcludeRuleIds: [] },
			groupNames: GROUP_NAMES,
		})).toBe('Piet Jansen does not see this widget, because Piet Jansen is not in KCC.')
	})

	it('names every include rule when one rule is enough and none matches', () => {
		expect(visibilitySentence({
			name: 'Piet Jansen',
			moment: 'Saturday 17 October 10:00',
			rules: [KCC, OFFICE_HOURS],
			result: { visible: false, matchedIncludeRuleIds: [], matchedExcludeRuleIds: [] },
			groupNames: GROUP_NAMES,
		})).toBe('Piet Jansen does not see this widget, because Piet Jansen is not in KCC and Saturday 17 October 10:00 is outside the times.')
	})

	it('names the hide rule that matched', () => {
		expect(visibilitySentence({
			name: 'Sanne de Vries',
			moment: 'Thursday 24 December 10:00',
			rules: [KCC, HOLIDAY],
			result: { visible: false, matchedIncludeRuleIds: [1], matchedExcludeRuleIds: [3] },
		})).toBe('Sanne de Vries does not see this widget, because Thursday 24 December 10:00 is within the period.')
	})

	it('says that no rule limits a widget without include rules, and nothing without a result', () => {
		expect(visibilitySentence({ name: 'Sanne de Vries', moment: TUESDAY, rules: [], result: { visible: true } }))
			.toBe('Sanne de Vries sees this widget: no rule limits who sees it.')
		expect(visibilitySentence({ name: 'Sanne de Vries', moment: TUESDAY, rules: [], result: { visible: false } }))
			.toBe('')
		expect(visibilitySentence({ name: 'Sanne de Vries', moment: TUESDAY, rules: [], result: null })).toBe('')
	})

	it('says a profile field matches or not, and joins several groups with or', () => {
		const field = { id: 4, ruleType: 'attribute', ruleConfig: { attribute: 'department', operator: 'equals', value: 'IT' }, isInclude: true }
		const groups = { id: 5, ruleType: 'group', ruleConfig: { groups: ['kcc', 'sales', 'board'] }, isInclude: true }
		expect(visibilitySentence({
			name: 'Sanne de Vries',
			moment: TUESDAY,
			rules: [field, groups],
			result: { visible: false, matchedIncludeRuleIds: [], matchedExcludeRuleIds: [] },
			groupNames: GROUP_NAMES,
		})).toBe('Sanne de Vries does not see this widget, because the profile field department of Sanne de Vries does not match and Sanne de Vries is not in KCC, sales or board.')
	})
})

describe('the rule cards', () => {
	it('names each kind', () => {
		expect([KCC, OFFICE_HOURS, HOLIDAY, { ruleType: 'attribute' }, { ruleType: 'weather' }].map(ruleKindLabel))
			.toEqual(['Groups', 'Times', 'Period', 'Profile field', ''])
	})

	it('shows each value', () => {
		expect(ruleValue(OFFICE_HOURS)).toBe('Mon–Fri 08:00–17:00')
		expect(ruleValue({ ruleType: 'time', ruleConfig: { startTime: '09:00', endTime: '12:00' } })).toBe('09:00–12:00')
		expect(ruleValue(HOLIDAY)).toBe('2026-12-24 – 2026-12-26')
		expect(ruleValue({ ruleType: 'date', ruleConfig: { startDate: '2026-12-01' } })).toBe(`2026-12-01 – ${'\u2026'}`)
		expect(ruleValue({ ruleType: 'attribute', ruleConfig: { attribute: 'language', operator: 'equals', value: 'nl' } })).toBe('language Equals nl')
		expect(ruleValue(KCC)).toBe('kcc')
		expect(ruleValue({ ruleType: 'weather' })).toBe('')
	})

	it('writes three or more days in a row as a range', () => {
		expect(dayRanges(['mon', 'tue', 'wed', 'thu', 'fri'])).toBe('Mon–Fri')
		expect(dayRanges(['sat', 'mon', 'sun'])).toBe('Mon, Sat, Sun')
		expect(dayRanges([])).toBe('')
	})
})

describe('helpers', () => {
	it('joins lists with and or or', () => {
		expect(joinList([])).toBe('')
		expect(joinList(['a'])).toBe('a')
		expect(joinList(['a', 'b', 'c'])).toBe('a, b and c')
		expect(joinList(['a', 'b'], true)).toBe('a or b')
	})

	it('says a moment as weekday, day, month and time', () => {
		expect(formatMoment(new Date(2026, 9, 13, 10, 0), 'en-GB')).toBe('Tuesday 13 October 10:00')
		expect(formatMoment('not a date')).toBe('')
	})

	it('picks the hide rules first, then the include rules that decided', () => {
		const rules = [KCC, OFFICE_HOURS, HOLIDAY]
		expect(decidingRules(rules, { visible: false, matchedIncludeRuleIds: [1, 2], matchedExcludeRuleIds: [3] }))
			.toEqual([{ rule: HOLIDAY, matched: true }])
		expect(decidingRules(rules, { visible: true, matchedIncludeRuleIds: [2], matchedExcludeRuleIds: [] }))
			.toEqual([{ rule: OFFICE_HOURS, matched: true }])
	})
})
