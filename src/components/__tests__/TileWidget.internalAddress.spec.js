/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for the office network address of a tile
 * (launcher-tile-internal-address, REQ-TIA-002, REQ-TIA-003): the tile opens
 * the internal address only when the server says the request is on an office
 * network, and the editor keeps the field and says which address is in effect.
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import TileEditor from '../../modals/TileEditor.vue'
import TileWidget from '../TileWidget.vue'

vi.mock('../../services/healthPingClient.js', () => ({
	validateHealthPingConfig: vi.fn(),
}))
vi.mock('../../composables/useTileClickTracking.js', () => ({
	useTileClickTracking: () => ({ recordTileClick: vi.fn() }),
}))

const TILE = {
	id: 5,
	title: 'Zaaksysteem',
	icon: 'icon-link',
	iconType: 'class',
	linkType: 'url',
	linkValue: 'https://zaken.gemeente.nl',
}

function mountTile(onOfficeNetwork, internalUrl = 'http://zaken.intern') {
	return mount(TileWidget, {
		props: { tile: TILE, internalUrl },
		global: { provide: { onOfficeNetwork } },
	})
}

describe('tile office network address', () => {
	it('REQ-TIA-003 at the office: the tile opens the internal address', () => {
		expect(mountTile(true).find('a').attributes('href')).toBe(
			'http://zaken.intern',
		)
	})

	it('REQ-TIA-003 at home: the tile opens the main address', () => {
		expect(mountTile(false).find('a').attributes('href')).toBe(
			'https://zaken.gemeente.nl',
		)
	})

	it('REQ-TIA-003: without an internal address the office network changes nothing', () => {
		expect(mountTile(true, '').find('a').attributes('href')).toBe(
			'https://zaken.gemeente.nl',
		)
	})

	it('REQ-TIA-003: a path on this Nextcloud goes through generateUrl', () => {
		expect(
			mountTile(true, '/apps/files').find('a').attributes('href'),
		).toContain('/apps/files')
	})

	it('REQ-TIA-002: the editor keeps the internal address and says which address is in effect', async () => {
		const wrapper = mount(TileEditor, {
			props: {
				open: true,
				tile: { ...TILE, internalUrl: 'http://zaken.intern' },
			},
			global: { provide: { onOfficeNetwork: true } },
		})
		// The dialog body is teleported; read the line the template renders.
		expect(wrapper.vm.addressInEffect).toBe(
			'You are on the office network: this tile opens the internal address',
		)

		wrapper.vm.saveTile()
		expect(wrapper.emitted('save')[0][0].internalUrl).toBe('http://zaken.intern')

		const away = mount(TileEditor, {
			props: {
				open: true,
				tile: { ...TILE, internalUrl: 'http://zaken.intern' },
			},
			global: { provide: { onOfficeNetwork: false } },
		})
		expect(away.vm.addressInEffect).toBe(
			'You are not on the office network: this tile opens the main address',
		)
	})
})
