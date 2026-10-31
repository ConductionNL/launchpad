/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The start-page menu stands in for the navigation panel when the panel is
 * left out (runtime-shell REQ-SHELL-009): nothing while the panel is there,
 * and otherwise every destination, grouped as the panel groups them.
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => ({ t: (_app, text) => text }))
vi.mock('@nextcloud/router', () => ({
	generateUrl: (tpl, vars) => tpl.replace('{appId}', vars.appId),
}))
vi.mock('@nextcloud/vue', () => ({
	NcActions: {
		name: 'NcActions',
		props: ['ariaLabel'],
		template: '<div role="menu" :aria-label="ariaLabel"><slot /></div>',
	},
	NcActionLink: {
		name: 'NcActionLink',
		props: ['href', 'target'],
		template: '<a :href="href" :target="target"><slot /></a>',
	},
	NcActionRouter: {
		name: 'NcActionRouter',
		props: ['to'],
		template: '<a class="router" :data-to="to.name"><slot /></a>',
	},
	NcActionButton: {
		name: 'NcActionButton',
		emits: ['click'],
		template:
			'<button type="button" @click="$emit(\'click\')"><slot /></button>',
	},
	NcActionSeparator: { name: 'NcActionSeparator', template: '<hr />' },
}))

import StartPageMenu from '../StartPageMenu.vue'

const ENTRIES = [
	{ id: 'dashboards', label: 'Dashboards', route: 'Workspace', order: 1 },
	{
		id: 'Documentation',
		label: 'Documentation',
		href: 'https://launchpad.conduction.nl',
		section: 'footer',
		order: 90,
	},
	{
		id: 'StoreMenu',
		label: 'Store',
		route: 'Store',
		section: 'footer',
		order: 91,
	},
	{
		id: 'FlowsMenu',
		label: 'Flows',
		route: 'Flows',
		section: 'settings',
		order: 50,
	},
	{
		id: 'admin-settings',
		label: 'Admin settings',
		route: 'admin-settings',
		section: 'settings',
		permission: 'admin',
		order: 60,
	},
]

function mountMenu(chrome, opener = vi.fn()) {
	return mount(StartPageMenu, {
		global: {
			provide: {
				launchpadChrome: { value: chrome },
				cnOpenUserSettings: opener,
			},
		},
	})
}

describe('StartPageMenu (REQ-SHELL-009)', () => {
	it('renders nothing while the panel is rendered', () => {
		const wrapper = mountMenu({
			railSuppressed: false,
			entries: ENTRIES,
			isAdmin: true,
		})
		expect(
			wrapper.find('[data-testid="launchpad-start-page-menu"]').exists(),
		).toBe(false)
		expect(wrapper.html()).toBe('<!--v-if-->')
	})

	it('renders nothing when nothing was provided', () => {
		const wrapper = mount(StartPageMenu)
		expect(
			wrapper.find('[data-testid="launchpad-start-page-menu"]').exists(),
		).toBe(false)
	})

	it('offers every entry with its own destination, in the panel order', () => {
		const wrapper = mountMenu({
			railSuppressed: true,
			entries: ENTRIES,
			isAdmin: true,
		})
		const menu = wrapper.find('[data-testid="launchpad-start-page-menu"]')
		expect(menu.attributes('aria-label')).toBe('Menu')

		const docs = wrapper.find(
			'[data-testid="launchpad-start-page-menu-Documentation"]',
		)
		expect(docs.attributes('href')).toBe('https://launchpad.conduction.nl')
		expect(docs.attributes('target')).toBe('_blank')

		expect(
			wrapper
				.find('[data-testid="launchpad-start-page-menu-StoreMenu"]')
				.attributes('data-to'),
		).toBe('Store')
		expect(
			wrapper
				.find('[data-testid="launchpad-start-page-menu-dashboards"]')
				.attributes('data-to'),
		).toBe('Workspace')
		expect(
			wrapper
				.find('[data-testid="launchpad-start-page-menu-admin-settings"]')
				.attributes('data-to'),
		).toBe('admin-settings')

		const ids = wrapper
			.findAll('[data-testid^="launchpad-start-page-menu-"]')
			.map((el) =>
				el
					.attributes('data-testid')
					.replace('launchpad-start-page-menu-', ''),
			)
		expect(ids).toEqual([
			'dashboards',
			'Documentation',
			'StoreMenu',
			'personal-settings',
			'admin-settings-link',
			'FlowsMenu',
			'admin-settings',
		])
		// Three sections, two separators between them.
		expect(wrapper.findAll('hr')).toHaveLength(2)
	})

	it('links an administrator to the Nextcloud admin settings in the same tab', () => {
		const wrapper = mountMenu({
			railSuppressed: true,
			entries: [],
			isAdmin: true,
		})
		const link = wrapper.find(
			'[data-testid="launchpad-start-page-menu-admin-settings-link"]',
		)
		expect(link.attributes('href')).toBe('/settings/admin/launchpad')
		expect(link.attributes('target')).toBe('_self')
	})

	it('gives a member no admin link', () => {
		const wrapper = mountMenu({
			railSuppressed: true,
			entries: [],
			isAdmin: false,
		})
		expect(
			wrapper
				.find(
					'[data-testid="launchpad-start-page-menu-admin-settings-link"]',
				)
				.exists(),
		).toBe(false)
	})

	it('opens the personal settings through the library opener', async () => {
		const opener = vi.fn()
		const wrapper = mountMenu(
			{ railSuppressed: true, entries: [], isAdmin: false },
			opener,
		)
		await wrapper
			.find('[data-testid="launchpad-start-page-menu-personal-settings"]')
			.trigger('click')
		expect(opener).toHaveBeenCalledTimes(1)
	})

	it('renders no button when it has nothing to offer', () => {
		const wrapper = mountMenu({
			railSuppressed: true,
			entries: [],
			isAdmin: false,
			includePersonalSettings: false,
		})
		expect(
			wrapper.find('[data-testid="launchpad-start-page-menu"]').exists(),
		).toBe(false)
	})
})
