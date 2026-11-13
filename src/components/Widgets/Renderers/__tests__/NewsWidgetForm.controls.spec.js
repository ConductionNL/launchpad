/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for the rendered controls of `NewsWidgetForm.vue`: the
 * layout and date-format selects, the numeric item-limit and summary-length
 * fields, the thumbnail/summary/metadata switches and the metadata fields
 * each write back through `update:modelValue` and emit the assembled
 * content. Also pins that a well-formed feed URL passes validation.
 */

import { NcCheckboxRadioSwitch, NcSelect, NcTextField } from '@nextcloud/vue'
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

function select(wrapper, label) {
	return wrapper
		.findAllComponents(NcSelect)
		.find((s) => s.props('inputLabel') === label)
}

function textField(wrapper, label) {
	return wrapper
		.findAllComponents(NcTextField)
		.find((field) => field.props('label') === label)
}

function toggle(wrapper, text) {
	return wrapper
		.findAllComponents(NcCheckboxRadioSwitch)
		.find((s) => s.text().includes(text))
}

describe('NewsWidgetForm rendered controls', () => {
	it('writes the layout chosen in its select', async () => {
		const wrapper = mount(NewsWidgetForm)
		select(wrapper, 'Layout').vm.$emit('update:modelValue', 'grid')
		await wrapper.vm.$nextTick()
		expect(lastEmitted(wrapper).layout).toBe('grid')
		expect(select(wrapper, 'Layout').props('modelValue')).toBe('grid')
	})

	it('writes the date format chosen in its select', async () => {
		const wrapper = mount(NewsWidgetForm)
		select(wrapper, 'Date format').vm.$emit('update:modelValue', 'absolute')
		await wrapper.vm.$nextTick()
		expect(lastEmitted(wrapper).dateFormat).toBe('absolute')
	})

	it('parses and clamps the item limit typed into its field', async () => {
		const wrapper = mount(NewsWidgetForm)
		const field = textField(wrapper, 'Item limit (1–50)')
		field.vm.$emit('update:modelValue', '25')
		await wrapper.vm.$nextTick()
		expect(lastEmitted(wrapper).itemLimit).toBe(25)
		field.vm.$emit('update:modelValue', '500')
		await wrapper.vm.$nextTick()
		expect(lastEmitted(wrapper).itemLimit).toBe(50)
		expect(textField(wrapper, 'Item limit (1–50)').props('modelValue')).toBe('50')
	})

	it('parses and clamps the summary length typed into its field', async () => {
		const wrapper = mount(NewsWidgetForm)
		textField(wrapper, 'Summary max characters').vm.$emit('update:modelValue', '-10')
		await wrapper.vm.$nextTick()
		expect(lastEmitted(wrapper).summaryMaxChars).toBe(0)
	})

	it('writes the thumbnail and summary switches', async () => {
		const wrapper = mount(NewsWidgetForm)
		toggle(wrapper, 'Show thumbnails').vm.$emit('update:modelValue', false)
		await wrapper.vm.$nextTick()
		expect(lastEmitted(wrapper)).toMatchObject({
			showThumbnails: false,
			showSummary: true,
		})
		toggle(wrapper, 'Show summary').vm.$emit('update:modelValue', false)
		await wrapper.vm.$nextTick()
		expect(lastEmitted(wrapper)).toMatchObject({
			showThumbnails: false,
			showSummary: false,
		})
	})

	it('reveals the metadata fields from the switch and writes both of them', async () => {
		const wrapper = mount(NewsWidgetForm)
		expect(textField(wrapper, 'Metadata field key')).toBeUndefined()

		toggle(wrapper, 'Filter by dashboard metadata').vm.$emit('update:modelValue', true)
		await wrapper.vm.$nextTick()
		textField(wrapper, 'Metadata field key').vm.$emit('update:modelValue', 'department')
		textField(wrapper, 'Metadata value to match').vm.$emit('update:modelValue', 'sales')
		await wrapper.vm.$nextTick()
		expect(lastEmitted(wrapper).metadataFilter).toEqual({
			fieldKey: 'department',
			value: 'sales',
		})
	})
})

describe('NewsWidgetForm feed URL validation', () => {
	it('accepts http and https feed URLs', () => {
		const wrapper = mount(NewsWidgetForm, {
			props: {
				value: { feedUrls: ['https://a.test/rss', ' HTTP://b.test/atom '] },
			},
		})
		expect(wrapper.vm.validate()).toEqual([])
	})

	it('reports only the bad row among valid ones', () => {
		const wrapper = mount(NewsWidgetForm, {
			props: { value: { feedUrls: ['https://a.test/rss', 'ftp://c.test/rss'] } },
		})
		expect(wrapper.vm.validate()).toEqual([
			'Feed URL must start with http:// or https://',
		])
	})
})
