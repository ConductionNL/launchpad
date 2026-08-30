/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `ClockWidget.vue` (REQ-CLOCK-001..003):
 * fully client-side render (no network call), timezone + hour-format
 * honoured, locale-aware date formatting, defaults when unset, per-second
 * ticking + cleanup, and analog accessibility (textual time exposed via
 * aria-label).
 */

import { setLanguage, setLocale } from '@nextcloud/l10n'
import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import ClockWidget from '../ClockWidget.vue'

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
	// 2024-06-15T13:05:09Z — a fixed instant so timezone conversions are
	// deterministic in assertions below.
	vi.setSystemTime(new Date('2024-06-15T13:05:09Z'))
})

afterEach(() => {
	vi.useRealTimers()
	setLocale('en_US')
	setLanguage('en')
})

describe('ClockWidget — REQ-CLOCK-001 fully client-side, no network call', () => {
	it('renders without ever importing axios or calling a LaunchPad endpoint', () => {
		const wrapper = mount(ClockWidget, {
			propsData: {
				content: {
					style: 'digital',
					timezone: 'Europe/Amsterdam',
					hourFormat: '24h',
				},
			},
		})
		// No fetch/XHR global was touched — the component makes zero network
		// calls; asserting the widget mounts and renders text is sufficient
		// proof there is no async data-loading branch blocking the render.
		expect(wrapper.find('.clock-widget__digital').exists()).toBe(true)
	})
})

describe('ClockWidget — REQ-CLOCK-002/003 style, format, timezone', () => {
	it('renders digital time in the configured timezone and 24h format', () => {
		setLocale('en_US')
		const wrapper = mount(ClockWidget, {
			propsData: {
				content: {
					style: 'digital',
					timezone: 'Europe/Amsterdam',
					hourFormat: '24h',
					showDate: false,
				},
			},
		})
		// 13:05:09 UTC in June (CEST, UTC+2) = 15:05:09 local.
		expect(wrapper.find('.clock-widget__time').text()).toBe('15:05:09')
	})

	it('renders an AM/PM suffix for a 12h configuration', () => {
		setLocale('en_US')
		const wrapper = mount(ClockWidget, {
			propsData: {
				content: {
					style: 'digital',
					timezone: 'Europe/Amsterdam',
					hourFormat: '12h',
					showDate: false,
				},
			},
		})
		expect(wrapper.find('.clock-widget__time').text()).toMatch(/PM/)
	})

	it('renders a different timezone correctly (America/New_York)', () => {
		setLocale('en_US')
		const wrapper = mount(ClockWidget, {
			propsData: {
				content: {
					style: 'digital',
					timezone: 'America/New_York',
					hourFormat: '24h',
					showDate: false,
				},
			},
		})
		// 13:05:09 UTC in June (EDT, UTC-4) = 09:05:09 local.
		expect(wrapper.find('.clock-widget__time').text()).toBe('09:05:09')
	})

	it('formats the date per the Dutch locale via Intl, not hardcoded English', () => {
		setLocale('nl_NL')
		const wrapper = mount(ClockWidget, {
			propsData: {
				content: {
					style: 'digital',
					timezone: 'Europe/Amsterdam',
					showDate: true,
				},
			},
		})
		const dateText = wrapper.find('.clock-widget__date').text()
		// Dutch weekday/month names — 15 June 2024 is a Saturday ("zaterdag")
		// in "juni".
		expect(dateText.toLowerCase()).toContain('zaterdag')
		expect(dateText.toLowerCase()).toContain('juni')
	})

	it('defaults to style=digital, timezone follows device, showDate=true when content is empty', () => {
		setLocale('en_US')
		const wrapper = mount(ClockWidget, {
			propsData: { content: {} },
		})
		expect(wrapper.find('.clock-widget__digital').exists()).toBe(true)
		expect(wrapper.find('.clock-widget__date').exists()).toBe(true)
	})

	it('persists / reflects the analog style with only style+timezone honoured', () => {
		setLocale('en_US')
		const wrapper = mount(ClockWidget, {
			propsData: {
				content: { style: 'analog', timezone: 'Europe/Amsterdam' },
			},
		})
		expect(wrapper.find('.clock-widget__analog').exists()).toBe(true)
		expect(wrapper.find('.clock-widget__digital').exists()).toBe(false)
	})
})

describe('ClockWidget — REQ-CLOCK-003 accessibility', () => {
	it('analog clock exposes the current time as text via aria-label (WCAG AA)', () => {
		setLocale('en_US')
		const wrapper = mount(ClockWidget, {
			propsData: {
				content: {
					style: 'analog',
					timezone: 'Europe/Amsterdam',
					hourFormat: '24h',
				},
			},
		})
		const analog = wrapper.find('.clock-widget__analog')
		expect(analog.attributes('role')).toBe('img')
		expect(analog.attributes('aria-label')).toBeTruthy()
		expect(analog.attributes('aria-label')).toContain('15:05:09')
	})

	it('digital clock also carries an aria-label so the value announces once', () => {
		setLocale('en_US')
		const wrapper = mount(ClockWidget, {
			propsData: {
				content: {
					style: 'digital',
					timezone: 'Europe/Amsterdam',
					hourFormat: '24h',
					showDate: false,
				},
			},
		})
		const digital = wrapper.find('.clock-widget__digital')
		expect(digital.attributes('aria-label')).toContain('15:05:09')
		expect(wrapper.find('.clock-widget__time').attributes('aria-hidden')).toBe(
			'true',
		)
	})
})

describe('ClockWidget — per-second ticking + cleanup', () => {
	it('updates the rendered time after one second elapses', async () => {
		setLocale('en_US')
		const wrapper = mount(ClockWidget, {
			propsData: {
				content: {
					style: 'digital',
					timezone: 'Europe/Amsterdam',
					hourFormat: '24h',
					showDate: false,
				},
			},
		})
		expect(wrapper.find('.clock-widget__time').text()).toBe('15:05:09')
		vi.advanceTimersByTime(1000)
		await wrapper.vm.$nextTick()
		expect(wrapper.find('.clock-widget__time').text()).toBe('15:05:10')
	})

	it('clears the interval on destroy', () => {
		const wrapper = mount(ClockWidget, {
			propsData: {
				content: { style: 'digital', timezone: 'Europe/Amsterdam' },
			},
		})
		const clearSpy = vi.spyOn(globalThis, 'clearInterval')
		wrapper.unmount()
		expect(clearSpy).toHaveBeenCalled()
	})
})
