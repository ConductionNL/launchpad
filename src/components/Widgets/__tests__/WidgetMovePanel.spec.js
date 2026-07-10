/**
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Vitest unit tests for `WidgetMovePanel.vue` (grid-layout keyboard
 * repositioning). Covers:
 *  - arrow keys nudge the pending position and update the live readout
 *  - Shift+Arrow resizes width/height (respecting the MIN_CELLS floor)
 *  - Enter emits `save` with the expected rect (then `close`)
 *  - Escape emits `close` with NO `save`
 *  - no pointer/mouse events are required to drive any of this
 */

import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import WidgetMovePanel from '../WidgetMovePanel.vue'

const stubs = {
	NcModal: { template: '<div class="nc-modal-stub"><slot /></div>' },
	NcButton: { template: '<button @click="$emit(\'click\')"><slot /></button>' },
}

beforeEach(() => {
	globalThis.t = (_app, key, params) => {
		if (!params) {
			return key
		}
		return key.replace(/\{(\w+)\}/g, (_m, name) => String(params[name]))
	}
})

/**
 * Mount helper with a default placement at column 3, row 2, size 4x4.
 *
 * @param {object} props prop overrides
 * @return {import('@vue/test-utils').Wrapper}
 */
function mountPanel(props = {}) {
	return mount(WidgetMovePanel, {
		propsData: {
			open: true,
			placement: { id: 'p1', gridX: 3, gridY: 2, gridWidth: 4, gridHeight: 4 },
			allPlacements: [],
			gridColumns: 12,
			...props,
		},
		stubs,
	})
}

describe('WidgetMovePanel', () => {
	it('ArrowRight nudges the pending column by one', async () => {
		const wrapper = mountPanel()
		await wrapper.find('[data-test="widget-move-panel"]').trigger('keydown', { key: 'ArrowRight' })
		expect(wrapper.vm.working.gridX).toBe(4)
		expect(wrapper.vm.working.gridY).toBe(2)
	})

	it('ArrowUp/ArrowLeft nudge and clamp at the grid origin', async () => {
		const wrapper = mountPanel({ placement: { id: 'p1', gridX: 0, gridY: 0, gridWidth: 4, gridHeight: 4 } })
		const el = wrapper.find('[data-test="widget-move-panel"]')
		await el.trigger('keydown', { key: 'ArrowUp' })
		await el.trigger('keydown', { key: 'ArrowLeft' })
		expect(wrapper.vm.working.gridX).toBe(0)
		expect(wrapper.vm.working.gridY).toBe(0)
	})

	it('Shift+ArrowRight grows width; Shift+ArrowLeft shrinks it (floor at MIN_CELLS)', async () => {
		const wrapper = mountPanel({ placement: { id: 'p1', gridX: 0, gridY: 0, gridWidth: 2, gridHeight: 2 } })
		const el = wrapper.find('[data-test="widget-move-panel"]')
		await el.trigger('keydown', { key: 'ArrowRight', shiftKey: true })
		expect(wrapper.vm.working.gridWidth).toBe(3)
		// Shrink twice — must not drop below the min of 2.
		await el.trigger('keydown', { key: 'ArrowLeft', shiftKey: true })
		await el.trigger('keydown', { key: 'ArrowLeft', shiftKey: true })
		expect(wrapper.vm.working.gridWidth).toBe(2)
	})

	it('the live readout reflects the pending position/size', async () => {
		const wrapper = mountPanel()
		await wrapper.find('[data-test="widget-move-panel"]').trigger('keydown', { key: 'ArrowDown' })
		const readout = wrapper.find('[data-test="widget-move-readout"]')
		// gridX 3 → column 4, gridY 3 → row 4, 4 wide by 4 tall.
		expect(readout.text()).toContain('4')
		expect(readout.attributes('aria-live')).toBe('polite')
	})

	it('Enter emits save with the pending rect, then close', async () => {
		const wrapper = mountPanel()
		const el = wrapper.find('[data-test="widget-move-panel"]')
		await el.trigger('keydown', { key: 'ArrowRight' })
		await el.trigger('keydown', { key: 'ArrowDown' })
		await el.trigger('keydown', { key: 'Enter' })

		const saved = wrapper.emitted('save')
		expect(saved).toHaveLength(1)
		expect(saved[0][0]).toMatchObject({ gridX: 4, gridY: 3, gridWidth: 4, gridHeight: 4 })
		expect(wrapper.emitted('close')).toHaveLength(1)
	})

	it('Escape emits close with NO save', async () => {
		const wrapper = mountPanel()
		const el = wrapper.find('[data-test="widget-move-panel"]')
		await el.trigger('keydown', { key: 'ArrowRight' })
		await el.trigger('keydown', { key: 'Escape' })

		expect(wrapper.emitted('close')).toHaveLength(1)
		expect(wrapper.emitted('save')).toBeFalsy()
	})

	it('the panel exposes an accessible group role and label for the move surface', () => {
		const wrapper = mountPanel()
		const panel = wrapper.find('[data-test="widget-move-panel"]')
		expect(panel.attributes('role')).toBe('group')
		expect(panel.attributes('aria-label')).toBeTruthy()
		expect(panel.attributes('tabindex')).toBe('0')
	})
})
