/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The New colleagues page of the people widget (REQ-PPL-014, scenario
 * "Three new colleagues on the intranet"): newest start first as the server
 * sends it, inside the group filter, "Starts Monday" for a future start, and
 * the windows inside their ranges.
 *
 * @spec openspec/changes/people-new-colleagues/specs/people-widget/spec.md#requirement-the-people-widget-can-list-new-colleagues-req-ppl-014
 */

import { describe, expect, it, vi } from 'vitest'
import {
	isNewSelection,
	newColleaguesPage,
	STARTS_FIELD_KEY,
	startsOn,
	utcToday,
	widgetContentFor,
	WINDOW_FILTER_FIELD,
	windowDays,
} from '../newColleagues.js'

vi.mock('@nextcloud/l10n', () => ({ t: (_app, text) => text }))

const ANSWER = {
	configured: true,
	users: [
		{
			id: 'yasmina',
			displayName: 'Yasmina El Amrani',
			startDate: '2026-10-12',
			starts: true,
			role: 'Adviseur',
			organisation: 'Team Wonen',
			groups: ['staff'],
		},
		{
			id: 'lotte',
			displayName: 'Lotte Verhoeven',
			startDate: '2026-10-01',
			starts: false,
			role: 'Jurist',
			organisation: 'Team Recht',
			groups: ['staff'],
			customFields: [
				{ key: 'office', label: 'Office', type: 'text', values: ['B2'] },
			],
		},
		{
			id: 'thomas',
			displayName: 'Thomas Brouwer',
			startDate: '2026-09-28',
			starts: false,
			role: 'Handhaver',
			organisation: 'Team Toezicht',
			groups: ['field'],
		},
	],
}

describe('newColleaguesPage', () => {
	it('keeps the newest-first order and marks the colleague who has not started with the weekday', () => {
		const page = newColleaguesPage(ANSWER, {
			filters: [],
			today: '2026-10-05',
			locale: 'nl',
		})

		expect(page.users.map((user) => user.uid)).toEqual([
			'yasmina',
			'lotte',
			'thomas',
		])
		expect(page.users[0].customFields).toEqual([
			{
				key: STARTS_FIELD_KEY,
				label: 'Starts',
				type: 'text',
				values: ['maandag'],
			},
		])
		expect(page.users[0].role).toBe('Adviseur')
		expect(page.users[0].organisation).toBe('Team Wonen')
		expect(page.users[1].customFields).toEqual([
			{ key: 'office', label: 'Office', type: 'text', values: ['B2'] },
		])
		expect(page).toMatchObject({ total: 3, hasMore: false })
	})

	it("stays inside the placement's groups", () => {
		const page = newColleaguesPage(ANSWER, {
			filters: [{ fieldName: 'group', operator: 'in', values: ['field'] }],
			today: '2026-10-05',
			locale: 'en',
		})

		expect(page.users.map((user) => user.uid)).toEqual(['thomas'])
	})

	it('reads an empty answer as nobody', () => {
		expect(
			newColleaguesPage(
				{ configured: false },
				{ today: '2026-10-05', locale: 'en' },
			).users,
		).toEqual([])
	})
})

describe('startsOn', () => {
	it('names the weekday within a week and the date beyond it', () => {
		expect(startsOn('2026-10-12', '2026-10-05', 'en')).toBe('Monday')
		expect(startsOn('2026-10-06', '2026-10-05', 'nl')).toBe('dinsdag')
		expect(startsOn('2026-10-20', '2026-10-05', 'nl')).toBe('20 okt')
		expect(startsOn('not a date', '2026-10-05', 'en')).toBe('')
	})
})

describe('windows', () => {
	it('uses the stored windows inside their ranges and the defaults otherwise', () => {
		expect(windowDays({}, 'since')).toBe(30)
		expect(windowDays({}, 'until')).toBe(14)
		expect(windowDays({ newSinceDays: 60, newUntilDays: 0 }, 'since')).toBe(60)
		expect(windowDays({ newSinceDays: 60, newUntilDays: 0 }, 'until')).toBe(0)
		expect(windowDays({ newSinceDays: 91, newUntilDays: 31 }, 'since')).toBe(30)
		expect(windowDays({ newSinceDays: 0, newUntilDays: 2.5 }, 'until')).toBe(14)
	})

	it("carries the window as a filter, once, next to the placement's own filters", () => {
		const group = { fieldName: 'group', operator: 'in', values: ['staff'] }
		const content = widgetContentFor({
			selectionMode: 'new',
			newSinceDays: 10,
			filters: [group, { fieldName: WINDOW_FILTER_FIELD, values: [1, 1] }],
		})

		expect(content.filters).toEqual([
			group,
			{
				fieldName: WINDOW_FILTER_FIELD,
				operator: 'between',
				values: [10, 14],
			},
		])
		expect(isNewSelection(content)).toBe(true)
		expect(isNewSelection({ selectionMode: 'filter' })).toBe(false)
	})

	it('reads today in UTC', () => {
		expect(utcToday(new Date('2026-10-05T23:30:00Z'))).toBe('2026-10-05')
	})
})
