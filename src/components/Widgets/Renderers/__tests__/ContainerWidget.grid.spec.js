/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for the inner GridStack lifecycle of `ContainerWidget.vue`
 * (REQ-CONT-002 / REQ-CONT-005): the nested grid is initialised with the
 * nested-grid options on the inner host element, its `change` event is wired
 * to the persistence bridge, it is torn down on unmount (tolerating a throwing
 * destroy), and child keys prefer id, then uuid, then the loop index.
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { toRaw } from 'vue'
import { getNestedGridOptions } from '../../../../composables/useNestedGridManager.js'

const gridstack = vi.hoisted(() => ({ GridStack: { init: vi.fn() } }))
vi.mock('gridstack', () => gridstack)

// Imported after the mock so the component's dynamic import sees it.
const { default: ContainerWidget } = await import('../ContainerWidget.vue')

function fakeGrid(overrides = {}) {
	return { on: vi.fn(), destroy: vi.fn(), ...overrides }
}

function mountContainer(content = { placements: [] }) {
	return mount(ContainerWidget, {
		props: { content },
		global: { stubs: { ContainerChild: true } },
	})
}

beforeEach(() => {
	globalThis.t = (_app, key) => key
	gridstack.GridStack.init.mockReset()
})

describe('ContainerWidget inner grid', () => {
	it('initialises GridStack with the nested options on the inner host and listens for change', async () => {
		const grid = fakeGrid()
		gridstack.GridStack.init.mockReturnValue(grid)
		const wrapper = mountContainer()
		await flushPromises()

		expect(gridstack.GridStack.init).toHaveBeenCalledTimes(1)
		const [opts, el] = gridstack.GridStack.init.mock.calls[0]
		expect(opts).toEqual(getNestedGridOptions())
		expect(el).toBe(wrapper.find('.launchpad-container-grid').element)
		expect(toRaw(wrapper.vm.gridInstance)).toBe(grid)
		expect(grid.on).toHaveBeenCalledWith('change', wrapper.vm.onGridChange)
	})

	it('persists through the change handler it registered', async () => {
		const grid = fakeGrid()
		gridstack.GridStack.init.mockReturnValue(grid)
		const placements = [{ id: 7, type: 'label', gridX: 0, gridY: 0, gridWidth: 2, gridHeight: 2 }]
		const wrapper = mountContainer({ title: 'Box', placements })
		await flushPromises()

		const handler = grid.on.mock.calls[0][1]
		handler({}, [{ el: { dataset: { launchpadIndex: '0' } }, x: 1, y: 2, w: 3, h: 4 }])

		expect(wrapper.emitted('update:content')).toEqual([[{
			title: 'Box',
			placements: [{ ...placements[0], gridX: 1, gridY: 2, gridWidth: 3, gridHeight: 4 }],
		}]])
	})

	it('skips the change wiring when the grid instance has no on()', async () => {
		gridstack.GridStack.init.mockReturnValue({})
		const wrapper = mountContainer()
		await flushPromises()
		expect(wrapper.vm.gridInstance).toEqual({})
		wrapper.unmount()
		expect(wrapper.vm.gridInstance).toBeNull()
	})

	it('destroys the grid without removing DOM on unmount', async () => {
		const grid = fakeGrid()
		gridstack.GridStack.init.mockReturnValue(grid)
		const wrapper = mountContainer()
		await flushPromises()

		const vm = wrapper.vm
		wrapper.unmount()
		expect(grid.destroy).toHaveBeenCalledWith(false)
		expect(vm.gridInstance).toBeNull()
		expect(vm._nestedManager).toBeNull()
	})

	it('swallows a throwing destroy and still clears the instance', async () => {
		const grid = fakeGrid({ destroy: vi.fn(() => { throw new Error('gone') }) })
		gridstack.GridStack.init.mockReturnValue(grid)
		const wrapper = mountContainer()
		await flushPromises()

		const vm = wrapper.vm
		expect(() => wrapper.unmount()).not.toThrow()
		expect(grid.destroy).toHaveBeenCalledTimes(1)
		expect(vm.gridInstance).toBeNull()
	})
})

describe('ContainerWidget child keys', () => {
	it('prefers id, then a non-empty uuid, then the index', () => {
		const wrapper = mountContainer()
		const key = wrapper.vm.childKey
		expect(key({ id: 0, uuid: 'u' }, 3)).toBe('id-0')
		expect(key({ id: null, uuid: 'abc' }, 3)).toBe('uuid-abc')
		expect(key({ uuid: '' }, 3)).toBe('idx-3')
		expect(key(null, 5)).toBe('idx-5')
	})
})
