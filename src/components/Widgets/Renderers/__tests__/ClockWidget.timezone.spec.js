/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `ClockWidget.vue` (REQ-CLOCK-003): an unknown IANA
 * timezone falls back to the device zone for both the time and the date
 * line instead of throwing and blanking the tile.
 */

import { setLanguage, setLocale } from '@nextcloud/l10n'
import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import ClockWidget from '../ClockWidget.vue'

const INSTANT = new Date('2024-06-15T13:05:09Z')

beforeEach(() => {
	globalThis.t = (_app, key, vars) => {
		if (vars && typeof key === 'string') {
			return key.replace(/\{(\w+)\}/g, (_, name) =>
				Object.hasOwn(vars, name) ? vars[name] : `{${name}}`,
			)
		}
		return key
	}
	vi.useFakeTimers()
	vi.setSystemTime(INSTANT)
	setLocale('en_US')
})

afterEach(() => {
	vi.useRealTimers()
	setLocale('en_US')
	setLanguage('en')
})

describe('ClockWidget — REQ-CLOCK-003 unknown timezone falls back to the device zone', () => {
	it('renders device-zone time and date for an invalid IANA zone', () => {
		const wrapper = mount(ClockWidget, {
			propsData: {
				content: {
					style: 'digital',
					timezone: 'Not/A_Zone',
					hourFormat: '24h',
					showDate: true,
				},
			},
		})
		const expectedTime = new Intl.DateTimeFormat('en-US', {
			hour: '2-digit',
			minute: '2-digit',
			second: '2-digit',
			hour12: false,
		}).format(INSTANT)
		const expectedDate = new Intl.DateTimeFormat('en-US', {
			weekday: 'long',
			year: 'numeric',
			month: 'long',
			day: 'numeric',
		}).format(INSTANT)
		expect(wrapper.find('.clock-widget__time').text()).toBe(expectedTime)
		expect(wrapper.find('.clock-widget__date').text()).toBe(expectedDate)
		expect(
			wrapper.find('.clock-widget__digital').attributes('aria-label'),
		).toBe(`${expectedDate}, ${expectedTime}`)
	})

	it('keeps the analog face labelled with the device-zone time for an invalid zone', () => {
		const wrapper = mount(ClockWidget, {
			propsData: { content: { style: 'analog', timezone: 'Mars/Olympus' } },
		})
		const expectedTime = new Intl.DateTimeFormat('en-US', {
			hour: '2-digit',
			minute: '2-digit',
			second: '2-digit',
		}).format(INSTANT)
		expect(
			wrapper.find('.clock-widget__analog').attributes('aria-label'),
		).toBe(expectedTime)
	})
})

describe('ClockWidget — REQ-CLOCK-002 an unset timezone is the viewer\'s Nextcloud timezone', () => {
	it('shows the injected user timezone when the clock names none', () => {
		const wrapper = mount(ClockWidget, {
			propsData: { content: { style: 'digital', hourFormat: '24h', showDate: false } },
			global: { provide: { userTimezone: 'Asia/Tokyo' } },
		})
		// 13:05:09 UTC is 22:05:09 in Tokyo.
		expect(wrapper.find('.clock-widget__time').text()).toBe('22:05:09')
	})

	it('keeps a configured timezone over the user\'s', () => {
		const wrapper = mount(ClockWidget, {
			propsData: { content: { style: 'digital', hourFormat: '24h', timezone: 'Europe/London', showDate: false } },
			global: { provide: { userTimezone: 'Asia/Tokyo' } },
		})
		expect(wrapper.find('.clock-widget__time').text()).toBe('14:05:09')
	})
})
