/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The administration as a Launchpad page (admin-settings REQ-ADM-030): the
 * tab comes from the route, opening another tab pushes its address, a page
 * reached inside the app loads the address from the server, and the More
 * menu links to the page.
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

const boot = vi.hoisted(() => ({ path: '/index.php/apps/launchpad/admin/general' }))

vi.mock('@nextcloud/router', () => ({
	generateUrl: (path) => '/index.php' + path,
}))
vi.mock('../../utils/bootPath.js', () => ({
	bootPath: () => boot.path,
}))
vi.mock('../../utils/loadInitialState.js', () => ({
	loadInitialState: () => ({ allGroups: [{ id: 'staff', displayName: 'Staff' }] }),
}))
vi.mock('../../components/admin/AdminSettings.vue', () => ({
	default: {
		name: 'AdminSettings',
		props: ['tab'],
		emits: ['tabChange'],
		inject: { allGroups: { default: () => [] } },
		template: '<div data-testid="admin-settings" :data-tab="tab" :data-groups="allGroups.length" />',
	},
}))
vi.mock('../AdminSettingsRedirect.vue', () => ({
	default: {
		name: 'AdminSettingsRedirect',
		props: ['tab'],
		template: '<div data-testid="admin-redirect" :data-tab="tab" />',
	},
}))

import AdminPage from '../AdminPage.vue'
import { startPageSections } from '../../components/Workspace/moreMenuItems.js'
import manifest from '../../manifest.json'
import { ADMIN_TABS, adminPageUrl, adminTab, isAdminPagePath } from '../../utils/adminPage.js'
import { routesFromManifest } from '../../utils/manifestRoutes.js'

/**
 * Mount the page on a route tab.
 *
 * @param {string} tab The route's tab.
 * @return {{ wrapper: object, router: { push: Function } }}
 */
function mountPage(tab) {
	const router = { push: vi.fn() }
	const wrapper = mount(AdminPage, {
		props: { tab },
		global: { mocks: { $router: router } },
	})
	return { wrapper, router }
}

describe('the administration page address', () => {
	it('is /apps/launchpad/admin/<tab>, General for an unknown or missing tab', () => {
		expect(adminPageUrl('templates')).toBe('/index.php/apps/launchpad/admin/templates')
		expect(adminPageUrl()).toBe('/index.php/apps/launchpad/admin/general')
		expect(adminPageUrl('settings')).toBe('/index.php/apps/launchpad/admin/general')
		expect(adminTab('')).toBe('general')
		expect(ADMIN_TABS[0]).toBe('general')
	})

	it('recognises the page under either app prefix', () => {
		expect(isAdminPagePath('/index.php/apps/launchpad/admin/embeds')).toBe(true)
		expect(isAdminPagePath('/apps/launchpad/admin')).toBe(true)
		expect(isAdminPagePath('/apps/launchpad/')).toBe(false)
		expect(isAdminPagePath('/apps/launchpad/administration-of-x')).toBe(false)
		expect(isAdminPagePath('/settings/admin/launchpad')).toBe(false)
	})

	it('knows the same tabs as the server', async () => {
		const { readFileSync } = await import('node:fs')
		const { join } = await import('node:path')
		const php = readFileSync(join(__dirname, '../../../lib/Controller/AdminPageController.php'), 'utf8')
		for (const tab of ADMIN_TABS) {
			expect(php).toContain(`'${tab}',`)
		}
	})
})

describe('AdminPage', () => {
	it('opens the tab the route names', () => {
		const { wrapper } = mountPage('embeds')
		expect(wrapper.find('[data-testid="admin-settings"]').attributes('data-tab')).toBe('embeds')
	})

	it('opens General for an unknown or missing tab', () => {
		expect(mountPage('nonsense').wrapper.find('[data-testid="admin-settings"]').attributes('data-tab')).toBe('general')
		expect(mountPage('').wrapper.find('[data-testid="admin-settings"]').attributes('data-tab')).toBe('general')
	})

	it('hands the administration initial state to the tabs', () => {
		const { wrapper } = mountPage('general')
		expect(wrapper.find('[data-testid="admin-settings"]').attributes('data-groups')).toBe('1')
	})

	it('puts an opened tab in the address as a new history entry', async () => {
		const { wrapper, router } = mountPage('general')
		await wrapper.findComponent({ name: 'AdminSettings' }).vm.$emit('tabChange', 'sharing')
		expect(router.push).toHaveBeenCalledWith({ name: 'admin-settings', params: { tab: 'sharing' } })
	})

	it('pushes nothing for the tab already open', async () => {
		const { wrapper, router } = mountPage('sharing')
		await wrapper.findComponent({ name: 'AdminSettings' }).vm.$emit('tabChange', 'sharing')
		expect(router.push).not.toHaveBeenCalled()
	})

	it('loads the page from the server when reached from another page of the app', () => {
		boot.path = '/index.php/apps/launchpad/'
		try {
			const { wrapper } = mountPage('templates')
			expect(wrapper.find('[data-testid="admin-settings"]').exists()).toBe(false)
			expect(wrapper.find('[data-testid="admin-redirect"]').attributes('data-tab')).toBe('templates')
		} finally {
			boot.path = '/index.php/apps/launchpad/admin/general'
		}
	})
})

describe('the administration routes', () => {
	const routes = routesFromManifest(manifest, {})

	it('serve /admin/:tab? and /admin/templates with the page, for administrators', () => {
		const admin = routes.find((route) => route.name === 'admin-settings')
		expect(admin.path).toBe('/admin/:tab?')
		expect(admin.props).toBe(true)
		expect(admin.meta.permission).toBe('admin')
		const page = manifest.pages.find((p) => p.id === 'admin-settings')
		expect(page.component).toBe('AdminPage')
		const templates = manifest.pages.find((p) => p.id === 'admin-templates-index')
		expect(templates.component).toBe('AdminPage')
		expect(templates.config.tab).toBe('templates')
	})
})

describe('the More menu', () => {
	it('links an administrator to the administration page on General', () => {
		const chrome = { entries: [], isAdmin: true, includePersonalSettings: false }
		const link = startPageSections(chrome, null).settings.find((item) => item.id === 'admin-settings-link')
		expect(link.href).toBe('/index.php/apps/launchpad/admin/general')
		expect(link.external).toBe(false)
	})

	it('offers a member no administration link', () => {
		const chrome = { entries: [], isAdmin: false, includePersonalSettings: false }
		const ids = startPageSections(chrome, null).settings.map((item) => item.id)
		expect(ids).not.toContain('admin-settings-link')
	})
})
