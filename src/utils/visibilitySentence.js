/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The words of the rules dialog "Who sees this widget?" (board
 * LpZichtbaarheid): each rule's kind and value as its card shows them, and
 * the "View as" sentence that says whether a person sees the widget at a
 * moment, and why. Pure functions over the rules and the preview result the
 * server answers (`POST /api/visibility/preview`), so the sentence is
 * tested without a dialog. The sentence names the person, never "he" or
 * "she".
 */

import { t } from '@nextcloud/l10n'

/** The days of the week in order, as the time rule stores them. */
const WEEK = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun']

/**
 * The short label of a day of the week.
 *
 * @param {string} day The day as stored (`mon` … `sun`).
 * @return {string} The translated label.
 */
function dayLabel(day) {
	const labels = {
		mon: t('launchpad', 'Mon'),
		tue: t('launchpad', 'Tue'),
		wed: t('launchpad', 'Wed'),
		thu: t('launchpad', 'Thu'),
		fri: t('launchpad', 'Fri'),
		sat: t('launchpad', 'Sat'),
		sun: t('launchpad', 'Sun'),
	}
	return labels[day] || day
}

/**
 * The kind of a rule as its card names it: Groups, Times, Period or
 * Profile field.
 *
 * @param {object} rule A rule `{ruleType, ruleConfig, isInclude}`.
 * @return {string} The translated kind, empty for an unknown type.
 * @spec openspec/changes/archive/2026-11-24-visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md#requirement-the-rules-dialog-as-lpzichtbaarheid-draws-it-req-cvui-006
 */
export function ruleKindLabel(rule) {
	const kinds = {
		group: t('launchpad', 'Groups'),
		time: t('launchpad', 'Times'),
		date: t('launchpad', 'Period'),
		attribute: t('launchpad', 'Profile field'),
	}
	return kinds[rule?.ruleType] || ''
}

/**
 * The groups a group rule names, by their display name when known.
 *
 * @param {object} rule A group rule.
 * @param {{[key: string]: string}} [groupNames] Display name per group id.
 * @return {string[]} The names, in rule order.
 * @spec openspec/changes/archive/2026-11-24-visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md#requirement-the-rules-dialog-as-lpzichtbaarheid-draws-it-req-cvui-006
 */
export function ruleGroupNames(rule, groupNames = {}) {
	const groups = Array.isArray(rule?.ruleConfig?.groups) ? rule.ruleConfig.groups : []
	return groups.map((id) => groupNames[id] || id)
}

/**
 * The stored days as runs of the week: three or more days in a row read
 * "Mon–Fri", fewer are listed.
 *
 * @param {string[]} days The stored days.
 * @return {string} The days, empty when none are set (every day).
 * @spec openspec/changes/archive/2026-11-24-visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md#requirement-the-rules-dialog-as-lpzichtbaarheid-draws-it-req-cvui-006
 */
export function dayRanges(days) {
	const indexes = WEEK.map((day, index) => (days.includes(day) ? index : -1)).filter((index) => index !== -1)
	const runs = []
	for (const index of indexes) {
		const run = runs[runs.length - 1]
		if (run && run[1] === index - 1) {
			run[1] = index
		} else {
			runs.push([index, index])
		}
	}
	return runs.map(([from, to]) => runLabel(from, to)).join(', ')
}

/**
 * One run of days: "Mon–Fri" for three or more, else the days listed.
 *
 * @param {number} from The first day's index in the week.
 * @param {number} to The last day's index in the week.
 * @return {string} The run.
 */
function runLabel(from, to) {
	if (to - from >= 2) {
		return `${dayLabel(WEEK[from])}–${dayLabel(WEEK[to])}`
	}
	return WEEK.slice(from, to + 1).map(dayLabel).join(', ')
}

/**
 * The value of a time, period or profile field rule as its card shows it.
 * A group rule shows its groups as chips instead (see `ruleGroupNames`).
 *
 * @param {object} rule A rule `{ruleType, ruleConfig}`.
 * @return {string} The value, e.g. "Mon–Fri 08:00–17:00".
 * @spec openspec/changes/archive/2026-11-24-visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md#requirement-the-rules-dialog-as-lpzichtbaarheid-draws-it-req-cvui-006
 */
export function ruleValue(rule) {
	const config = rule?.ruleConfig || {}
	const values = {
		group: () => ruleGroupNames(rule).join(', '),
		time: () => [dayRanges(config.days || []), `${config.startTime || '00:00'}–${config.endTime || '23:59'}`].filter(Boolean).join(' '),
		date: () => `${config.startDate || '…'} – ${config.endDate || '…'}`,
		attribute: () => [config.attribute, operatorLabel(config.operator), config.value].filter(Boolean).join(' '),
	}
	const value = values[rule?.ruleType]
	return value ? value() : ''
}

/**
 * The label of a profile field comparison, as the rule editor offers it.
 *
 * @param {string} operator The stored operator.
 * @return {string} The translated label.
 */
function operatorLabel(operator) {
	const labels = {
		equals: t('launchpad', 'Equals'),
		not_equals: t('launchpad', 'Does not equal'),
		contains: t('launchpad', 'Contains'),
		starts_with: t('launchpad', 'Starts with'),
		ends_with: t('launchpad', 'Ends with'),
	}
	return labels[operator || 'equals'] || operator
}

/**
 * A moment as the sentence says it, e.g. "Tuesday 13 October 10:00".
 *
 * @param {Date|string} moment The moment.
 * @param {string} [locale] The reader's language, e.g. `en` or `nl`.
 * @return {string} The moment in words, empty for an invalid moment.
 * @spec openspec/changes/archive/2026-11-24-visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md#requirement-the-rules-dialog-as-lpzichtbaarheid-draws-it-req-cvui-006
 */
export function formatMoment(moment, locale = 'en') {
	const date = moment instanceof Date ? moment : new Date(moment)
	if (Number.isNaN(date.getTime())) {
		return ''
	}
	const day = new Intl.DateTimeFormat(locale, { weekday: 'long', day: 'numeric', month: 'long' }).format(date)
	const time = new Intl.DateTimeFormat(locale, { hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).format(date)
	return `${day.replace(/,/g, '')} ${time}`
}

/**
 * Items joined for a sentence: "a", "a and b", "a, b and c" (or "or").
 *
 * @param {string[]} items The items.
 * @param {boolean} [orList] Join the last item with "or" instead of "and".
 * @return {string} The list.
 * @spec openspec/changes/archive/2026-11-24-visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md#requirement-the-rules-dialog-as-lpzichtbaarheid-draws-it-req-cvui-006
 */
export function joinList(items, orList = false) {
	if (items.length <= 1) {
		return items[0] || ''
	}
	const list = items.slice(0, -1).join(', ')
	const last = items[items.length - 1]
	return orList
		? t('launchpad', '{list} or {last}', { list, last }, undefined, { escape: false })
		: t('launchpad', '{list} and {last}', { list, last }, undefined, { escape: false })
}

/**
 * Why one rule matches or does not, in the person's terms: "Sanne de Vries
 * is in KCC", "Tuesday 13 October 10:00 is within the times".
 *
 * @param {{rule: object, matched: boolean}} deciding The rule, and whether it matched for the person.
 * @param {{name: string, moment: string, groupNames: {[key: string]: string}}} who The person, moment and group names.
 * @return {string} The reason.
 * @spec openspec/changes/archive/2026-11-24-visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md#requirement-the-rules-dialog-as-lpzichtbaarheid-draws-it-req-cvui-006
 */
export function ruleReason({ rule, matched }, who) {
	const vars = {
		name: who.name,
		moment: who.moment,
		groups: joinList(ruleGroupNames(rule, who.groupNames), true),
		field: rule?.ruleConfig?.attribute || '',
	}
	const texts = {
		group: matched ? t('launchpad', '{name} is in {groups}', vars, undefined, { escape: false }) : t('launchpad', '{name} is not in {groups}', vars, undefined, { escape: false }),
		time: matched ? t('launchpad', '{moment} is within the times', vars, undefined, { escape: false }) : t('launchpad', '{moment} is outside the times', vars, undefined, { escape: false }),
		date: matched ? t('launchpad', '{moment} is within the period', vars, undefined, { escape: false }) : t('launchpad', '{moment} is outside the period', vars, undefined, { escape: false }),
		attribute: matched ? t('launchpad', 'the profile field {field} of {name} matches', vars, undefined, { escape: false }) : t('launchpad', 'the profile field {field} of {name} does not match', vars, undefined, { escape: false }),
	}
	return texts[rule?.ruleType] || ''
}

/**
 * The rules that explain the verdict, each with whether it matched: the
 * hide rules that matched; else, for a hidden widget, the include rules
 * that did not match; else the include rules that did.
 *
 * @param {Array<object>} rules The rules, each with the `id` the preview echoes.
 * @param {{visible: boolean, matchedIncludeRuleIds: Array, matchedExcludeRuleIds: Array}} result The preview result.
 * @return {Array<{rule: object, matched: boolean}>} The deciding rules.
 * @spec openspec/changes/archive/2026-11-24-visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md#requirement-the-rules-dialog-as-lpzichtbaarheid-draws-it-req-cvui-006
 */
export function decidingRules(rules, result) {
	const included = new Set(result.matchedIncludeRuleIds || [])
	const excluded = new Set(result.matchedExcludeRuleIds || [])
	const hiding = rules.filter((rule) => rule.isInclude === false && excluded.has(rule.id))
	if (hiding.length > 0) {
		return hiding.map((rule) => ({ rule, matched: true }))
	}
	const include = rules.filter((rule) => rule.isInclude !== false)
	const deciding = include.filter((rule) => included.has(rule.id) === result.visible)
	return deciding.map((rule) => ({ rule, matched: result.visible }))
}

/**
 * The "View as" sentence: whether the person sees the widget at the moment,
 * and why, e.g. "Sanne de Vries sees this widget, because Sanne de Vries is
 * in KCC and Tuesday 13 October 10:00 is within the times."
 *
 * @param {object} input The sentence's input.
 * @param {string} input.name The person's display name.
 * @param {string} input.moment The moment in words (see `formatMoment`).
 * @param {Array<object>} input.rules The rules, with the ids the preview echoes.
 * @param {object} input.result The preview result `{visible, matchedIncludeRuleIds, matchedExcludeRuleIds}`.
 * @param {{[key: string]: string}} [input.groupNames] Display name per group id.
 * @return {string} The sentence, empty without a result or a reason.
 * @spec openspec/changes/archive/2026-11-24-visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md#requirement-the-rules-dialog-as-lpzichtbaarheid-draws-it-req-cvui-006
 */
export function visibilitySentence({ name, moment, rules, result, groupNames = {} }) {
	if (!result) {
		return ''
	}
	const who = { name, moment, groupNames }
	const reasons = joinList(decidingRules(rules || [], result).map((deciding) => ruleReason(deciding, who)))
	if (reasons === '') {
		return result.visible
			? t('launchpad', '{name} sees this widget: no rule limits who sees it.', { name }, undefined, { escape: false })
			: ''
	}
	return result.visible
		? t('launchpad', '{name} sees this widget, because {reasons}.', { name, reasons }, undefined, { escape: false })
		: t('launchpad', '{name} does not see this widget, because {reasons}.', { name, reasons }, undefined, { escape: false })
}
