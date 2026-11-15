/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `WidgetWrapper.vue` header and chrome decisions:
 * when the header shows (including the 0 / '0' showTitle round-trip),
 * which chrome variant and frame the card gets, the actions menu, the
 * acknowledgement gate, and the events its overlays emit.
 */

import { shallowMount } from '@vue/test-utils'
import { beforeEach, describe, expect, it } from 'vitest'
import WidgetWrapper from '../WidgetWrapper.vue'

beforeEach(() => {
	globalThis.t = (_app, key) => key
})

function mountWrapper(placement, props = {}) {
	return shallowMount(WidgetWrapper, {
		propsData: { placement, widget: { title: 'Widget' }, ...props },
		mocks: { t: (_app, key) => key },
	})
}

describe('WidgetWrapper — header visibility', () => {
	it('treats 0, "0", false and "" as hidden and an absent flag as shown', () => {
		const shown = (showTitle) => mountWrapper({ id: 1, widgetId: 'data', showTitle }).vm.showHeader
		expect(shown(undefined)).toBe(true)
		expect(shown(null)).toBe(true)
		expect(shown(true)).toBe(true)
		expect(shown(1)).toBe(true)
		expect(shown('1')).toBe(true)
		expect(shown(false)).toBe(false)
		expect(shown(0)).toBe(false)
		expect(shown('0')).toBe(false)
		expect(shown('')).toBe(false)
	})

	it('never shows a header on a tile or an NC widget, which paint their own', () => {
		expect(mountWrapper({ id: 1, widgetId: 'tile-42', showTitle: true }).vm.showHeader).toBe(false)
		expect(mountWrapper({ id: 1, widgetId: 'nc-widget', showTitle: true }).vm.showHeader).toBe(false)
	})

	it('shows a chromeless widget header only with a custom title', () => {
		expect(mountWrapper({ id: 1, widgetId: 'divider', showTitle: true }).vm.showHeader).toBe(false)
		expect(mountWrapper({ id: 1, widgetId: 'label', customTitle: 'Section', showTitle: true }).vm.showHeader).toBe(true)
		expect(mountWrapper({ id: 1, widgetId: 'label', customTitle: 'Section', showTitle: 0 }).vm.showHeader).toBe(false)
	})
})

describe('WidgetWrapper — chrome and frame', () => {
	it('gives cards the dashboard chrome and chromeless surfaces the default frame', () => {
		const card = mountWrapper({ id: 1, widgetId: 'data' }).vm
		expect([card.wrapperChrome, card.isChromelessFrame, card.isTileWidget]).toEqual(['nc-dashboard', false, false])

		for (const widgetId of ['tile-7', 'tile', 'header', 'label', 'divider']) {
			const vm = mountWrapper({ id: 1, widgetId }).vm
			expect([vm.wrapperChrome, vm.isChromelessFrame], widgetId).toEqual(['default', true])
		}
	})

	it('uses the widget icon and buttons when present', () => {
		const vm = mountWrapper({ id: 1, widgetId: 'data' }, { widget: { iconUrl: '/i.svg', buttons: [{ text: 'Open' }] } }).vm
		expect(vm.widgetIconUrl).toBe('/i.svg')
		expect(vm.widgetButtons).toEqual([{ text: 'Open' }])
		const bare = mountWrapper({ id: 1, widgetId: 'data' }, { widget: null }).vm
		expect(bare.widgetIconUrl).toBe(null)
		expect(bare.widgetButtons).toEqual([])
	})

	it('hides the actions menu only when the content opts out outside edit mode', () => {
		expect(mountWrapper({ id: 1, widgetId: 'data' }).vm.showActionsMenu).toBe(true)
		expect(mountWrapper({ id: 1, widgetId: 'data', content: { showActions: false } }).vm.showActionsMenu).toBe(false)
		expect(mountWrapper({ id: 1, widgetId: 'data', content: { showActions: false } }, { editMode: true }).vm.showActionsMenu).toBe(true)
	})

	it('lets only non-compulsory placements be removed', () => {
		expect(mountWrapper({ id: 1, widgetId: 'data' }).vm.canRemove).toBe(true)
		expect(mountWrapper({ id: 1, widgetId: 'data', isCompulsory: true }).vm.canRemove).toBe(false)
	})
})

describe('WidgetWrapper — acknowledgement gate', () => {
	it('gates only an owed, required acknowledgement outside edit mode', () => {
		const placement = { id: 3, widgetId: 'data', requiresAcknowledgement: '1' }
		expect(mountWrapper(placement, { outstandingAcknowledgement: true }).vm.showAcknowledgementGate).toBe(true)
		expect(mountWrapper(placement, { outstandingAcknowledgement: false }).vm.showAcknowledgementGate).toBe(false)
		expect(mountWrapper(placement, { outstandingAcknowledgement: true, editMode: true }).vm.showAcknowledgementGate).toBe(false)
		expect(mountWrapper({ ...placement, requiresAcknowledgement: 0 }, { outstandingAcknowledgement: true }).vm.showAcknowledgementGate).toBe(false)
	})

	it('re-emits the prompt\'s acknowledgement with the placement', async () => {
		const placement = { id: 3, widgetId: 'data', requiresAcknowledgement: 1 }
		const wrapper = mountWrapper(placement, { outstandingAcknowledgement: true })
		const prompt = wrapper.findComponent({ name: 'AcknowledgementPrompt' })
		expect(prompt.exists()).toBe(true)
		prompt.vm.$emit('acknowledged')
		expect(wrapper.emitted('acknowledged')).toEqual([[placement]])
	})

	it('forwards the edit cog\'s edit and remove with the placement and its id', () => {
		const placement = { id: 9, widgetId: 'data' }
		const wrapper = mountWrapper(placement, { editMode: true })
		const cog = wrapper.findComponent({ name: 'CnWidgetEditCog' })
		expect(cog.exists()).toBe(true)
		cog.vm.$emit('edit')
		cog.vm.$emit('remove')
		expect(wrapper.emitted('edit')).toEqual([[placement]])
		expect(wrapper.emitted('remove')).toEqual([[9]])
	})
})
