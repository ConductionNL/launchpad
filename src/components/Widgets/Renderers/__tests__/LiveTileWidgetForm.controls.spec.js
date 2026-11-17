/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for the rendered controls of `LiveTileWidgetForm.vue`:
 * every text field, select and switch writes back through
 * `update:modelValue` and the form emits the assembled content. Also covers
 * the connector-mode source-id field and validation for an unknown source
 * mode. `liveTileClient.js` (the HTTP layer) is mocked.
 */

import { NcCheckboxRadioSwitch, NcSelect, NcTextField } from '@nextcloud/vue'
import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import LiveTileWidgetForm from '../LiveTileWidgetForm.vue'
import {
	fetchConnectorAvailability,
	validateLiveTileSource,
} from '../../../../services/liveTileClient.js'

vi.mock('../../../../services/liveTileClient.js', () => ({
	fetchConnectorAvailability: vi.fn(),
	validateLiveTileSource: vi.fn(),
}))

async function flushPromises() {
	await new Promise((resolve) => setTimeout(resolve, 0))
	await new Promise((resolve) => setTimeout(resolve, 0))
}

function lastEmitted(wrapper) {
	return (wrapper.emitted('update:content') || []).at(-1)?.[0]
}

function textField(wrapper, label) {
	return wrapper
		.findAllComponents(NcTextField)
		.find((field) => field.props('label') === label)
}

function select(wrapper, label) {
	return wrapper
		.findAllComponents(NcSelect)
		.find((s) => s.props('inputLabel') === label)
}

async function mountForm(value = { url: 'https://a.test/x', valueExpr: '$.n' }) {
	const wrapper = mount(LiveTileWidgetForm, { props: { value } })
	await flushPromises()
	return wrapper
}

beforeEach(() => {
	globalThis.t = (_app, key) => key
	fetchConnectorAvailability.mockReset()
	fetchConnectorAvailability.mockResolvedValue(true)
	validateLiveTileSource.mockReset()
	validateLiveTileSource.mockResolvedValue({ valid: true, errors: [] })
})

describe('LiveTileWidgetForm rendered controls', () => {
	it('writes the label typed into the Label field', async () => {
		const wrapper = await mountForm()
		textField(wrapper, 'Label').vm.$emit('update:modelValue', 'Open tickets')
		await wrapper.vm.$nextTick()
		expect(lastEmitted(wrapper).label).toBe('Open tickets')
		expect(textField(wrapper, 'Label').props('modelValue')).toBe('Open tickets')
	})

	it('writes the value expression field', async () => {
		const wrapper = await mountForm()
		textField(wrapper, 'Value expression').vm.$emit('update:modelValue', '$.data.total')
		await wrapper.vm.$nextTick()
		expect(lastEmitted(wrapper).valueExpr).toBe('$.data.total')
	})

	it('writes prefix, suffix and the thousands switch into format', async () => {
		const wrapper = await mountForm()
		textField(wrapper, 'Prefix').vm.$emit('update:modelValue', '€ ')
		textField(wrapper, 'Suffix').vm.$emit('update:modelValue', ' items')
		wrapper.findComponent(NcCheckboxRadioSwitch).vm.$emit('update:modelValue', true)
		await wrapper.vm.$nextTick()
		expect(lastEmitted(wrapper).format).toEqual({
			prefix: '€ ',
			suffix: ' items',
			thousands: true,
		})
		expect(wrapper.emitted('update:content')).toHaveLength(3)
	})

	it('writes the click-through link URL', async () => {
		const wrapper = await mountForm()
		textField(wrapper, 'Click-through link (optional)').vm.$emit(
			'update:modelValue',
			'https://tickets.test/',
		)
		await wrapper.vm.$nextTick()
		expect(lastEmitted(wrapper).linkUrl).toBe('https://tickets.test/')
	})

	it('writes the link target and falls back to same-tab when cleared', async () => {
		const wrapper = await mountForm()
		const target = select(wrapper, 'Link target')
		target.vm.$emit('update:modelValue', 'new-tab')
		await wrapper.vm.$nextTick()
		expect(lastEmitted(wrapper).linkTarget).toBe('new-tab')
		target.vm.$emit('update:modelValue', null)
		await wrapper.vm.$nextTick()
		expect(lastEmitted(wrapper).linkTarget).toBe('same-tab')
	})

	it('writes the connector source id from its field in connector mode', async () => {
		const wrapper = await mountForm({ sourceMode: 'connector', valueExpr: '$.n' })
		expect(textField(wrapper, 'URL')).toBeUndefined()
		const sourceField = textField(wrapper, 'Integriq source id')
		expect(sourceField.props('disabled')).toBe(false)
		sourceField.vm.$emit('update:modelValue', 'src-42')
		await wrapper.vm.$nextTick()
		expect(lastEmitted(wrapper)).toMatchObject({
			sourceMode: 'connector',
			sourceId: 'src-42',
		})
		expect(wrapper.vm.validate()).toEqual([])
	})
})

describe('LiveTileWidgetForm validation of an unknown source mode', () => {
	it('reports no source errors when the stored mode is neither url nor connector', async () => {
		const wrapper = await mountForm({ sourceMode: 'legacy', valueExpr: '$.n' })
		expect(wrapper.vm.sourceErrors()).toEqual([])
		expect(wrapper.vm.validate()).toEqual([])
	})
})
