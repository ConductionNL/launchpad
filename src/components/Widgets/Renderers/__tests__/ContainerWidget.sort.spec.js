/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for tile sorting in `ContainerWidget.vue`
 * (launcher-tile-sorting, REQ-TSO-001..004): the view-time order per mode,
 * the reflow that keeps sizes and never writes back, most and last used read
 * from the viewer's browser only, "Forget my usage", and the once-per-load
 * shuffle.
 */

import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import ContainerWidget from '../ContainerWidget.vue'
import {
	__resetTileUseForTest,
	recordLocalTileUse,
} from '../../../../composables/useTileClickTracking.js'

vi.mock('@nextcloud/axios', () => ({
	default: {
		get: vi.fn(() => Promise.resolve({ data: {} })),
		post: vi.fn(() => Promise.resolve({})),
	},
}))

function tile(id, title, x) {
	return {
		id,
		type: 'tile',
		content: { title },
		gridX: x,
		gridY: 0,
		gridWidth: 1,
		gridHeight: 1,
	}
}

// Stored in this order: Zaaksysteem, Agenda, Mail.
const PLACEMENTS = [
	tile(1, 'Zaaksysteem', 0),
	tile(2, 'Agenda', 1),
	tile(3, 'Mail', 2),
]

function mountSorted(sortBy, editMode = false) {
	return mount(ContainerWidget, {
		props: {
			content: { title: 'Applicaties', sortBy, placements: PLACEMENTS },
			editMode,
		},
		global: {
			stubs: {
				ContainerChild: {
					props: ['placement'],
					template:
						'<span class="child">{{ placement.content.title }}</span>',
				},
			},
		},
	})
}

function order(wrapper) {
	return wrapper
		.findAll('.container-widget__child')
		.map((item) => ({
			title: item.text(),
			x: Number(item.attributes('gs-x')),
			y: Number(item.attributes('gs-y')),
		}))
		.sort((a, b) => a.y - b.y || a.x - b.x)
		.map((item) => item.title)
}

describe('ContainerWidget tile sorting', () => {
	beforeEach(() => {
		globalThis.t = (_app, key) => key
		localStorage.clear()
		__resetTileUseForTest()
	})

	afterEach(() => {
		vi.restoreAllMocks()
	})

	it('REQ-TSO-001: alphabetical shows Agenda, Mail, Zaaksysteem from left to right', () => {
		expect(order(mountSorted('alphabetical'))).toEqual([
			'Agenda',
			'Mail',
			'Zaaksysteem',
		])
	})

	it('REQ-TSO-001: by hand keeps the stored order', () => {
		expect(order(mountSorted('manual'))).toEqual([
			'Zaaksysteem',
			'Agenda',
			'Mail',
		])
	})

	it('REQ-TSO-001: edit mode shows the stored positions and nothing is written back', () => {
		const wrapper = mountSorted('alphabetical', true)
		expect(order(wrapper)).toEqual(['Zaaksysteem', 'Agenda', 'Mail'])
		expect(mountSorted('alphabetical').emitted('update:content')).toBeUndefined()
	})

	it('REQ-TSO-001: the reflow keeps each tile size and wraps at four columns', () => {
		const wide = [
			{ ...tile(1, 'B', 0), gridWidth: 3 },
			{ ...tile(2, 'A', 3), gridWidth: 2, gridHeight: 2 },
		]
		const wrapper = mount(ContainerWidget, {
			props: { content: { sortBy: 'alphabetical', placements: wide } },
			global: { stubs: { ContainerChild: true } },
		})
		const items = wrapper.findAll('.container-widget__child')
		const a = items.find((item) => item.attributes('gs-w') === '2')
		const b = items.find((item) => item.attributes('gs-w') === '3')
		expect([
			a.attributes('gs-x'),
			a.attributes('gs-y'),
			a.attributes('gs-h'),
		]).toEqual(['0', '0', '2'])
		expect([b.attributes('gs-x'), b.attributes('gs-y')]).toEqual(['0', '2'])
	})

	it('REQ-TSO-002: a daily app moves to the front, and unused tiles fall back to alphabetical', () => {
		recordLocalTileUse(1)
		recordLocalTileUse(1)
		recordLocalTileUse(1)
		expect(order(mountSorted('most-used'))).toEqual([
			'Zaaksysteem',
			'Agenda',
			'Mail',
		])

		localStorage.clear()
		__resetTileUseForTest()
		recordLocalTileUse(3)
		expect(order(mountSorted('most-used'))).toEqual([
			'Mail',
			'Agenda',
			'Zaaksysteem',
		])
	})

	it('REQ-TSO-002: clicking a tile in the container counts it in the browser only', async () => {
		const axios = (await import('@nextcloud/axios')).default
		const wrapper = mountSorted('most-used')
		await wrapper.findAll('.container-widget__child')[2].trigger('click')

		const stored = JSON.parse(localStorage.getItem('launchpad.tileUse'))
		expect(Object.keys(stored)).toHaveLength(1)
		expect(Object.values(stored)[0].count).toBe(1)
		expect(axios.post).not.toHaveBeenCalled()
	})

	it('REQ-TSO-002: last used puts the latest click first', () => {
		vi.spyOn(Date, 'now').mockReturnValueOnce(1000).mockReturnValueOnce(2000)
		recordLocalTileUse(2)
		recordLocalTileUse(3)
		expect(order(mountSorted('last-used'))).toEqual([
			'Mail',
			'Agenda',
			'Zaaksysteem',
		])
	})

	it('REQ-TSO-002: blocked storage shows the stored order without an error', () => {
		vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
			throw new Error('SecurityError')
		})
		expect(order(mountSorted('most-used'))).toEqual([
			'Zaaksysteem',
			'Agenda',
			'Mail',
		])
	})

	it('REQ-TSO-003: forget my usage returns the container to alphabetical order', async () => {
		recordLocalTileUse(1)
		const wrapper = mountSorted('most-used')
		expect(order(wrapper)).toEqual(['Zaaksysteem', 'Agenda', 'Mail'])

		const forget = wrapper.find('button.container-widget__forget')
		expect(forget.text()).toBe('Forget my usage')
		await forget.trigger('click')

		expect(order(wrapper)).toEqual(['Agenda', 'Mail', 'Zaaksysteem'])
		expect(wrapper.find('button.container-widget__forget').exists()).toBe(false)
	})

	it('REQ-TSO-003: the forget button is not offered for alphabetical or by hand', () => {
		recordLocalTileUse(1)
		expect(
			mountSorted('alphabetical')
				.find('button.container-widget__forget')
				.exists(),
		).toBe(false)
	})

	it('REQ-TSO-004: random order holds for the page load', async () => {
		const first = order(mountSorted('random'))
		const wrapper = mountSorted('random')
		await wrapper.setProps({
			content: { sortBy: 'random', placements: [...PLACEMENTS] },
		})
		expect(order(wrapper)).toEqual(first)
		expect([...first].sort()).toEqual(['Agenda', 'Mail', 'Zaaksysteem'])
	})

	it('announces the sort order to assistive technology', () => {
		expect(
			mountSorted('most-used')
				.find('.launchpad-container-grid')
				.attributes('aria-description'),
		).toBe('Sorted by most used')
		expect(
			mountSorted('manual')
				.find('.launchpad-container-grid')
				.attributes('aria-description'),
		).toBeUndefined()
	})
})
