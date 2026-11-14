/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * How TileWidget opens per link target (launcher-tile-open-behaviour D2, D3).
 *
 * @spec openspec/changes/launcher-tile-open-behaviour/specs/tiles/spec.md
 */

import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@conduction/nextcloud-vue', () => ({
	CnWidgetEditCog: { name: 'CnWidgetEditCog', template: '<div />' },
	CnDashboardIcon: { name: 'CnDashboardIcon', template: '<i />' },
}))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => '/nc' + path }))
vi.mock('../../composables/useTileClickTracking.js', () => ({
	useTileClickTracking: () => ({ recordTileClick: vi.fn() }),
}))
vi.mock('../HealthPingBadge.vue', () => ({ default: { name: 'HealthPingBadge', template: '<span />' } }))

const { default: TileWidget } = await import('../TileWidget.vue')
const { useTilePanelStore } = await import('../../stores/tilePanel.js')

let nextId = 100

/**
 * Mount a tile.
 *
 * @param {object} tileFields Tile fields.
 * @param {object} props Extra props.
 * @return {object} The wrapper.
 */
function mountTile(tileFields = {}, props = {}) {
	return mount(TileWidget, {
		attachTo: document.body,
		props: {
			tile: { id: nextId++, title: 'Files', linkType: 'app', linkValue: 'files', iconType: 'emoji', icon: 'x', ...tileFields },
			...props,
		},
	})
}

describe('TileWidget link target', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
	})

	it('opens per its own target, else the dashboard default, else the link type', () => {
		const target = (tile, props) => mountTile(tile, props).find('a').attributes('target')

		expect(target({}, { linkTarget: 'new-tab' })).toBe('_blank')
		expect(target({ linkType: 'url', linkValue: 'https://x.example' }, { linkTarget: 'same-tab' })).toBe('_self')
		expect(target({}, { defaultLinkTarget: 'new-tab' })).toBe('_blank')
		expect(target({ linkType: 'url', linkValue: 'https://x.example' }, {})).toBe('_blank')
		expect(target({}, {})).toBe('_self')
		expect(target({}, { linkTarget: 'side-panel' })).toBe('_self')
	})

	it('a side-panel tile opens the panel instead of navigating, and returns focus to itself', async () => {
		const wrapper = mountTile({}, { linkTarget: 'side-panel' })
		const link = wrapper.find('a')
		const event = new MouseEvent('click', { cancelable: true, bubbles: true })

		link.element.dispatchEvent(event)

		const panel = useTilePanelStore()
		expect(event.defaultPrevented).toBe(true)
		expect(panel.isOpen).toBe(true)
		expect(panel.url).toBe('/nc/apps/files')
		expect(panel.title).toBe('Files')
		expect(panel.sameOrigin).toBe(true)
		expect(panel.opener).toBe(link.element)
		wrapper.unmount()
	})

	it('an outside side-panel address is marked for the framing check', () => {
		mountTile({ linkType: 'url', linkValue: 'https://x.example/page' }, { defaultLinkTarget: 'side-panel' }).find('a').trigger('click')

		expect(useTilePanelStore().sameOrigin).toBe(false)
		expect(useTilePanelStore().url).toBe('https://x.example/page')
	})

	it('other targets and edit mode leave the panel closed', async () => {
		await mountTile({}, { linkTarget: 'new-tab' }).find('a').trigger('click')
		await mountTile({}, { linkTarget: 'side-panel', editMode: true }).find('a').trigger('click')

		expect(useTilePanelStore().isOpen).toBe(false)
	})
})
