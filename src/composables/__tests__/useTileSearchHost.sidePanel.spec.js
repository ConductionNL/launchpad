/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Quick search opens a side-panel tile in the panel, as a click does
 * (launcher-tile-open-behaviour task 8): activation clicks the rendered tile
 * link, and TileWidget's click handler decides where it opens.
 *
 * @spec openspec/changes/launcher-tile-open-behaviour/specs/tiles/spec.md
 */

import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { h } from 'vue'

vi.mock('@conduction/nextcloud-vue', () => ({
	CnWidgetEditCog: { name: 'CnWidgetEditCog', template: '<div />' },
	CnDashboardIcon: { name: 'CnDashboardIcon', template: '<i />' },
}))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => '/nc' + path }))
vi.mock('../useTileClickTracking.js', () => ({ useTileClickTracking: () => ({ recordTileClick: vi.fn() }) }))
vi.mock('../../components/HealthPingBadge.vue', () => ({ default: { name: 'HealthPingBadge', template: '<span />' } }))

const { default: TileWidget } = await import('../../components/TileWidget.vue')
const { activateSearchResult, GRID_CONTAINER_ID } = await import('../useTileSearchHost.js')
const { useTilePanelStore } = await import('../../stores/tilePanel.js')

/**
 * Mount one tile inside a grid item, as Views.vue renders it.
 *
 * @param {number} id The placement id.
 * @param {object} props TileWidget props.
 * @return {object} The wrapper.
 */
function mountGridTile(id, props) {
	const host = document.createElement('div')
	host.id = GRID_CONTAINER_ID
	document.body.appendChild(host)

	return mount(
		{ render: () => h('div', { class: 'launchpad-grid-item', 'data-placement-id': String(id) }, [h(TileWidget, props)]) },
		{ attachTo: host },
	)
}

describe('quick search on a side-panel tile', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
	})

	afterEach(() => {
		document.body.innerHTML = ''
	})

	it('opens the tile in the panel', () => {
		mountGridTile(7, { tile: { id: 7, title: 'Files', linkType: 'app', linkValue: 'files' }, linkTarget: 'side-panel' })

		expect(activateSearchResult({ id: 7, placement: { id: 7 } })).toBe(true)

		const panel = useTilePanelStore()
		expect(panel.isOpen).toBe(true)
		expect(panel.url).toBe('/nc/apps/files')
		expect(panel.title).toBe('Files')
	})

	it('leaves the panel closed for a tile that opens in a tab', () => {
		mountGridTile(8, { tile: { id: 8, title: 'Deck', linkType: 'app', linkValue: 'deck' }, defaultLinkTarget: 'new-tab' })
		const link = document.querySelector('[data-placement-id="8"] a')
		link.addEventListener('click', (event) => event.preventDefault())

		activateSearchResult({ id: 8, placement: { id: 8 } })

		expect(useTilePanelStore().isOpen).toBe(false)
		expect(link.getAttribute('target')).toBe('_blank')
	})
})
