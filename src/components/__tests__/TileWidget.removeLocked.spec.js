/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A tile whose removal the host locks (a compulsory tile on a dashboard held
 * with less than full permission) asks the edit cog to leave Delete out.
 *
 * @spec openspec/specs/permissions/spec.md
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@conduction/nextcloud-vue', () => ({
	CnDashboardIcon: { name: 'CnDashboardIcon', template: '<i />' },
}))
vi.mock('../Widgets/WidgetEditMenu.vue', () => ({ default: { name: 'WidgetEditMenu', props: ['removeLocked', 'embedHref', 'embedDisabled'], template: '<div class="cog" />' } }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => '/nc' + path }))
vi.mock('../../composables/useTileClickTracking.js', () => ({ useTileClickTracking: () => ({ recordTileClick: vi.fn() }) }))
vi.mock('../HealthPingBadge.vue', () => ({ default: { name: 'HealthPingBadge', template: '<span />' } }))

const { default: TileWidget } = await import('../TileWidget.vue')

const TILE = { id: 3, title: 'Files', linkType: 'url', linkValue: '/apps/files', iconType: 'class', icon: 'icon-files' }

/**
 * The edit cog of a tile in edit mode.
 *
 * @param {object} props Extra props.
 * @return {object} The cog wrapper.
 */
function cogOf(props) {
	return mount(TileWidget, {
		props: { tile: TILE, editMode: true, ...props },
		global: { mocks: { t: (_app, key) => key } },
	}).findComponent({ name: 'WidgetEditMenu' })
}

describe('TileWidget removeLocked', () => {
	it('offers Delete by default and leaves it out when removal is locked', () => {
		expect(cogOf({}).props('removeLocked')).toBe(false)
		expect(cogOf({ removeLocked: true }).props('removeLocked')).toBe(true)
	})
})

// @spec openspec/changes/edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-047
describe('TileWidget embed entry (REQ-WDG-047)', () => {
	const href = '/index.php/apps/launchpad/admin/general?tab=embeds&embedType=widget&embedId=3'

	it('hands the embed link to the edit menu, enabled for a saved tile', () => {
		const cog = cogOf({ embedHref: href, placementId: 3 })
		expect(cog.props('embedHref')).toBe(href)
		expect(cog.props('embedDisabled')).toBe(false)
	})

	it('disables it for a tile that is not saved yet', () => {
		expect(cogOf({ embedHref: href }).props('embedDisabled')).toBe(true)
	})

	it('shows no menu at all in view mode', () => {
		const wrapper = mount(TileWidget, {
			props: { tile: TILE, embedHref: href, placementId: 3 },
			global: { mocks: { t: (_app, key) => key } },
		})
		expect(wrapper.findComponent({ name: 'WidgetEditMenu' }).exists()).toBe(false)
	})
})
