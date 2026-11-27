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
	CnAppTileWidget: { name: 'CnAppTileWidget', props: ['content', 'placement'], template: '<div class="cn-app-tile" />' },
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

	// app-link-tile-widget REQ-APPTILE-002, -005, -006.
	it('renders an app-mode tile through the shared app tile in its text colour', () => {
		const content = { mode: 'app', appId: 'files', colourToken: '--sc-work-field', textColour: '--sc-primary-deep' }
		const wrapper = mount(TileHost, { props: { content, placement: { id: 5, widgetId: 'tile' } } })

		const app = wrapper.findComponent({ name: 'CnAppTileWidget' })
		expect(wrapper.findComponent({ name: 'CnDashTileWidget' }).exists()).toBe(false)
		expect(app.props('content')).toMatchObject({ appId: 'files', colour: 'work-field' })
		expect(wrapper.find('[data-testid="tile-host-app"]').attributes('style')).toContain('--lp-tile-text: var(--sc-primary-deep, #234a78)')
	})

	it('draws an app tile without a text colour in white', () => {
		const wrapper = mount(TileHost, { props: { content: { mode: 'app', appId: 'deck' } } })

		expect(wrapper.find('[data-testid="tile-host-app"]').attributes('style')).toContain('--lp-tile-text: #ffffff')
	})

	it('renders a retired app tile as an app-mode tile', () => {
		const wrapper = mount(TileHost, { props: { content: { appId: 'mail', colour: 'accent' }, placement: { id: 6, widgetId: 'app-tile' } } })

		expect(wrapper.findComponent({ name: 'CnAppTileWidget' }).props('content')).toMatchObject({ mode: 'app', appId: 'mail', colour: 'accent' })
	})

	it('gives a link tile its stored text colour', () => {
		const content = { mode: 'link', title: 'Intranet', linkType: 'url', linkValue: 'https://intranet.example.org', textColour: 'black' }
		const wrapper = mount(TileHost, { props: { content, placement: { id: 7 } } })

		expect(wrapper.findComponent({ name: 'CnDashTileWidget' }).props('content')).toEqual({ ...content, textColor: '#000000' })
	})
})
