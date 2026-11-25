/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The Calendar content form (REQ-CAL-020): the reader's calendars, external
 * calendars by ICS address, and the period 7 days or Month.
 */

import { NcCheckboxRadioSwitch, NcTextField } from '@nextcloud/vue'
import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import CalendarWidgetForm from '../CalendarWidgetForm.vue'

async function flushPromises() {
	await new Promise((resolve) => setTimeout(resolve, 0))
	await new Promise((resolve) => setTimeout(resolve, 0))
}

function lastEmitted(wrapper) {
	return (wrapper.emitted('update:content') || []).at(-1)?.[0]
}

function icsField(wrapper) {
	return wrapper.findAllComponents(NcTextField).find((field) => field.props('label') === 'External calendar (ICS address)')
}

const CALENDARS = [
	{ key: 'personal', name: 'Personal', color: '#0082c9' },
	{ key: 'team', name: 'Team', color: '#e9322d' },
]

async function mountForm(props = {}) {
	const wrapper = mount(CalendarWidgetForm, { props: { calendarsFetcher: vi.fn().mockResolvedValue(CALENDARS), ...props } })
	await flushPromises()
	return wrapper
}

describe('CalendarWidgetForm calendars', () => {
	it('lists the reader\'s calendars and stores the chosen ones', async () => {
		const wrapper = await mountForm()
		const toggles = wrapper.findAllComponents(NcCheckboxRadioSwitch)
		expect(toggles.map((toggle) => toggle.text())).toEqual(['Personal', 'Team'])
		toggles[1].vm.$emit('update:modelValue', true)
		await wrapper.vm.$nextTick()
		expect(lastEmitted(wrapper).internalCalendars).toEqual(['team'])
		toggles[1].vm.$emit('update:modelValue', false)
		await wrapper.vm.$nextTick()
		expect(lastEmitted(wrapper).internalCalendars).toEqual([])
	})

	it('keeps a stored calendar that is no longer offered visible', async () => {
		const wrapper = await mountForm({ value: { internalCalendars: ['gone'] } })
		expect(wrapper.findAllComponents(NcCheckboxRadioSwitch).map((toggle) => toggle.text())).toContain('gone')
	})

	it('works without a fetcher', async () => {
		const wrapper = mount(CalendarWidgetForm)
		await flushPromises()
		expect(wrapper.findAllComponents(NcCheckboxRadioSwitch)).toHaveLength(0)
	})
})

describe('CalendarWidgetForm add an external calendar (scenario)', () => {
	it('adds an ICS address with 7 days', async () => {
		const wrapper = await mountForm({ value: { internalCalendars: ['personal'], viewMode: 'month' } })
		icsField(wrapper).vm.$emit('update:modelValue', 'https://example.com/holidays.ics')
		await wrapper.vm.$nextTick()
		wrapper.vm.addIcs()
		await wrapper.find('[data-testid="calendar-period-week"]').trigger('change')
		expect(lastEmitted(wrapper)).toEqual({
			internalCalendars: ['personal'],
			externalIcsUrls: ['https://example.com/holidays.ics'],
			viewMode: 'agenda',
			daysAhead: 7,
			colorByCalendar: true,
		})
		expect(wrapper.text()).toContain('https://example.com/holidays.ics')
		expect(wrapper.vm.validate()).toEqual([])
	})

	it('refuses an address that is not https and removes an added one', async () => {
		const wrapper = await mountForm({ value: { externalIcsUrls: ['https://a.test/a.ics'] } })
		icsField(wrapper).vm.$emit('update:modelValue', 'http://a.test/b.ics')
		await wrapper.vm.$nextTick()
		expect(wrapper.vm.validate()).toEqual(['An ICS address starts with https://'])
		wrapper.vm.addIcs()
		await wrapper.vm.$nextTick()
		expect(icsField(wrapper).props('helperText')).toBe('An ICS address starts with https://')
		wrapper.vm.removeIcs(0)
		expect(lastEmitted(wrapper).externalIcsUrls).toEqual([])
	})

	it('keeps a valid address typed but not added', async () => {
		const wrapper = await mountForm()
		icsField(wrapper).vm.$emit('update:modelValue', 'https://a.test/c.ics')
		await wrapper.vm.$nextTick()
		expect(wrapper.vm.validate()).toEqual([])
		expect(wrapper.vm.assembledContent.externalIcsUrls).toEqual(['https://a.test/c.ics'])
	})

	it('asks for a calendar when none is chosen', async () => {
		const wrapper = await mountForm()
		expect(wrapper.vm.validate()).toEqual(['Choose at least one calendar or add an ICS address'])
	})
})

describe('CalendarWidgetForm period', () => {
	it('Month stores the month view', async () => {
		const wrapper = await mountForm({ value: { internalCalendars: ['team'] } })
		expect(wrapper.find('[data-testid="calendar-period-week"]').element.checked).toBe(true)
		await wrapper.find('[data-testid="calendar-period-month"]').trigger('change')
		expect(lastEmitted(wrapper)).toMatchObject({ viewMode: 'month', daysAhead: 31 })
	})
})
