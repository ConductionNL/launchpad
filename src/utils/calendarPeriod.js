/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The Calendar form's period (REQ-CAL-020) over the content the Calendar
 * renderer reads: 7 days is the agenda over seven days, Month the month view.
 */

/**
 * The period of stored content: Month for the month view, else 7 days.
 *
 * @param {{viewMode?: string}|null} content The stored content.
 * @return {'week'|'month'} the period.
 * @spec openspec/changes/archive/2026-11-25-widget-content-forms-board-sync/specs/calendar-widget/spec.md#requirement-the-calendar-content-form-req-cal-020
 */
export function periodOf(content) {
	return content?.viewMode === 'month' ? 'month' : 'week'
}

/**
 * The renderer fields for a period.
 *
 * @param {'week'|'month'} period The period.
 * @return {{viewMode: string, daysAhead: number}} the fields.
 * @spec openspec/changes/archive/2026-11-25-widget-content-forms-board-sync/specs/calendar-widget/spec.md#requirement-the-calendar-content-form-req-cal-020
 */
export function contentForPeriod(period) {
	return period === 'month'
		? { viewMode: 'month', daysAhead: 31 }
		: { viewMode: 'agenda', daysAhead: 7 }
}

/**
 * Whether an address can be an external calendar: https with a host.
 *
 * @param {string} address The address.
 * @return {boolean}
 * @spec openspec/changes/archive/2026-11-25-widget-content-forms-board-sync/specs/calendar-widget/spec.md#requirement-the-calendar-content-form-req-cal-020
 */
export function isIcsAddress(address) {
	return typeof address === 'string' && /^https:\/\/[^\s/]+/i.test(address.trim())
}
