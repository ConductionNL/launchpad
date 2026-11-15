/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for how `NewsWidgetForm.vue` reacts to input: the feed
 * add/remove buttons and feed URL fields, numeric fields that ignore
 * non-numbers and clamp the rest, and the metadata filter fields. Every
 * accepted change emits the assembled content.
 */

import { NcTextField } from '@nextcloud/vue'
import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it } from 'vitest'

let NewsWidgetForm

beforeEach(async () => {
	globalThis.t = (_app, key) => key
	NewsWidgetForm = (await import('../NewsWidgetForm.vue')).default
})

function lastEmitted(wrapper) {
	return wrapper.emitted('update:content')?.at(-1)?.[0]
}

describe('NewsWidgetForm input handling', () => {
	it('adds a feed row with the add button and removes the right one with its remove button', async () => {
		const wrapper = mount(NewsWidgetForm, {
			props: { value: { feedUrls: ['https://a.test/rss', 'https://b.test/rss'] } },
		})
		await wrapper.find('.news-widget-form__feed-add').trigger('click')
		expect(lastEmitted(wrapper).feedUrls).toEqual([
			'https://a.test/rss',
			'https://b.test/rss',
			'',
		])
		expect(wrapper.findAll('.news-widget-form__feed-row')).toHaveLength(3)

		await wrapper.findAll('.news-widget-form__feed-remove')[0].trigger('click')
		expect(lastEmitted(wrapper).feedUrls).toEqual(['https://b.test/rss', ''])
		expect(wrapper.findAll('.news-widget-form__feed-row')).toHaveLength(2)
	})

	it('writes a typed feed URL to its own row', async () => {
		const wrapper = mount(NewsWidgetForm, {
			props: { value: { feedUrls: ['https://a.test/rss', ''] } },
		})
		const secondFeedField = wrapper
			.findAll('.news-widget-form__feed-row')[1]
			.findComponent(NcTextField)
		secondFeedField.vm.$emit('update:modelValue', 'https://c.test/atom')
		await wrapper.vm.$nextTick()
		expect(lastEmitted(wrapper).feedUrls).toEqual([
			'https://a.test/rss',
			'https://c.test/atom',
		])
	})

	it('ignores a non-numeric value without emitting', () => {
		const wrapper = mount(NewsWidgetForm)
		const before = wrapper.vm.itemLimit
		wrapper.vm.updateNumericField('itemLimit', 'abc', 1, 50)
		expect(wrapper.vm.itemLimit).toBe(before)
		expect(wrapper.emitted('update:content')).toBeUndefined()
	})

	it('parses and clamps the summary length to 0..5000', () => {
		const wrapper = mount(NewsWidgetForm)
		wrapper.vm.updateNumericField('summaryMaxChars', '120.9', 0, 5000)
		expect(lastEmitted(wrapper).summaryMaxChars).toBe(120)
		wrapper.vm.updateNumericField('summaryMaxChars', '-4', 0, 5000)
		expect(lastEmitted(wrapper).summaryMaxChars).toBe(0)
		wrapper.vm.updateNumericField('summaryMaxChars', '9000', 0, 5000)
		expect(lastEmitted(wrapper).summaryMaxChars).toBe(5000)
	})

	it('sets plain fields and emits them', () => {
		const wrapper = mount(NewsWidgetForm)
		wrapper.vm.updateField('showThumbnails', false)
		expect(lastEmitted(wrapper).showThumbnails).toBe(false)
		wrapper.vm.updateField('dateFormat', 'absolute')
		expect(lastEmitted(wrapper).dateFormat).toBe('absolute')
	})

	it('writes the metadata value, ignores an unknown metadata field, and shows the fields only when enabled', async () => {
		const wrapper = mount(NewsWidgetForm)
		expect(wrapper.find('.news-widget-form__metadata').exists()).toBe(false)

		wrapper.vm.toggleMetadataFilter(true)
		wrapper.vm.updateMetadataField('fieldKey', 'department')
		wrapper.vm.updateMetadataField('value', 'marketing')
		await wrapper.vm.$nextTick()
		expect(wrapper.find('.news-widget-form__metadata').exists()).toBe(true)
		expect(lastEmitted(wrapper).metadataFilter).toEqual({
			fieldKey: 'department',
			value: 'marketing',
		})

		const count = wrapper.emitted('update:content').length
		wrapper.vm.updateMetadataField('operator', 'eq')
		expect(wrapper.emitted('update:content')).toHaveLength(count + 1)
		expect(lastEmitted(wrapper).metadataFilter).toEqual({
			fieldKey: 'department',
			value: 'marketing',
		})
	})

	it('passes validation once an enabled filter has a field key', () => {
		const wrapper = mount(NewsWidgetForm)
		wrapper.vm.toggleMetadataFilter(true)
		expect(wrapper.vm.validate()).toEqual([
			'Metadata field key is required when filter is enabled',
		])
		wrapper.vm.updateMetadataField('fieldKey', '  ')
		expect(wrapper.vm.validate()).toHaveLength(1)
		wrapper.vm.updateMetadataField('fieldKey', 'department')
		expect(wrapper.vm.validate()).toEqual([])
	})
})
