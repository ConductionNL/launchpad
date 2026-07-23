/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `ClockWidgetForm.vue` (REQ-CLOCK-002): digital
 * config persists `{style, hourFormat, timezone, showDate}`; analog config
 * persists only `{style, timezone}`; timezone options are IANA
 * identifiers; validate() never fails (no required fields).
 */

import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import ClockWidgetForm from '../ClockWidgetForm.vue'

beforeEach(() => {
	globalThis.t = (_app, key) => key
})

describe('ClockWidgetForm — REQ-CLOCK-002 digital persisted shape', () => {
	it('assembles {style, hourFormat, timezone, showDate} for a digital clock', () => {
		const wrapper = mount(ClockWidgetForm, {
			propsData: { value: { style: 'digital', hourFormat: '24h', timezone: 'Europe/Amsterdam', showDate: true } },
		})
		expect(wrapper.vm.assembledContent).toEqual({
			style: 'digital',
			hourFormat: '24h',
			timezone: 'Europe/Amsterdam',
			showDate: true,
		})
	})

	it('emits update:content with the new field on updateField()', async () => {
		const wrapper = mount(ClockWidgetForm, {
			propsData: { value: { style: 'digital', hourFormat: '24h', timezone: '', showDate: true } },
		})
		wrapper.vm.updateField('hourFormat', '12h')
		await wrapper.vm.$nextTick()
		const emitted = wrapper.emitted('update:content')
		expect(emitted).toBeTruthy()
		expect(emitted[emitted.length - 1][0]).toEqual({
			style: 'digital',
			hourFormat: '12h',
			timezone: '',
			showDate: true,
		})
	})
})

describe('ClockWidgetForm — REQ-CLOCK-002 analog persisted shape', () => {
	it('assembles only {style, timezone} for an analog clock — no hourFormat/showDate', () => {
		const wrapper = mount(ClockWidgetForm, {
			propsData: { value: { style: 'analog', timezone: 'America/New_York' } },
		})
		expect(wrapper.vm.assembledContent).toEqual({
			style: 'analog',
			timezone: 'America/New_York',
		})
		expect(wrapper.vm.assembledContent).not.toHaveProperty('hourFormat')
		expect(wrapper.vm.assembledContent).not.toHaveProperty('showDate')
	})

	it('switching style to analog via updateField drops hourFormat/showDate from the emitted content', async () => {
		const wrapper = mount(ClockWidgetForm, {
			propsData: { value: { style: 'digital', hourFormat: '24h', timezone: 'America/New_York', showDate: true } },
		})
		wrapper.vm.updateField('style', 'analog')
		await wrapper.vm.$nextTick()
		const emitted = wrapper.emitted('update:content')
		expect(emitted[emitted.length - 1][0]).toEqual({
			style: 'analog',
			timezone: 'America/New_York',
		})
	})
})

describe('ClockWidgetForm — timezone picker + validation', () => {
	it('offers a non-empty list of IANA timezone identifiers', () => {
		const wrapper = mount(ClockWidgetForm, { propsData: { value: {} } })
		expect(wrapper.vm.timezoneOptions.length).toBeGreaterThan(0)
		expect(wrapper.vm.timezoneOptions.some((o) => o.value === 'Europe/Amsterdam')).toBe(true)
	})

	it('validate() always returns an empty array (no required fields)', () => {
		const wrapper = mount(ClockWidgetForm, { propsData: { value: {} } })
		expect(wrapper.vm.validate()).toEqual([])
	})

	it('pre-fills from editingWidget.content when editing an existing placement', () => {
		const wrapper = mount(ClockWidgetForm, {
			propsData: {
				editingWidget: { content: { style: 'analog', timezone: 'Asia/Tokyo' } },
			},
		})
		expect(wrapper.vm.style).toBe('analog')
		expect(wrapper.vm.timezone).toBe('Asia/Tokyo')
	})
})
