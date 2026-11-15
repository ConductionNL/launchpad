/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for the drag-and-drop and save paths of
 * `GroupPriorityOrder.vue` not covered by GroupPriorityOrder.spec.js:
 * reordering within the active column, dropping between columns at an
 * index or at the end, the debounced save, and load / save failures.
 */

import { showError, showSuccess } from '@nextcloud/dialogs'
import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import GroupPriorityOrder from '../GroupPriorityOrder.vue'
import { api } from '../../../services/api.js'
import { logger } from '../../../utils/logger.js'

vi.mock('../../../services/api.js', () => ({
	api: { getAdminGroups: vi.fn(), updateAdminGroupOrder: vi.fn() },
}))
vi.mock('../../../utils/logger.js', () => ({ logger: { error: vi.fn() } }))
vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn(), showSuccess: vi.fn() }))

const GROUPS = {
	active: ['a', 'b', 'c'],
	inactive: ['x', 'z'],
	allKnown: [
		{ id: 'a', displayName: 'Alpha' },
		{ id: 'b', displayName: 'Beta' },
		{ id: 'c', displayName: 'Gamma' },
		{ id: 'x', displayName: 'Xray' },
		{ id: 'z', displayName: 'Zulu' },
		{ id: 'm', displayName: 'Mike' },
	],
}

async function mountOrder(payload = GROUPS) {
	api.getAdminGroups.mockResolvedValue({ data: payload })
	const wrapper = mount(GroupPriorityOrder, {
		global: { stubs: { NcButton: true, NcTextField: true } },
	})
	await flushPromises()
	return wrapper
}

/**
 * Drag one id from a column position.
 *
 * @param {object} vm the component.
 * @param {string} id the dragged id.
 * @param {string} column 'active' or 'inactive'.
 * @param {number} index its position.
 * @return {object} the fake drag event.
 */
function drag(vm, id, column, index) {
	const event = { dataTransfer: { setData: vi.fn(), effectAllowed: '', dropEffect: '' } }
	vm.onDragStart(event, id, column, index)
	return event
}

beforeEach(() => {
	vi.clearAllMocks()
	vi.useFakeTimers()
	globalThis.t = (_app, key) => key
	api.updateAdminGroupOrder.mockResolvedValue({})
})

afterEach(() => {
	vi.useRealTimers()
})

describe('GroupPriorityOrder drag and drop', () => {
	it('marks the drag as a move carrying the id', async () => {
		const wrapper = await mountOrder()
		const event = drag(wrapper.vm, 'a', 'active', 0)

		expect(event.dataTransfer.effectAllowed).toBe('move')
		expect(event.dataTransfer.setData).toHaveBeenCalledWith('text/plain', 'a')
		expect(wrapper.vm.dragState).toEqual({ id: 'a', fromColumn: 'active', fromIndex: 0 })

		const over = { dataTransfer: {} }
		wrapper.vm.onDragOver(over, 'active')
		expect(over.dataTransfer.dropEffect).toBe('move')
		const itemOver = { dataTransfer: {} }
		wrapper.vm.onItemDragOver(itemOver, 1, 'active')
		expect(itemOver.dataTransfer.dropEffect).toBe('move')
		wrapper.vm.onDragOver({}, 'active')
		wrapper.vm.onItemDragOver({}, 0, 'active')
		wrapper.vm.onDragStart({}, 'b', 'active', 1)
		expect(wrapper.vm.dragState.id).toBe('b')
	})

	it.each([
		[0, 2, ['b', 'a', 'c']],
		[0, 3, ['b', 'c', 'a']],
		[2, 0, ['c', 'a', 'b']],
	])('moves active index %s onto item %s', async (from, to, order) => {
		const wrapper = await mountOrder()
		drag(wrapper.vm, GROUPS.active[from], 'active', from)

		wrapper.vm.onItemDrop({}, to, 'active')

		expect(wrapper.vm.active).toEqual(order)
		expect(wrapper.vm.dragState).toBeNull()
	})

	it('ignores a drop onto itself or without a drag', async () => {
		const wrapper = await mountOrder()
		wrapper.vm.onItemDrop({}, 1, 'active')
		wrapper.vm.onDrop({}, 'active')

		drag(wrapper.vm, 'b', 'active', 1)
		wrapper.vm.onItemDrop({}, 1, 'active')

		expect(wrapper.vm.active).toEqual(['a', 'b', 'c'])
		vi.runAllTimers()
		expect(api.updateAdminGroupOrder).not.toHaveBeenCalled()
	})

	it('inserts an inactive group at the dropped position', async () => {
		const wrapper = await mountOrder()
		drag(wrapper.vm, 'z', 'inactive', 1)

		wrapper.vm.onItemDrop({}, 1, 'active')

		expect(wrapper.vm.active).toEqual(['a', 'z', 'b', 'c'])
		expect(wrapper.vm.inactive).toEqual(['x'])
	})

	it.each([[-1], [3], [null]])('appends at the end for drop index %s', async (index) => {
		const wrapper = await mountOrder()
		wrapper.vm.moveBetweenColumns('x', 'inactive', 'active', index)
		expect(wrapper.vm.active).toEqual(['a', 'b', 'c', 'x'])
	})

	it('drops on the inactive column sorted by display name', async () => {
		const wrapper = await mountOrder({ ...GROUPS, inactive: ['x', 'z', 'q'] })
		drag(wrapper.vm, 'b', 'active', 1)
		wrapper.vm.onDrop({}, 'inactive')

		expect(wrapper.vm.active).toEqual(['a', 'c'])
		expect(wrapper.vm.inactive).toEqual(['b', 'q', 'x', 'z'])
	})

	it('drops on the active column at the end, and ignores reordering inside inactive', async () => {
		const wrapper = await mountOrder()
		drag(wrapper.vm, 'x', 'inactive', 0)
		wrapper.vm.onDrop({}, 'active')
		expect(wrapper.vm.active).toEqual(['a', 'b', 'c', 'x'])

		drag(wrapper.vm, 'z', 'inactive', 0)
		wrapper.vm.onDrop({}, 'inactive')
		expect(wrapper.vm.inactive).toEqual(['z'])

		wrapper.vm.moveBetweenColumns('a', 'active', 'active', 0)
		expect(wrapper.vm.active).toEqual(['a', 'b', 'c', 'x'])
	})
})

describe('GroupPriorityOrder rendered columns', () => {
	const ButtonStub = { emits: ['click'], template: '<button class="move-stub" @click="$emit(\'click\')"><slot /></button>' }
	const FieldStub = {
		props: ['modelValue'],
		emits: ['update:modelValue'],
		template: '<input class="filter-stub" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
	}

	async function mountRendered() {
		api.getAdminGroups.mockResolvedValue({ data: GROUPS })
		const wrapper = mount(GroupPriorityOrder, { global: { stubs: { NcButton: ButtonStub, NcTextField: FieldStub } } })
		await flushPromises()
		return wrapper
	}

	const items = (wrapper, column) => wrapper.findAll(`.group-priority__list--${column} .group-priority__item`)
	const labels = (wrapper, column) => items(wrapper, column).map((li) => li.find('.group-priority__label').text())

	it('reorders the active column by dragging one item onto another', async () => {
		const wrapper = await mountRendered()
		const dataTransfer = { setData: vi.fn(), effectAllowed: '', dropEffect: '' }

		await items(wrapper, 'active')[2].trigger('dragstart', { dataTransfer })
		await items(wrapper, 'active')[0].trigger('dragover', { dataTransfer })
		await items(wrapper, 'active')[0].trigger('drop', { dataTransfer })

		expect(labels(wrapper, 'active')).toEqual(['Gamma', 'Alpha', 'Beta'])
	})

	it('moves groups between columns by drag onto a column or with the buttons', async () => {
		const wrapper = await mountRendered()
		const dataTransfer = { setData: vi.fn() }
		const list = (column) => wrapper.find(`.group-priority__list--${column}`)

		await items(wrapper, 'inactive')[0].trigger('dragstart', { dataTransfer })
		await list('active').trigger('dragover', { dataTransfer })
		await list('active').trigger('drop', { dataTransfer })
		expect(labels(wrapper, 'active')).toEqual(['Alpha', 'Beta', 'Gamma', 'Xray'])

		await items(wrapper, 'active')[0].trigger('dragstart', { dataTransfer })
		await list('inactive').trigger('dragover', { dataTransfer })
		await list('inactive').trigger('drop', { dataTransfer })
		expect(wrapper.vm.inactive).toEqual(['a', 'z'])

		await items(wrapper, 'inactive')[1].find('.move-stub').trigger('click')
		await items(wrapper, 'active')[0].find('.move-stub').trigger('click')
		expect(wrapper.vm.active).toEqual(['c', 'x', 'z'])
	})

	it('filters each column from its search field', async () => {
		const wrapper = await mountRendered()
		const [activeFilter, inactiveFilter] = wrapper.findAll('.filter-stub')

		await activeFilter.setValue('GAM')
		await inactiveFilter.setValue('zu')

		expect(labels(wrapper, 'active')).toEqual(['Gamma'])
		expect(items(wrapper, 'inactive').map((li) => li.text())).toEqual([expect.stringContaining('Zulu')])
	})
})

describe('GroupPriorityOrder saving', () => {
	it('saves once, 300 ms after the last change', async () => {
		const wrapper = await mountOrder()
		wrapper.vm.moveToActive('x')
		vi.advanceTimersByTime(200)
		wrapper.vm.moveToInactive('a')
		vi.advanceTimersByTime(299)
		expect(api.updateAdminGroupOrder).not.toHaveBeenCalled()

		vi.advanceTimersByTime(1)
		await flushPromises()

		expect(api.updateAdminGroupOrder).toHaveBeenCalledTimes(1)
		expect(api.updateAdminGroupOrder).toHaveBeenCalledWith(['b', 'c', 'x'])
		expect(showSuccess).toHaveBeenCalledWith('Group order saved.')
		expect(wrapper.vm.saving).toBe(false)
	})

	it('reports a failed save', async () => {
		api.updateAdminGroupOrder.mockRejectedValue(new Error('x'))
		const wrapper = await mountOrder()

		await wrapper.vm.persist()

		expect(showError).toHaveBeenCalledWith('Failed to save group order.')
		expect(logger.error).toHaveBeenCalledWith('Failed to save group order:', expect.any(Error))
		expect(wrapper.vm.saving).toBe(false)
	})

	it('drops a pending save on unmount', async () => {
		const wrapper = await mountOrder()
		wrapper.vm.moveToActive('x')
		wrapper.unmount()
		vi.runAllTimers()
		expect(api.updateAdminGroupOrder).not.toHaveBeenCalled()
	})

	it('reports a failed load and treats missing lists as empty', async () => {
		api.getAdminGroups.mockRejectedValueOnce(new Error('x'))
		const failed = mount(GroupPriorityOrder, { props: { initialActive: ['seed'] }, global: { stubs: { NcButton: true, NcTextField: true } } })
		await flushPromises()
		expect(showError).toHaveBeenCalledWith('Failed to load group list.')
		expect(failed.vm.active).toEqual(['seed'])
		expect(failed.vm.loading).toBe(false)

		const empty = await mountOrder({ active: 'a' })
		expect([empty.vm.active, empty.vm.inactive, empty.vm.allKnown]).toEqual([[], [], []])
		expect(empty.vm.displayName('ghost')).toBe('ghost')
		expect(empty.vm.isStale('ghost')).toBe(true)
	})
})
