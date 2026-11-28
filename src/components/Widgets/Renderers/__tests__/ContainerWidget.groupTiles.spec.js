/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A tile inside a group opens the tile form from its own tile menu in edit
 * mode (tile-form-board-sync REQ-TILE-041): ContainerChild shows the menu,
 * ContainerWidget hands the edit or delete to the dashboard's opener.
 *
 * @spec openspec/changes/archive/2026-11-25-tile-form-board-sync/specs/tiles/spec.md#requirement-a-tiles-group-and-the-groups-order-req-tile-041
 */

import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import ContainerChild from '../ContainerChild.vue'
import ContainerWidget from '../ContainerWidget.vue'
import { readTileUse } from '../../../../utils/tileSort.js'

// The child's edit menu (WidgetEditMenu since REQ-WDG-050), stubbed down to
// its labels, Edit and Delete.
const CnWidgetEditCog = {
	name: 'WidgetEditMenu',
	props: ['menuLabel', 'editLabel', 'deleteLabel'],
	emits: ['edit', 'remove'],
	template: '<div class="cog" :data-menu="menuLabel"><button class="edit" @click="$emit(\'edit\')">{{ editLabel }}</button><button class="remove" @click="$emit(\'remove\')">{{ deleteLabel }}</button></div>',
}

const tile = { type: 'tile', content: { title: 'Case system', linkType: 'url', linkValue: 'https://cases.example.org' } }
const bookmark = { type: 'link', content: { label: 'Intranet', url: 'https://intranet.example.org', actionType: 'external' } }
const label = { type: 'label', content: { text: 'Hello' } }

beforeEach(() => {
	globalThis.t = (_app, key) => key
})

/**
 * Mount a child.
 *
 * @param {object} placement The child.
 * @param {object} [props] More props.
 * @return {object} The wrapper.
 */
function mountChild(placement, props = {}) {
	return mount(ContainerChild, {
		props: { placement, editMode: true, canEditTile: true, ...props },
		global: { stubs: { WidgetEditMenu: CnWidgetEditCog } },
	})
}

describe('ContainerChild — the tile menu of a tile in a group', () => {
	it('shows the tile menu on a tile and on an address link in edit mode', () => {
		for (const child of [tile, bookmark]) {
			const wrapper = mountChild(child)
			const menu = wrapper.find('[data-testid="container-child-tile-menu"]')
			expect(menu.exists()).toBe(true)
			expect(menu.find('.cog').attributes('data-menu')).toBe('Tile menu')
			expect(menu.find('.edit').text()).toBe('Edit tile')
			expect(menu.find('.remove').text()).toBe('Delete tile')
		}
	})

	it('shows no tile menu in view mode, without an opener, or on another widget', () => {
		expect(mountChild(tile, { editMode: false }).find('[data-testid="container-child-tile-menu"]').exists()).toBe(false)
		expect(mountChild(tile, { canEditTile: false }).find('[data-testid="container-child-tile-menu"]').exists()).toBe(false)
		expect(mountChild(label).find('[data-testid="container-child-tile-menu"]').exists()).toBe(false)
		expect(mountChild({ type: 'tile', content: { linkType: 'create', linkValue: 'docx' } }).find('[data-testid="container-child-tile-menu"]').exists()).toBe(false)
	})

	it('emits editTile and removeTile from the menu', async () => {
		const wrapper = mountChild(tile)
		await wrapper.find('.edit').trigger('click')
		await wrapper.find('.remove').trigger('click')
		expect(wrapper.emitted('editTile')).toHaveLength(1)
		expect(wrapper.emitted('removeTile')).toHaveLength(1)
	})
})

/**
 * Mount a group.
 *
 * @param {object} props The props.
 * @param {Function|null} opener The dashboard's opener.
 * @return {object} The wrapper.
 */
function mountGroup(props, opener) {
	return mount(ContainerWidget, {
		props,
		global: {
			stubs: { WidgetEditMenu: CnWidgetEditCog },
			provide: opener ? { openGroupTileEditor: opener } : {},
		},
	})
}

describe('ContainerWidget — hands a group tile to the dashboard', () => {
	const content = { title: 'Work apps', sortBy: 'alphabetical', placements: [label, bookmark, tile] }

	it('opens the form for the child at its stored position', async () => {
		const opener = vi.fn()
		const wrapper = mountGroup({ content, placement: { id: 41 }, editMode: true }, opener)
		const edits = wrapper.findAll('.edit')
		expect(edits).toHaveLength(2)
		await edits[1].trigger('click')
		expect(opener).toHaveBeenCalledWith({ containerId: 41, index: 2, remove: false })
		await wrapper.findAll('.remove')[0].trigger('click')
		expect(opener).toHaveBeenLastCalledWith({ containerId: 41, index: 1, remove: true })
	})

	it('offers no tile menu without an opener, a placement id, or in view mode', () => {
		expect(mountGroup({ content, placement: { id: 41 }, editMode: true }, null).vm.canEditTiles).toBe(false)
		expect(mountGroup({ content, placement: {}, editMode: true }, vi.fn()).vm.canEditTiles).toBe(false)
		expect(mountGroup({ content, placement: { id: 41 }, editMode: false }, vi.fn()).vm.canEditTiles).toBe(false)
	})

	it('opens nothing while the shown order is not the stored one', () => {
		const opener = vi.fn()
		const wrapper = mountGroup({ content, placement: { id: 41 }, editMode: false }, opener)
		wrapper.vm.editTile(2)
		expect(opener).not.toHaveBeenCalled()
	})
})

describe('ContainerWidget — a click counts a use of the cell it landed in', () => {
	it('counts the child of this grid, also when the click lands in a group inside it', async () => {
		localStorage.clear()
		const inner = { id: 'inner', type: 'container', content: { placements: [{ ...tile, id: 'deep' }] } }
		const wrapper = mountGroup({
			content: { placements: [{ ...label, id: 'first' }, inner] },
			placement: { id: 41 },
			editMode: false,
		}, vi.fn())
		const cells = wrapper.findAll('.launchpad-container-grid > [data-launchpad-child]')
		expect(cells).toHaveLength(2)
		await cells[0].trigger('click')
		expect(readTileUse().first.count).toBe(1)
		const deep = cells[1].findAll('[data-launchpad-child]')
		const target = deep.length > 0 ? deep[0] : cells[1]
		await target.trigger('click')
		expect(readTileUse().inner.count).toBe(1)
		wrapper.unmount()
	})
})
