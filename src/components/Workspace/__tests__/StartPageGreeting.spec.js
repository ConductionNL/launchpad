/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The start page header greets the reader (board LpVandaag, LpStart): the
 * header placement of the start page template renders the day-part greeting,
 * the first name, the date and the ISO week number.
 *
 * @spec openspec/changes/archive/2026-11-24-start-page-board-sync/specs/lpvandaag-start-page/spec.md#requirement-the-start-page-greets-the-reader
 */

import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => ({
	translate: (_app, text, vars = {}) =>
		text.replace(/\{(\w+)\}/g, (_m, k) => vars[k] ?? `{${k}}`),
	translatePlural: (_app, one, many, count) =>
		(count === 1 ? one : many).replace('%n', String(count)),
	getCanonicalLocale: () => 'en-GB',
	getLanguage: () => 'en',
	getLocale: () => 'en_GB',
}))
vi.mock('@nextcloud/auth', () => ({
	getCurrentUser: () => ({ uid: 'pieter', displayName: 'Pieter Jansen', isAdmin: false }),
}))

import CnHeaderWidget from '@conduction/nextcloud-vue/src/components/CnHeaderWidget/CnHeaderWidget.vue'

/**
 * Mount the header at a given local time with the start page's content.
 *
 * @param {string} at Local date and time, as 2026-10-05T13:10:00.
 * @param {object} content Extra header content.
 * @return {object} The wrapper.
 */
function headerAt(at, content = {}) {
	vi.setSystemTime(new Date(at))
	return mount(CnHeaderWidget, {
		props: { content: { title: 'Vandaag', greeting: true, showDate: true, greetingLine: true, ...content } },
	})
}

beforeEach(() => {
	vi.useFakeTimers()
})

afterEach(() => {
	vi.useRealTimers()
})

describe('Start page greeting', () => {
	it.each([
		['2026-10-05T11:59:00', 'Good morning, Pieter'],
		['2026-10-05T12:00:00', 'Good afternoon, Pieter'],
		['2026-10-05T17:59:00', 'Good afternoon, Pieter'],
		['2026-10-05T18:00:00', 'Good evening, Pieter'],
	])('greets for the part of the day at %s', (at, greeting) => {
		const line = headerAt(at).find('.cn-header-widget__line').text()
		expect(line.startsWith(greeting + ' · ')).toBe(true)
	})

	it('uses the first name only and writes out the date', () => {
		const line = headerAt('2026-10-05T13:10:00').find('.cn-header-widget__line').text()
		expect(line).not.toContain('Jansen')
		expect(line).toContain('Monday')
		expect(line).toContain('5 October 2026')
	})

	it('adds the ISO week number with showWeek', () => {
		const line = headerAt('2026-10-05T13:10:00', { showWeek: true }).find('.cn-header-widget__line').text()
		expect(line.endsWith(' · week 41')).toBe(true)
	})

	it('shows no week number without showWeek', () => {
		const line = headerAt('2026-10-05T13:10:00').find('.cn-header-widget__line').text()
		expect(line).not.toContain('week')
	})

	it('is text a screen reader reads, not an image', () => {
		const wrapper = headerAt('2026-10-05T13:10:00')
		expect(wrapper.find('.cn-header-widget__line').element.tagName).not.toBe('IMG')
		expect(wrapper.find('.cn-header-widget__title').text()).toBe('Vandaag')
	})
})
