/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for how `ContainerWidget.vue` keeps its children's
 * positions in sync (REQ-CONT-005): a GridStack `change` maps moved nodes
 * back onto `content.placements[]` by their index and emits the whole
 * content with the new placements.
 */

import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it } from 'vitest'
import ContainerWidget from '../ContainerWidget.vue'

const PLACEMENTS = [
	{ id: 1, type: 'label', content: { text: 'A' }, gridX: 0, gridY: 0, gridWidth: 2, gridHeight: 2 },
	{ id: 2, type: 'label', content: { text: 'B' }, gridX: 2, gridY: 0, gridWidth: 2, gridHeight: 2 },
]

function mountContainer(content = { title: 'Box', placements: PLACEMENTS }) {
	return mount(ContainerWidget, {
		props: { content },
		global: { stubs: { ContainerChild: true } },
	})
}

const node = (index, x, y, w, h) => ({ el: { dataset: { launchpadIndex: String(index) } }, x, y, w, h })

beforeEach(() => {
	globalThis.t = (_app, key) => key
})

describe('ContainerWidget persistence', () => {
	it('writes a moved node\'s position onto its placement and keeps the rest', () => {
		const wrapper = mountContainer()

		wrapper.vm.onGridChange({}, [node(1, 4, 3, 1, 5)])

		expect(wrapper.emitted('update:content')).toEqual([[{
			title: 'Box',
			placements: [
				PLACEMENTS[0],
				{ ...PLACEMENTS[1], gridX: 4, gridY: 3, gridWidth: 1, gridHeight: 5 },
			],
		}]])
	})

	it('ignores nodes without an element or with an unknown index', () => {
		const wrapper = mountContainer()

		wrapper.vm.onGridChange({}, [{ x: 9, y: 9, w: 9, h: 9 }, node(7, 1, 1, 1, 1), { el: {}, x: 0 }])

		expect(wrapper.emitted('update:content')[0][0].placements).toEqual(PLACEMENTS)
	})

	it('emits nothing for an empty or missing node list', () => {
		const wrapper = mountContainer()
		wrapper.vm.onGridChange({}, [])
		wrapper.vm.onGridChange({}, undefined)
		expect(wrapper.emitted('update:content')).toBeUndefined()
	})

	it('persists a non-array as no placements', () => {
		const wrapper = mountContainer({ placements: PLACEMENTS })
		wrapper.vm.handlePersist(null)
		expect(wrapper.emitted('update:content')).toEqual([[{ placements: [] }]])
	})
})
