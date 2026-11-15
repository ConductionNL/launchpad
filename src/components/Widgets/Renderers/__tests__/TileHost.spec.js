/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * integrations-office-tiles: inside a container a `create` tile renders
 * through LaunchPad's TileWidget; every other tile keeps the shared
 * CnDashTileWidget with the same content, placement and attributes.
 *
 * @spec openspec/changes/integrations-office-tiles/specs/office-tiles/spec.md
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@conduction/nextcloud-vue', () => ({
	CnDashTileWidget: { name: 'CnDashTileWidget', props: ['content', 'placement', 'canEdit'], template: '<div class="cn-tile" />' },
}))
vi.mock('../../../TileWidget.vue', () => ({
	default: { name: 'TileWidget', props: ['tile', 'editMode'], template: '<div class="lp-tile" />' },
}))

const { default: TileHost } = await import('../TileHost.vue')

describe('TileHost', () => {
	it('renders a create tile through TileWidget with the child content', () => {
		const content = { title: 'New document', linkType: 'create', linkValue: 'richdocuments:.docx', icon: 'icon-filetype-file', iconType: 'class' }
		const wrapper = mount(TileHost, { props: { content, placement: { id: 3 }, editMode: true } })

		const tile = wrapper.findComponent({ name: 'TileWidget' })
		expect(wrapper.findComponent({ name: 'CnDashTileWidget' }).exists()).toBe(false)
		expect(tile.props('editMode')).toBe(true)
		expect(tile.props('tile')).toMatchObject({ id: 3, title: 'New document', linkType: 'create', linkValue: 'richdocuments:.docx', icon: 'icon-filetype-file', iconType: 'class' })
	})

	it('names a create tile without a placement id after its creator', () => {
		const wrapper = mount(TileHost, { props: { content: { linkType: 'create', linkValue: 'onlyoffice:.xlsx' } } })

		expect(wrapper.findComponent({ name: 'TileWidget' }).props('tile').id).toBe('create-onlyoffice:.xlsx')
	})

	it('hands every other tile to CnDashTileWidget unchanged', () => {
		const content = { title: 'Files', linkType: 'app', linkValue: '/apps/files/' }
		const placement = { id: 4 }
		const wrapper = mount(TileHost, { props: { content, placement }, attrs: { canEdit: true } })

		const shared = wrapper.findComponent({ name: 'CnDashTileWidget' })
		expect(wrapper.findComponent({ name: 'TileWidget' }).exists()).toBe(false)
		expect(shared.props('content')).toEqual(content)
		expect(shared.props('placement')).toEqual(placement)
		expect(shared.props('canEdit')).toBe(true)
	})
})
