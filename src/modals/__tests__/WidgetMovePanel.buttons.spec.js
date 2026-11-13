/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `WidgetMovePanel.vue` driven by its buttons rather
 * than the keyboard: each position and size button applies its action, the
 * Cancel/Confirm buttons and the modal close emit what the parent acts on,
 * and reopening or swapping the placement resets the pending rectangle.
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it } from 'vitest'
import WidgetMovePanel from '../WidgetMovePanel.vue'

const stubs = {
	NcModal: {
		name: 'NcModal',
		emits: ['close'],
		template: '<div class="nc-modal-stub"><slot /></div>',
	},
	NcButton: {
		emits: ['click'],
		template: '<button @click="$emit(\'click\')"><slot /></button>',
	},
}

beforeEach(() => {
	globalThis.t = (_app, key, params) => {
		if (!params) {
			return key
		}
		return key.replace(/\{(\w+)\}/g, (_m, name) => String(params[name]))
	}
})

const PLACEMENT = { id: 'p1', gridX: 3, gridY: 2, gridWidth: 4, gridHeight: 4 }

function mountPanel(props = {}) {
	return mount(WidgetMovePanel, {
		propsData: { open: true, placement: PLACEMENT, allPlacements: [], gridColumns: 12, ...props },
		stubs,
	})
}

function rect(wrapper) {
	const { gridX, gridY, gridWidth, gridHeight } = wrapper.vm.working
	return { gridX, gridY, gridWidth, gridHeight }
}

describe('WidgetMovePanel buttons', () => {
	it.each([
		['move-up', { gridX: 3, gridY: 1, gridWidth: 4, gridHeight: 4 }],
		['move-down', { gridX: 3, gridY: 3, gridWidth: 4, gridHeight: 4 }],
		['move-left', { gridX: 2, gridY: 2, gridWidth: 4, gridHeight: 4 }],
		['move-right', { gridX: 4, gridY: 2, gridWidth: 4, gridHeight: 4 }],
		['grow-width', { gridX: 3, gridY: 2, gridWidth: 5, gridHeight: 4 }],
		['shrink-width', { gridX: 3, gridY: 2, gridWidth: 3, gridHeight: 4 }],
		['grow-height', { gridX: 3, gridY: 2, gridWidth: 4, gridHeight: 5 }],
		['shrink-height', { gridX: 3, gridY: 2, gridWidth: 4, gridHeight: 3 }],
	])('%s applies its action to the pending rectangle', async (action, expected) => {
		const wrapper = mountPanel()

		await wrapper.find(`[data-test="${action}"]`).trigger('click')

		expect(rect(wrapper)).toEqual(expected)
		expect(wrapper.find('[data-test="widget-move-readout"]').text()).toBe(
			`Column ${expected.gridX + 1}, row ${expected.gridY + 1}, ${expected.gridWidth} wide by ${expected.gridHeight} tall`,
		)
	})

	it('Confirm emits save with the pending rectangle, then close', async () => {
		const wrapper = mountPanel()
		await wrapper.find('[data-test="move-right"]').trigger('click')

		await wrapper.find('[data-test="move-save"]').trigger('click')

		expect(wrapper.emitted('save')[0][0]).toMatchObject({ gridX: 4, gridY: 2, gridWidth: 4, gridHeight: 4 })
		expect(wrapper.emitted('close')).toHaveLength(1)
	})

	it('Cancel and the modal close emit close without save', async () => {
		const wrapper = mountPanel()

		await wrapper.find('[data-test="move-cancel"]').trigger('click')
		wrapper.findComponent({ name: 'NcModal' }).vm.$emit('close')

		expect(wrapper.emitted('close')).toHaveLength(2)
		expect(wrapper.emitted('save')).toBeUndefined()
	})

	it('a key without an action does nothing and is not swallowed', async () => {
		const wrapper = mountPanel()
		const event = new KeyboardEvent('keydown', { key: 'a', cancelable: true })

		wrapper.find('[data-test="widget-move-panel"]').element.dispatchEvent(event)

		expect(event.defaultPrevented).toBe(false)
		expect(rect(wrapper)).toEqual({ gridX: 3, gridY: 2, gridWidth: 4, gridHeight: 4 })
	})
})

describe('WidgetMovePanel reset', () => {
	it('renders nothing while closed and resets the pending rectangle when reopened', async () => {
		const wrapper = mountPanel({ open: false })
		expect(wrapper.find('[data-test="widget-move-panel"]').exists()).toBe(false)

		await wrapper.setProps({ open: true })
		await wrapper.find('[data-test="move-down"]').trigger('click')
		await wrapper.setProps({ open: false })
		await wrapper.setProps({ open: true })
		await flushPromises()

		expect(rect(wrapper)).toEqual({ gridX: 3, gridY: 2, gridWidth: 4, gridHeight: 4 })
	})

	it('takes the new placement when it changes, defaulting missing geometry', async () => {
		const wrapper = mountPanel()

		await wrapper.setProps({ placement: { id: 'p2', gridX: 1, gridY: 'x' } })

		expect(rect(wrapper)).toEqual({ gridX: 1, gridY: 0, gridWidth: 2, gridHeight: 2 })
	})
})
