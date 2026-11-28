/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `ContainerWidget.vue` (REQ-CONT-002 / REQ-CONT-005)
 * when the GridStack runtime is unusable: a module that fails to load, an
 * export without `init()`, or an init that arrives after the inner host has
 * been torn down all leave the container rendering its children without an
 * inner grid and without emitting anything.
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const gridstack = vi.hoisted(() => ({ mode: 'ok', init: null }))
vi.mock('gridstack', () => ({
	get GridStack() {
		if (gridstack.mode === 'throw') {
			throw new Error('gridstack failed to load')
		}
		if (gridstack.mode === 'no-init') {
			return {}
		}
		return { init: gridstack.init }
	},
	default: undefined,
}))

// Imported after the mock so the component's dynamic import sees it.
const { default: ContainerWidget } = await import('../ContainerWidget.vue')

const placements = [
	{ id: 1, type: 'label', gridX: 0, gridY: 0, gridWidth: 2, gridHeight: 2 },
	{ uuid: 'u-2', type: 'label', gridX: 2, gridY: 0, gridWidth: 2, gridHeight: 2 },
]

function mountContainer() {
	return mount(ContainerWidget, {
		props: { content: { placements } },
		global: { stubs: { ContainerChild: true } },
	})
}

beforeEach(() => {
	globalThis.t = (_app, key) => key
	gridstack.mode = 'ok'
	gridstack.init = vi.fn(() => ({ on: vi.fn(), destroy: vi.fn() }))
})

describe('ContainerWidget without a usable GridStack', () => {
	it('still renders every child when the GridStack module fails to load', async () => {
		gridstack.mode = 'throw'
		const wrapper = mountContainer()
		await flushPromises()
		expect(wrapper.findAll('.container-widget__child')).toHaveLength(2)
		expect(wrapper.vm.gridInstance).toBeNull()
		expect(gridstack.init).not.toHaveBeenCalled()
		expect(wrapper.emitted('update:content')).toBeUndefined()
	})

	it('skips grid initialisation when the export has no init()', async () => {
		gridstack.mode = 'no-init'
		const wrapper = mountContainer()
		await flushPromises()
		expect(wrapper.findAll('.container-widget__child')).toHaveLength(2)
		expect(wrapper.vm.gridInstance).toBeNull()
		expect(gridstack.init).not.toHaveBeenCalled()
		// A repeated initialisation resolves quietly instead of rejecting.
		await expect(wrapper.vm.initInnerGrid()).resolves.toBeUndefined()
		expect(wrapper.vm.gridInstance).toBeNull()
	})

	it('does not initialise a grid once the inner host has been unmounted', async () => {
		const wrapper = mountContainer()
		await flushPromises()
		expect(gridstack.init).toHaveBeenCalledTimes(1)
		const vm = wrapper.vm
		wrapper.unmount()
		await vm.initInnerGrid()
		expect(gridstack.init).toHaveBeenCalledTimes(1)
		expect(vm.gridInstance).toBeNull()
	})
})
