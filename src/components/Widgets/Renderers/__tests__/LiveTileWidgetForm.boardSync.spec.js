/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The Live data form as board LpWidgetInstellen draws it (REQ-LIVETILE-020):
 * choice cards, the chosen connection with its last success, the view, unit
 * and refresh, the formatting fields and a preview that follows them.
 */

import { NcCheckboxRadioSwitch, NcSelect, NcTextField } from '@nextcloud/vue'
import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import LiveTileWidget from '../LiveTileWidget.vue'
import LiveTileWidgetForm from '../LiveTileWidgetForm.vue'
import {
	fetchConnectorAvailability,
	fetchLiveTileValue,
	validateLiveTileSource,
} from '../../../../services/liveTileClient.js'
import { fetchLiveTileConnections } from '../../../../services/liveTileConnections.js'

vi.mock('../../../../services/liveTileClient.js', () => ({
	fetchConnectorAvailability: vi.fn(),
	fetchLiveTileValue: vi.fn(),
	validateLiveTileSource: vi.fn(),
}))
vi.mock('../../../../services/liveTileConnections.js', () => ({
	fetchLiveTileConnections: vi.fn(),
}))

async function flushPromises() {
	await new Promise((resolve) => setTimeout(resolve, 0))
	await new Promise((resolve) => setTimeout(resolve, 0))
}

function lastEmitted(wrapper) {
	return (wrapper.emitted('update:content') || []).at(-1)?.[0]
}

function textField(wrapper, label) {
	return wrapper.findAllComponents(NcTextField).find((field) => field.props('label') === label)
}

function select(wrapper, label) {
	return wrapper.findAllComponents(NcSelect).find((s) => s.props('inputLabel') === label)
}

function toggle(wrapper, text) {
	return wrapper.findAllComponents(NcCheckboxRadioSwitch).find((s) => s.text().includes(text))
}

const KCC = { value: 'kcc-queue', label: 'Contact centre queue', lastSuccessAt: '2026-11-25T10:38:00Z' }

beforeEach(() => {
	fetchConnectorAvailability.mockReset()
	fetchConnectorAvailability.mockResolvedValue(true)
	fetchLiveTileValue.mockReset()
	fetchLiveTileValue.mockResolvedValue({ value: 12 })
	validateLiveTileSource.mockReset()
	validateLiveTileSource.mockResolvedValue({ valid: true, errors: [] })
	fetchLiveTileConnections.mockReset()
	fetchLiveTileConnections.mockResolvedValue([KCC])
})

describe('LiveTileWidgetForm source cards', () => {
	it('offers Connection and Web address, and choosing a card switches the source', async () => {
		const wrapper = mount(LiveTileWidgetForm, { props: { value: { url: 'https://a.test/x', valueExpr: '$.n' } } })
		await flushPromises()
		expect(wrapper.find('[data-testid="live-tile-source-url"]').element.checked).toBe(true)
		await wrapper.find('[data-testid="live-tile-source-connector"]').trigger('change')
		expect(lastEmitted(wrapper).sourceMode).toBe('connector')
		expect(textField(wrapper, 'URL')).toBeUndefined()
		expect(select(wrapper, 'Connection')).toBeDefined()
	})

	it('disables the Connection card without integriq', async () => {
		fetchConnectorAvailability.mockResolvedValue(false)
		const wrapper = mount(LiveTileWidgetForm, { props: { value: { url: 'https://a.test/x', valueExpr: '$.n' } } })
		await flushPromises()
		expect(wrapper.find('[data-testid="live-tile-source-connector"]').element.disabled).toBe(true)
	})

	it('falls back to the source id field when no connection is listed', async () => {
		fetchLiveTileConnections.mockResolvedValue([])
		const wrapper = mount(LiveTileWidgetForm, { props: { value: { sourceMode: 'connector', valueExpr: '$.n' } } })
		await flushPromises()
		expect(select(wrapper, 'Connection')).toBeUndefined()
		expect(textField(wrapper, 'Integriq source id')).toBeDefined()
	})
})

describe('LiveTileWidgetForm KCC waiting time (scenario)', () => {
	it('shows the connection\'s last success and a preview with the unit and the refresh', async () => {
		const wrapper = mount(LiveTileWidgetForm, {
			props: { editingWidget: { id: 9, content: { sourceMode: 'connector', valueExpr: '', label: 'KCC waiting time' } } },
		})
		await flushPromises()
		select(wrapper, 'Connection').vm.$emit('update:modelValue', 'kcc-queue')
		textField(wrapper, 'Path in the response').vm.$emit('update:modelValue', 'wait.minutes')
		textField(wrapper, 'Unit (optional)').vm.$emit('update:modelValue', 'minutes')
		await wrapper.vm.$nextTick()

		expect(wrapper.find('[data-testid="live-tile-last-success"]').text()).toContain('Last success:')
		expect(wrapper.find('[data-testid="live-tile-last-success"]').text()).not.toContain('Not yet')
		expect(fetchLiveTileValue).toHaveBeenCalledWith(9)
		const preview = wrapper.find('[data-testid="live-tile-preview"]')
		expect(preview.text()).toContain('KCC waiting time')
		expect(preview.text()).toContain('12 minutes')
		expect(preview.text()).toContain('Refreshes every 5 minutes')
		expect(lastEmitted(wrapper)).toMatchObject({
			sourceMode: 'connector',
			sourceId: 'kcc-queue',
			valueExpr: 'wait.minutes',
			format: { suffix: ' minutes' },
			refresh: 300,
		})
		expect(wrapper.vm.validate()).toEqual([])
	})

	it('says Not yet for a connection that never succeeded', async () => {
		fetchLiveTileConnections.mockResolvedValue([{ ...KCC, lastSuccessAt: null }])
		const wrapper = mount(LiveTileWidgetForm, { props: { value: { sourceMode: 'connector', sourceId: 'kcc-queue', valueExpr: '$.n' } } })
		await flushPromises()
		expect(wrapper.find('[data-testid="live-tile-last-success"]').text()).toContain('Not yet')
	})
})

describe('LiveTileWidgetForm view, refresh and formatting', () => {
	it('chooses the view with the view radios', async () => {
		const wrapper = mount(LiveTileWidgetForm, { props: { value: { url: 'https://a.test/x', valueExpr: '$.n' } } })
		await flushPromises()
		await wrapper.find('[data-testid="live-tile-view-table"]').trigger('change')
		expect(lastEmitted(wrapper).display).toBe('table')
	})

	it('sets the refresh from its choices and the preview follows', async () => {
		const wrapper = mount(LiveTileWidgetForm, { props: { value: { url: 'https://a.test/x', valueExpr: '$.n' } } })
		await flushPromises()
		select(wrapper, 'Refresh').vm.$emit('update:modelValue', 900)
		await wrapper.vm.$nextTick()
		expect(lastEmitted(wrapper).refresh).toBe(900)
		expect(wrapper.find('[data-testid="live-tile-preview"]').text()).toContain('Refreshes every 15 minutes')
	})

	it('stores show title, colour and border only when they differ from the default', async () => {
		const wrapper = mount(LiveTileWidgetForm, { props: { value: { url: 'https://a.test/x', valueExpr: '$.n', label: 'Open' } } })
		await flushPromises()
		expect(wrapper.vm.assembledContent).not.toHaveProperty('showTitle')
		expect(wrapper.vm.assembledContent).not.toHaveProperty('colour')
		expect(wrapper.vm.assembledContent).not.toHaveProperty('border')

		toggle(wrapper, 'Show title').vm.$emit('update:modelValue', false)
		select(wrapper, 'Color').vm.$emit('update:modelValue', 'warning')
		toggle(wrapper, 'Border').vm.$emit('update:modelValue', true)
		await wrapper.vm.$nextTick()
		expect(lastEmitted(wrapper)).toMatchObject({ showTitle: false, colour: 'warning', border: true })
		const preview = wrapper.find('[data-testid="live-tile-preview"]')
		expect(preview.text()).not.toContain('Open')
		expect(preview.classes()).toContain('live-tile-colour--warning')
		expect(preview.classes()).toContain('live-tile-widget-form__preview--bordered')
	})

	it('drops an unknown stored colour', async () => {
		const wrapper = mount(LiveTileWidgetForm, { props: { value: { url: 'https://a.test/x', valueExpr: '$.n', colour: '#123456' } } })
		await flushPromises()
		expect(wrapper.vm.colour).toBe('')
	})
})

describe('LiveTileWidget renders the formatting', () => {
	it('hides the title, and takes the colour and border', async () => {
		fetchLiveTileValue.mockResolvedValue({ value: 3, formatted: '3', badge: null, stale: false })
		const wrapper = mount(LiveTileWidget, {
			props: { content: { label: 'Open', showTitle: false, colour: 'success', border: true }, placement: { id: 1 } },
		})
		await flushPromises()
		expect(wrapper.find('.live-tile-widget__label').exists()).toBe(false)
		expect(wrapper.classes()).toContain('live-tile-colour--success')
		expect(wrapper.classes()).toContain('live-tile-widget--bordered')
	})

	it('shows the title by default and ignores an unknown colour', async () => {
		fetchLiveTileValue.mockResolvedValue({ value: 3, formatted: '3', badge: null, stale: false })
		const wrapper = mount(LiveTileWidget, {
			props: { content: { label: 'Open', colour: 'hotpink' }, placement: { id: 1 } },
		})
		await flushPromises()
		expect(wrapper.find('.live-tile-widget__label').text()).toBe('Open')
		expect(wrapper.classes().some((c) => c.startsWith('live-tile-colour--'))).toBe(false)
	})
})
