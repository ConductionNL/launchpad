/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `WeatherWidget.vue` (REQ-WEATHER-001..003). The
 * browser client (`weatherClient.js`) is mocked so these tests never
 * perform a real network call — REQ-WEATHER-002 "upstream failure
 * degrades gracefully" and the loading/stale/error states are exercised
 * deterministically.
 */

import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import WeatherWidget from '../WeatherWidget.vue'
import { fetchWeatherReading } from '../../../../services/weatherClient.js'

vi.mock('../../../../services/weatherClient.js', () => ({
	fetchWeatherReading: vi.fn(),
}))

// Vue 2's @vue/test-utils does not export `flushPromises`.
async function flushPromises() {
	await new Promise((resolve) => setTimeout(resolve, 0))
	await new Promise((resolve) => setTimeout(resolve, 0))
}

beforeEach(() => {
	globalThis.t = (_app, key, vars) => {
		if (vars && typeof key === 'string') {
			return key.replace(/\{(\w+)\}/g, (_, name) =>
				Object.prototype.hasOwnProperty.call(vars, name) ? vars[name] : `{${name}}`)
		}
		return key
	}
	fetchWeatherReading.mockReset()
})

describe('WeatherWidget — REQ-WEATHER-001 fetches via the placement endpoint only', () => {
	it('calls fetchWeatherReading(placement.id) on mount — never a raw provider URL', async () => {
		fetchWeatherReading.mockResolvedValue({
			location: 'Amsterdam, NL',
			tempValue: 18,
			units: 'metric',
			condition: 'partly-cloudy',
			conditionText: 'Partly cloudy',
			language: 'en',
			fetchedAt: '2024-06-15T13:00:00Z',
			stale: false,
		})
		mount(WeatherWidget, { propsData: { content: {}, placement: { id: 42 } } })
		await flushPromises()
		expect(fetchWeatherReading).toHaveBeenCalledWith(42)
	})

	it('renders an error state (never crashes) when the placement has no id yet', async () => {
		const wrapper = mount(WeatherWidget, { propsData: { content: {}, placement: null } })
		await flushPromises()
		expect(fetchWeatherReading).not.toHaveBeenCalled()
		expect(wrapper.find('.weather-widget__state--error').exists()).toBe(true)
	})
})

describe('WeatherWidget — REQ-WEATHER-003 accessible condition + temperature', () => {
	it('renders the condition via an icon AND a text label, and an aria-label on the temperature including units', async () => {
		fetchWeatherReading.mockResolvedValue({
			location: 'Amsterdam, NL',
			tempValue: 18.4,
			units: 'metric',
			condition: 'rain',
			conditionText: 'Light rain',
			language: 'en',
			fetchedAt: '2024-06-15T13:00:00Z',
			stale: false,
		})
		const wrapper = mount(WeatherWidget, { propsData: { content: {}, placement: { id: 1 } } })
		await flushPromises()

		// Icon present.
		expect(wrapper.find('.weather-widget__icon').exists()).toBe(true)
		// Text label present (not colour/icon alone).
		expect(wrapper.find('.weather-widget__condition-text').text()).toBe('Light rain')
		// Temperature carries an accessible label including its units.
		const temp = wrapper.find('.weather-widget__temp')
		expect(temp.text()).toBe('18°C')
		expect(temp.attributes('aria-label')).toContain('Celsius')
	})

	it('renders imperial units in the temperature and its aria-label when units=imperial', async () => {
		fetchWeatherReading.mockResolvedValue({
			location: 'New York, US',
			tempValue: 72,
			units: 'imperial',
			condition: 'clear',
			conditionText: 'Clear sky',
			language: 'en',
			fetchedAt: '2024-06-15T13:00:00Z',
			stale: false,
		})
		const wrapper = mount(WeatherWidget, { propsData: { content: {}, placement: { id: 2 } } })
		await flushPromises()

		const temp = wrapper.find('.weather-widget__temp')
		expect(temp.text()).toBe('72°F')
		expect(temp.attributes('aria-label')).toContain('Fahrenheit')
	})
})

describe('WeatherWidget — REQ-WEATHER-002 stale + error degradation', () => {
	it('shows a stale badge when the reading is marked stale, without erroring', async () => {
		fetchWeatherReading.mockResolvedValue({
			location: 'Amsterdam, NL',
			tempValue: 18,
			units: 'metric',
			condition: 'cloudy',
			conditionText: 'Cloudy',
			language: 'en',
			fetchedAt: '2024-06-15T10:00:00Z',
			stale: true,
		})
		const wrapper = mount(WeatherWidget, { propsData: { content: {}, placement: { id: 3 } } })
		await flushPromises()
		expect(wrapper.find('.weather-widget__badge').exists()).toBe(true)
	})

	it('renders the error state — never throws — when the endpoint call rejects', async () => {
		fetchWeatherReading.mockRejectedValue(new Error('network error'))
		const wrapper = mount(WeatherWidget, { propsData: { content: {}, placement: { id: 4 } } })
		await flushPromises()
		expect(wrapper.find('.weather-widget__state--error').exists()).toBe(true)
	})

	it('renders the error state when the endpoint returns an error shape with no cached reading', async () => {
		fetchWeatherReading.mockResolvedValue({ error: 'no_cached_reading' })
		const wrapper = mount(WeatherWidget, { propsData: { content: {}, placement: { id: 5 } } })
		await flushPromises()
		expect(wrapper.find('.weather-widget__state--error').exists()).toBe(true)
	})

	it('retry button re-invokes fetchWeatherReading', async () => {
		fetchWeatherReading.mockRejectedValueOnce(new Error('boom'))
		const wrapper = mount(WeatherWidget, { propsData: { content: {}, placement: { id: 6 } } })
		await flushPromises()
		expect(wrapper.find('.weather-widget__state--error').exists()).toBe(true)

		fetchWeatherReading.mockResolvedValueOnce({
			location: 'Amsterdam, NL',
			tempValue: 20,
			units: 'metric',
			condition: 'clear',
			conditionText: 'Clear',
			language: 'en',
			fetchedAt: '2024-06-15T13:00:00Z',
			stale: false,
		})
		await wrapper.find('.weather-widget__retry').trigger('click')
		await flushPromises()
		expect(fetchWeatherReading).toHaveBeenCalledTimes(2)
		expect(wrapper.find('.weather-widget__state--error').exists()).toBe(false)
	})
})
