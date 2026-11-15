/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for the rendered controls of `ClockWidgetForm.vue`: each
 * NcSelect / switch writes its field back through `update:modelValue` and
 * the form emits the assembled content; digital-only controls disappear for
 * an analog clock; the timezone picker falls back to a curated list when
 * `Intl.supportedValuesOf` is missing or throws.
 */

import { NcCheckboxRadioSwitch, NcSelect } from '@nextcloud/vue'
import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import ClockWidgetForm from '../ClockWidgetForm.vue'

function lastEmitted(wrapper) {
	return wrapper.emitted('update:content')?.at(-1)?.[0]
}

function selectByLabel(wrapper, label) {
	return wrapper
		.findAllComponents(NcSelect)
		.find((select) => select.props('inputLabel') === label)
}

const DIGITAL = {
	style: 'digital',
	hourFormat: 'auto',
	timezone: 'Europe/Amsterdam',
	showDate: true,
}

beforeEach(() => {
	globalThis.t = (_app, key) => key
})

afterEach(() => {
	vi.restoreAllMocks()
})

describe('ClockWidgetForm rendered controls', () => {
	it('renders style, hour format, timezone and the show-date switch for a digital clock', () => {
		const wrapper = mount(ClockWidgetForm, { props: { value: { ...DIGITAL } } })
		const labels = wrapper
			.findAllComponents(NcSelect)
			.map((select) => select.props('inputLabel'))
		expect(labels).toEqual(['Style', 'Hour format', 'Timezone'])
		expect(wrapper.findComponent(NcCheckboxRadioSwitch).text()).toContain('Show date')
	})

	it('hides the hour format and show-date controls for an analog clock', () => {
		const wrapper = mount(ClockWidgetForm, {
			props: { value: { style: 'analog', timezone: '' } },
		})
		const labels = wrapper
			.findAllComponents(NcSelect)
			.map((select) => select.props('inputLabel'))
		expect(labels).toEqual(['Style', 'Timezone'])
		expect(wrapper.findComponent(NcCheckboxRadioSwitch).exists()).toBe(false)
	})

	it('switching the style select to analog emits the analog shape and removes the digital controls', async () => {
		const wrapper = mount(ClockWidgetForm, { props: { value: { ...DIGITAL } } })
		selectByLabel(wrapper, 'Style').vm.$emit('update:modelValue', 'analog')
		await wrapper.vm.$nextTick()
		expect(lastEmitted(wrapper)).toEqual({
			style: 'analog',
			timezone: 'Europe/Amsterdam',
		})
		expect(selectByLabel(wrapper, 'Hour format')).toBeUndefined()
	})

	it('writes the hour format chosen in its select', async () => {
		const wrapper = mount(ClockWidgetForm, { props: { value: { ...DIGITAL } } })
		selectByLabel(wrapper, 'Hour format').vm.$emit('update:modelValue', '12h')
		await wrapper.vm.$nextTick()
		expect(lastEmitted(wrapper)).toEqual({ ...DIGITAL, hourFormat: '12h' })
	})

	it('writes the show-date switch', async () => {
		const wrapper = mount(ClockWidgetForm, { props: { value: { ...DIGITAL } } })
		wrapper.findComponent(NcCheckboxRadioSwitch).vm.$emit('update:modelValue', false)
		await wrapper.vm.$nextTick()
		expect(lastEmitted(wrapper)).toEqual({ ...DIGITAL, showDate: false })
	})

	it('writes a chosen timezone and stores a cleared one as an empty string', async () => {
		const wrapper = mount(ClockWidgetForm, { props: { value: { ...DIGITAL } } })
		const tz = selectByLabel(wrapper, 'Timezone')
		tz.vm.$emit('update:modelValue', 'Asia/Tokyo')
		await wrapper.vm.$nextTick()
		expect(lastEmitted(wrapper).timezone).toBe('Asia/Tokyo')
		tz.vm.$emit('update:modelValue', null)
		await wrapper.vm.$nextTick()
		expect(lastEmitted(wrapper).timezone).toBe('')
	})
})

describe('ClockWidgetForm timezone fallback', () => {
	const CURATED_FIRST = ['UTC', 'Europe/Amsterdam', 'Europe/London']

	it('uses the curated list when Intl.supportedValuesOf throws', () => {
		vi.spyOn(Intl, 'supportedValuesOf').mockImplementation(() => {
			throw new RangeError('unsupported')
		})
		const wrapper = mount(ClockWidgetForm, { props: { value: {} } })
		const zones = wrapper.vm.timezoneOptions.map((o) => o.value)
		expect(zones).toHaveLength(18)
		expect(zones.slice(0, 3)).toEqual(CURATED_FIRST)
		expect(zones.at(-1)).toBe('Pacific/Auckland')
		expect(selectByLabel(wrapper, 'Timezone').props('options')).toEqual(
			wrapper.vm.timezoneOptions,
		)
	})

	it('uses the curated list when Intl.supportedValuesOf does not exist', () => {
		const original = Intl.supportedValuesOf
		Intl.supportedValuesOf = undefined
		try {
			const wrapper = mount(ClockWidgetForm, { props: { value: {} } })
			const zones = wrapper.vm.timezoneOptions.map((o) => o.value)
			expect(zones).toHaveLength(18)
			expect(zones.slice(0, 3)).toEqual(CURATED_FIRST)
			expect(wrapper.vm.timezoneOptions[0]).toEqual({ value: 'UTC', label: 'UTC' })
		} finally {
			Intl.supportedValuesOf = original
		}
	})
})
