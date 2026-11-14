/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest for `TileWidget.vue`'s office-network address: the tile opens it only
 * when the server saw the request come from an office network AND the tile
 * has one; everywhere else it opens its main address.
 *
 * @spec openspec/changes/launcher-tile-internal-address/specs/tiles/spec.md#requirement-the-tile-opens-the-address-that-fits-the-network-req-tia-003
 */

import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const network = vi.hoisted(() => ({ office: false }))

vi.mock('@conduction/nextcloud-vue', () => ({
	CnWidgetEditCog: { name: 'CnWidgetEditCog', template: '<div />' },
	CnDashboardIcon: { name: 'CnDashboardIcon', props: ['name', 'size'], template: '<i />' },
}))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => '/nc' + path }))
vi.mock('../../composables/useTileClickTracking.js', () => ({ useTileClickTracking: () => ({ recordTileClick: vi.fn() }) }))
vi.mock('../HealthPingBadge.vue', () => ({ default: { name: 'HealthPingBadge', template: '<span />' } }))
vi.mock('../../utils/loadInitialState.js', () => ({ isOnOfficeNetwork: () => network.office }))

const { default: TileWidget } = await import('../TileWidget.vue')

let nextId = 1000

/**
 * The href a tile renders.
 *
 * @param {object} fields The tile fields.
 * @return {string}
 */
function hrefOf(fields) {
	const tile = { id: nextId++, title: 'Zaaksysteem', linkType: 'url', linkValue: 'https://zaken.gemeente.nl', iconType: 'emoji', icon: '📋', ...fields }
	return mount(TileWidget, { props: { tile } }).find('a').attributes('href')
}

describe('TileWidget office-network address', () => {
	beforeEach(() => {
		network.office = false
	})

	it('opens the internal address at the office', () => {
		network.office = true
		expect(hrefOf({ internalUrl: 'http://zaken.intern' })).toBe('http://zaken.intern')
	})

	it('opens the main address at home', () => {
		expect(hrefOf({ internalUrl: 'http://zaken.intern' })).toBe('https://zaken.gemeente.nl')
	})

	it('opens the main address at the office when the tile has no internal address', () => {
		network.office = true
		expect(hrefOf({ internalUrl: '' })).toBe('https://zaken.gemeente.nl')
		expect(hrefOf({})).toBe('https://zaken.gemeente.nl')
	})

	it('routes an internal path through generateUrl, even for an app tile', () => {
		network.office = true
		expect(hrefOf({ linkType: 'app', linkValue: 'deck', internalUrl: '/apps/files' })).toBe('/nc/apps/files')
		network.office = false
		expect(hrefOf({ linkType: 'app', linkValue: 'deck', internalUrl: '/apps/files' })).toBe('/nc/apps/deck')
	})
})
