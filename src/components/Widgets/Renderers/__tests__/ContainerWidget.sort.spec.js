/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for the sorted view of `ContainerWidget.vue`
 * (launcher-tile-sorting REQ-TSO-001..003): view mode reflows the tiles in
 * the chosen order without writing it back, edit mode shows the stored
 * layout, clicks are counted in this browser only, "Forget my usage"
 * clears them, and the order is announced.
 */

import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import ContainerWidget from '../ContainerWidget.vue'
import { readTileUse, TILE_USE_KEY } from '../../../../utils/tileSort.js'

const axiosPost = vi.fn(() => Promise.resolve({}))
vi.mock('@nextcloud/axios', () => ({
	default: { post: (...args) => axiosPost(...args), get: () => Promise.resolve({ data: { enabled: true } }) },
}))

const tile = (id, title, x) => ({ id, type: 'label', content: { title, text: title }, gridX: x, gridY: 0, gridWidth: 1, gridHeight: 1 })

// "Zaaksysteem", "Agenda" and "Mail", placed in that order.
const applicaties = () => [tile(1, 'Zaaksysteem', 0), tile(2, 'Agenda', 1), tile(3, 'Mail', 2)]

/**
 * Mount a container.
 *
 * @param {object} content The container content.
 * @param {boolean} editMode Edit mode.
 * @return {object} The wrapper.
 */
function mountContainer(content, editMode = false) {
	return mount(ContainerWidget, { props: { content, editMode } })
}

/**
 * The shown children as [id, gridX].
 *
 * @param {object} wrapper The wrapper.
 * @return {Array<Array<number>>}
 */
function shown(wrapper) {
	return wrapper.vm.displayedChildren.map((c) => [c.id, c.gridX])
}

beforeEach(() => {
	localStorage.clear()
	axiosPost.mockClear()
	globalThis.t = (_app, key) => key
})

describe('ContainerWidget sorting — REQ-TSO-001', () => {
	it('shows Agenda, Mail, Zaaksysteem left to right when sorted alphabetically', () => {
		const wrapper = mountContainer({ placements: applicaties(), sortBy: 'alphabetical' })
		expect(shown(wrapper)).toEqual([[2, 0], [3, 1], [1, 2]])
		const items = wrapper.findAll('.container-widget__child')
		expect(items.map((item) => item.attributes('gs-x'))).toEqual(['0', '1', '2'])
	})

	it('shows the stored order by hand, and in edit mode whatever the sort', () => {
		expect(shown(mountContainer({ placements: applicaties(), sortBy: 'manual' }))).toEqual([[1, 0], [2, 1], [3, 2]])
		expect(shown(mountContainer({ placements: applicaties() }))).toEqual([[1, 0], [2, 1], [3, 2]])
		expect(shown(mountContainer({ placements: applicaties(), sortBy: 'alphabetical' }, true))).toEqual([[1, 0], [2, 1], [3, 2]])
	})

	it('never writes the sorted order back', () => {
		const wrapper = mountContainer({ placements: applicaties(), sortBy: 'alphabetical' })
		const el = { dataset: { launchpadIndex: '0' } }
		wrapper.vm.onGridChange(null, [{ el, x: 3, y: 1, w: 1, h: 1 }])
		expect(wrapper.emitted('update:content')).toBeUndefined()
	})
})

describe('ContainerWidget most used — REQ-TSO-002', () => {
	it('moves a tile opened three times to the front on the next load, and sends no counts', async () => {
		const first = mountContainer({ placements: applicaties(), sortBy: 'most-used' })
		expect(shown(first)).toEqual([[2, 0], [3, 1], [1, 2]])

		const zaaksysteem = first.findAll('.container-widget__child')[2]
		await zaaksysteem.trigger('click')
		await zaaksysteem.trigger('click')
		await zaaksysteem.trigger('click')
		expect(readTileUse()['1'].count).toBe(3)
		// No jumping while clicking: the order changes on the next load.
		expect(shown(first)).toEqual([[2, 0], [3, 1], [1, 2]])
		expect(axiosPost).not.toHaveBeenCalled()

		const reloaded = mountContainer({ placements: applicaties(), sortBy: 'most-used' })
		expect(shown(reloaded)[0]).toEqual([1, 0])
	})

	it('counts nothing in edit mode', async () => {
		const wrapper = mountContainer({ placements: applicaties(), sortBy: 'most-used' }, true)
		await wrapper.findAll('.container-widget__child')[0].trigger('click')
		expect(localStorage.getItem(TILE_USE_KEY)).toBeNull()
	})

	it('shows the stored order without an error when storage is blocked', () => {
		const spy = vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
			throw new Error('SecurityError')
		})
		expect(shown(mountContainer({ placements: applicaties(), sortBy: 'most-used' }))).toEqual([[2, 0], [3, 1], [1, 2]])
		spy.mockRestore()
	})
})

describe('ContainerWidget forget my usage — REQ-TSO-003', () => {
	it('offers it for most and last used only, and clears this container\'s counts', async () => {
		localStorage.setItem(TILE_USE_KEY, JSON.stringify({ 1: { count: 5, lastUsedAt: 9 }, 99: { count: 1, lastUsedAt: 1 } }))
		const wrapper = mountContainer({ placements: applicaties(), sortBy: 'most-used' })
		expect(shown(wrapper)[0]).toEqual([1, 0])

		await wrapper.find('[data-testid="container-forget-usage"]').trigger('click')

		expect(readTileUse()).toEqual({ 99: { count: 1, lastUsedAt: 1 } })
		expect(shown(wrapper)).toEqual([[2, 0], [3, 1], [1, 2]])

		expect(mountContainer({ placements: applicaties(), sortBy: 'alphabetical' }).find('[data-testid="container-forget-usage"]').exists()).toBe(false)
		expect(mountContainer({ placements: applicaties(), sortBy: 'last-used' }, true).find('[data-testid="container-forget-usage"]').exists()).toBe(false)
	})
})

describe('ContainerWidget accessible order', () => {
	it('describes the grid with the sort, and says nothing by hand', () => {
		const wrapper = mountContainer({ placements: applicaties(), sortBy: 'most-used' })
		const grid = wrapper.find('.launchpad-container-grid')
		const description = wrapper.find(`#${grid.attributes('aria-describedby')}`)
		expect(description.text()).toBe('Sorted by most used')

		const manual = mountContainer({ placements: applicaties() })
		expect(manual.find('.launchpad-container-grid').attributes('aria-describedby')).toBeUndefined()
	})
})
