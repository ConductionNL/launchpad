/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest for `TileEditor.vue`'s office-network address: the field is pre-filled
 * from the tile, saved with it, and the line below it says which address the
 * tile opens for the author right now.
 *
 * @spec openspec/changes/launcher-tile-internal-address/specs/tiles/spec.md#requirement-a-tile-can-hold-an-office-network-address-req-tia-002
 */
import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const network = vi.hoisted(() => ({ office: false }))

vi.mock('../../services/healthPingClient.js', () => ({ validateHealthPingConfig: vi.fn() }))
vi.mock('../../utils/loadInitialState.js', () => ({ isOnOfficeNetwork: () => network.office }))

const { default: TileEditor } = await import('../TileEditor.vue')

/**
 * Mount the editor for a tile.
 *
 * @param {object|null} tile The tile, or null for a new one.
 * @return {import('@vue/test-utils').VueWrapper}
 */
function mountEditor(tile) {
	return mount(TileEditor, { propsData: { open: true, tile } })
}

describe('TileEditor office-network address', () => {
	beforeEach(() => {
		network.office = false
	})

	it('pre-fills the internal address and saves it with the tile', () => {
		const wrapper = mountEditor({ id: 1, title: 'Zaaksysteem', linkType: 'url', linkValue: 'https://zaken.gemeente.nl', internalUrl: 'http://zaken.intern' })
		expect(wrapper.vm.form.internalUrl).toBe('http://zaken.intern')

		wrapper.vm.saveTile()
		expect(wrapper.emitted('save')[0][0]).toMatchObject({ linkValue: 'https://zaken.gemeente.nl', internalUrl: 'http://zaken.intern' })
	})

	it('starts empty for a new tile and for a tile without one', () => {
		expect(mountEditor(null).vm.form.internalUrl).toBe('')
		expect(mountEditor({ id: 2, title: 'Deck' }).vm.form.internalUrl).toBe('')
	})

	it('says the tile opens the internal address on the office network', () => {
		network.office = true
		const wrapper = mountEditor({ id: 3, title: 'Zaaksysteem' })
		expect(wrapper.vm.addressInEffect).toBe('You are on the office network: this tile opens the internal address')
	})

	it('says the tile opens the main address elsewhere', () => {
		const wrapper = mountEditor({ id: 4, title: 'Zaaksysteem' })
		expect(wrapper.vm.addressInEffect).toBe('You are not on the office network: this tile opens the main address')
	})
})
