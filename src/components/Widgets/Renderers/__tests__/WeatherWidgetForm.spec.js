/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `WeatherWidgetForm.vue` (REQ-WEATHER-002/003):
 * location + units-override fields persist correctly; validation requires
 * a location; pre-fills from an existing placement.
 */

import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it } from 'vitest'
import WeatherWidgetForm from '../WeatherWidgetForm.vue'

beforeEach(() => {
	globalThis.t = (_app, key) => key
})

describe('WeatherWidgetForm — REQ-WEATHER-002/003 persisted shape', () => {
	it('assembles {location, unitsOverride}', () => {
		const wrapper = mount(WeatherWidgetForm, {
			propsData: {
				value: { location: 'Amsterdam, NL', unitsOverride: 'imperial' },
			},
		})
		expect(wrapper.vm.assembledContent).toEqual({
			location: 'Amsterdam, NL',
			unitsOverride: 'imperial',
		})
	})

	it('defaults unitsOverride to empty string (follow locale)', () => {
		const wrapper = mount(WeatherWidgetForm, {
			propsData: { value: { location: 'Utrecht' } },
		})
		expect(wrapper.vm.assembledContent.unitsOverride).toBe('')
	})

	it('emits update:content on updateField()', async () => {
		const wrapper = mount(WeatherWidgetForm, {
			propsData: { value: { location: 'Utrecht' } },
		})
		wrapper.vm.updateField('unitsOverride', 'imperial')
		await wrapper.vm.$nextTick()
		const emitted = wrapper.emitted('update:content')
		expect(emitted[emitted.length - 1][0]).toEqual({
			location: 'Utrecht',
			unitsOverride: 'imperial',
		})
	})

	it('pre-fills from editingWidget.content', () => {
		const wrapper = mount(WeatherWidgetForm, {
			propsData: {
				editingWidget: {
					content: { location: 'Tokyo, JP', unitsOverride: 'metric' },
				},
			},
		})
		expect(wrapper.vm.location).toBe('Tokyo, JP')
		expect(wrapper.vm.unitsOverride).toBe('metric')
	})
})

describe('WeatherWidgetForm — validation', () => {
	it('requires a non-empty location', () => {
		const wrapper = mount(WeatherWidgetForm, {
			propsData: { value: { location: '' } },
		})
		expect(wrapper.vm.validate()).toEqual(['Location is required'])
	})

	it('passes validation once a location is set', () => {
		const wrapper = mount(WeatherWidgetForm, {
			propsData: { value: { location: 'Rotterdam' } },
		})
		expect(wrapper.vm.validate()).toEqual([])
	})
})
