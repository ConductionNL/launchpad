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
	CnWidgetEditCog: { name: 'CnWidgetEditCog', props: ['canDelete'], template: '<div class="cog" />' },
	CnDashboardIcon: { name: 'CnDashboardIcon', template: '<i />' },
}))
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
	}).findComponent({ name: 'CnWidgetEditCog' })
}

describe('TileWidget removeLocked', () => {
	it('offers Delete by default and leaves it out when removal is locked', () => {
		expect(cogOf({}).props('canDelete')).toBe(true)
		expect(cogOf({ removeLocked: true }).props('canDelete')).toBe(false)
	})
})
