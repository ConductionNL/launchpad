/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for the program, remote desktop and single sign-on tiles
 * (launcher-tile-launch-types, REQ-TLT-001 to REQ-TLT-003): the address each
 * tile opens, the program hint, and the editor fields per type.
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
vi.mock('@nextcloud/router', async (importOriginal) => ({
	...(await importOriginal()),
	generateUrl: (path, params = {}) =>
		'/index.php' + path.replace(/{(\w+)}/g, (match, key) => params[key]),
}))

const TEMPLATES = [
	{
		key: 'entra',
		name: 'Microsoft Entra ID',
		urlTemplate:
			'https://launcher.myapps.microsoft.com/api/signin/{appId}?tenantId=contoso',
	},
]

function tile(linkType, linkValue = '') {
	return {
		id: 7,
		title: 'Tegel',
		icon: 'icon-link',
		iconType: 'class',
		linkType,
		linkValue,
	}
}

function mountTile(props) {
	return mount(TileWidget, {
		props: { placementId: 7, ...props },
		global: { provide: { ssoLaunchTemplates: TEMPLATES } },
	})
}

describe('launch type tiles', () => {
	it('REQ-TLT-001: a program tile links to its own address and shows the hint after a click', async () => {
		const wrapper = mountTile({
			tile: tile('program', 'ms-word:ofe|u|https://docs.example.nl/sjabloon.docx'),
		})
		const link = wrapper.find('a')
		expect(link.attributes('href')).toBe(
			'ms-word:ofe|u|https://docs.example.nl/sjabloon.docx',
		)
		expect(link.attributes('target')).toBe('_self')
		expect(wrapper.find('[data-testid="tile-program-hint"]').exists()).toBe(false)

		await link.trigger('click')

		expect(wrapper.find('[data-testid="tile-program-hint"]').text()).toBe(
			'Nothing happened? The program may not be installed on this computer.',
		)
	})

	it('REQ-TLT-001: a web tile shows no program hint', async () => {
		const wrapper = mountTile({ tile: tile('url', 'https://zaken.gemeente.nl') })
		await wrapper.find('a').trigger('click')
		expect(wrapper.find('[data-testid="tile-program-hint"]').exists()).toBe(false)
		expect(wrapper.find('a').attributes('target')).toBe('_blank')
	})

	it('REQ-TLT-002: an RDP tile links to its file on the server', () => {
		const wrapper = mountTile({
			tile: tile('remote-desktop'),
			remote: { mode: 'rdp', host: 'rds01.gemeente.local' },
		})
		expect(wrapper.find('a').attributes('href')).toBe(
			'/index.php/apps/launchpad/api/tiles/7/rdp',
		)
		expect(wrapper.find('a').attributes('target')).toBe('_self')
	})

	it('REQ-TLT-002: a gateway tile opens the gateway in a new tab', () => {
		const wrapper = mountTile({
			tile: tile('remote-desktop'),
			remote: {
				mode: 'gateway',
				url: 'https://desktop.gemeente.nl/guacamole/#/client/werkplek',
			},
		})
		expect(wrapper.find('a').attributes('href')).toBe(
			'https://desktop.gemeente.nl/guacamole/#/client/werkplek',
		)
		expect(wrapper.find('a').attributes('target')).toBe('_blank')
	})

	it('REQ-TLT-003: a sign-on tile opens its template with the encoded app id', () => {
		const wrapper = mountTile({
			tile: tile('sso'),
			sso: { template: 'entra', appId: 'a1b2c3' },
		})
		expect(wrapper.find('a').attributes('href')).toBe(
			'https://launcher.myapps.microsoft.com/api/signin/a1b2c3?tenantId=contoso',
		)

		const encoded = mountTile({
			tile: tile('sso'),
			sso: { template: 'entra', appId: 'urn:x' },
		})
		expect(encoded.find('a').attributes('href')).toContain('/signin/urn%3Ax?')
	})

	it('REQ-TLT-003: a sign-on tile on a removed template has no address', () => {
		const wrapper = mountTile({
			tile: tile('sso'),
			sso: { template: 'okta', appId: 'a1b2c3' },
		})
		expect(wrapper.find('a').attributes('href')).toBeUndefined()
	})
})

describe('tile editor launch types', () => {
	function mountEditor(tileProp, provide = {}) {
		return mount(TileEditor, {
			props: { open: true, tile: tileProp },
			global: {
				// The dialog body is teleported; render it in place.
				stubs: { NcModal: { template: '<div><slot /></div>' } },
				provide: {
					tileAllowedSchemes: ['ms-word'],
					ssoLaunchTemplates: TEMPLATES,
					...provide,
				},
			},
		})
	}

	it('REQ-TLT-003: a sign-on tile has a template picker and an app id, and no free address field', async () => {
		const wrapper = mountEditor({ ...tile('sso'), sso: { template: 'entra', appId: 'a1b2c3' } })

		expect(wrapper.find('[data-testid="tile-sso-template"]').exists()).toBe(true)
		expect(wrapper.find('[data-testid="tile-sso-app-id"]').exists()).toBe(true)
		expect(wrapper.find('[data-testid="tile-program-address"]').exists()).toBe(false)
		expect(wrapper.vm.selectedSsoTemplate).toEqual({
			id: 'entra',
			label: 'Microsoft Entra ID',
		})

		wrapper.vm.saveTile()
		const saved = wrapper.emitted('save')[0][0]
		expect(saved.linkType).toBe('sso')
		expect(saved.linkValue).toBe('')
		expect(saved.sso).toEqual({ template: 'entra', appId: 'a1b2c3' })
		expect(saved.remote).toBeNull()
	})

	it('REQ-TLT-002: a remote desktop tile saves only its RDP connection', () => {
		const wrapper = mountEditor({
			...tile('remote-desktop'),
			remote: {
				mode: 'rdp',
				host: ' rds01.gemeente.local ',
				port: '3390',
				remoteApp: '||Belastingen',
				gateway: 'rdgw.gemeente.nl',
			},
		})
		wrapper.vm.saveTile()
		const saved = wrapper.emitted('save')[0][0]
		expect(saved.remote).toEqual({
			mode: 'rdp',
			host: 'rds01.gemeente.local',
			port: 3390,
			remoteApp: '||Belastingen',
			gateway: 'rdgw.gemeente.nl',
		})
		expect(saved.sso).toBeNull()
	})

	it('REQ-TLT-001: a program tile names the allowed addresses, or says none are allowed', () => {
		const wrapper = mountEditor(tile('program', 'ms-word:'))
		expect(wrapper.vm.programSchemesLine).toBe('Allowed program addresses: ms-word:')

		const none = mountEditor(tile('program'), { tileAllowedSchemes: [] })
		expect(none.vm.programSchemesLine).toBe(
			'An administrator has not allowed any program address yet, so this tile does nothing.',
		)
	})

	it('REQ-TLT-001: a web tile switched to a program keeps its address and carries no launch settings', () => {
		const wrapper = mountEditor(tile('url', 'https://zaken.gemeente.nl'))
		expect(wrapper.vm.linkTypeOptions.map((option) => option.id)).toEqual([
			'url',
			'program',
			'remote-desktop',
			'sso',
		])
		wrapper.vm.selectedLinkType = { id: 'program' }
		expect(wrapper.vm.form.linkType).toBe('program')

		wrapper.vm.saveTile()
		const saved = wrapper.emitted('save')[0][0]
		expect(saved.remote).toBeNull()
		expect(saved.sso).toBeNull()
		expect(saved.linkValue).toBe('https://zaken.gemeente.nl')
	})
})
