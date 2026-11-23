/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * LaunchPad without the navigation panel (runtime-shell REQ-SHELL-009).
 *
 * No route renders the panel. The dashboard view carries the workspace's own
 * More menu; every other page carries the app's More menu at its top. The
 * stand-in `CnAppRoot` honours `hideMenu` as the library does.
 *
 * The route-no-loss rule: every entry the panel would have shown has a
 * destination in the start-page menu, and every router destination is a
 * page the app declares.
 */

import { mount } from '@vue/test-utils'
import fs from 'node:fs'
import path from 'node:path'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@conduction/nextcloud-vue', () => ({
	CnAppRoot: {
		name: 'CnAppRoot',
		props: ['hideMenu', 'pageGround'],
		template:
			'<div><slot v-if="!hideMenu" name="menu"><nav data-testid="cn-nav" /></slot><main /><slot name="header-actions" /></div>',
	},
	// The user settings dialog's library sections (src/views/settings/UserSettings.vue).
	CnCredentials: { name: 'CnCredentials', template: '<div />' },
	CnNotificationPreferences: { name: 'CnNotificationPreferences', template: '<div />' },
}))
vi.mock('../customComponents.js', () => ({ default: {} }))
vi.mock('../services/iconCatalogue.js', () => ({ ICON_CATALOGUE: [], loadIconCatalogue: () => Promise.resolve([]) }))

import App, { DASHBOARD_ROUTES, panelEntriesFor } from '../App.vue'
import {
	isExternal,
	resolveMenuItem,
} from '../components/Workspace/StartPageMenu.vue'
import bundledStub from '../manifest.json'
import { routesFromManifest } from '../utils/manifestRoutes.js'
import { applyManifestFragments } from '../utils/mergeManifestFragments.js'

/**
 * The manifest main.js builds: the bundled stub with every fragment
 * merged, read from disk because `require.context` is webpack-only.
 *
 * @return {object} The merged manifest.
 */
function mergedManifest() {
	const dir = path.resolve(__dirname, '../manifest.d')
	const fragments = fs
		.readdirSync(dir)
		.filter((name) => name.endsWith('.json'))
		.sort()
		.map((name) => JSON.parse(fs.readFileSync(path.join(dir, name), 'utf8')))
	return applyManifestFragments(bundledStub, fragments)
}

function mountApp({ routeName, permissions = ['user'] }) {
	return mount(App, {
		props: {
			manifest: mergedManifest(),
			registry: {},
			pageTypes: {},
			permissions,
		},
		global: {
			mocks: { $route: { name: routeName } },
		},
	})
}

describe('App without the navigation panel (REQ-SHELL-009)', () => {
	it.each([...DASHBOARD_ROUTES, 'Store', 'Reports', 'Flows', 'admin-settings'])(
		'renders no panel on the %s route',
		(routeName) => {
			const wrapper = mountApp({ routeName })
			expect(wrapper.find('[data-testid="cn-nav"]').exists()).toBe(false)
			expect(wrapper.find('nav').exists()).toBe(false)
		},
	)

	it.each(DASHBOARD_ROUTES)(
		'leaves the More menu to the workspace on the %s route',
		(routeName) => {
			const wrapper = mountApp({ routeName })
			expect(
				wrapper.find('[data-testid="launchpad-page-menu"]').exists(),
			).toBe(false)
		},
	)

	it.each([
		'Store',
		'Reports',
		'Flows',
		'FeaturesRoadmap',
		'admin-settings',
		'admin-templates-index',
	])('puts the More menu at the top of the %s page', (routeName) => {
		const wrapper = mountApp({ routeName })
		const holder = wrapper.find('[data-testid="launchpad-page-menu"]')
		expect(holder.exists()).toBe(true)
		expect(holder.findComponent({ name: 'StartPageMenu' }).exists()).toBe(
			true,
		)
	})

	it('tells the menus the panel is left out, on every route', () => {
		const wrapper = mountApp({ routeName: 'Store' })
		expect(wrapper.vm.chromeState.railSuppressed).toBe(true)
		expect(App.provide.call({ chromeState: {} })).toHaveProperty(
			'launchpadChrome',
		)
	})
})

describe('App on the page ground (REQ-SHELL-012)', () => {
	it('asks the root to draw its pages on the page ground', () => {
		const wrapper = mountApp({ routeName: 'Workspace' })
		expect(
			wrapper.findComponent({ name: 'CnAppRoot' }).props('pageGround'),
		).toBe(true)
	})
})

describe('Route no loss: every panel destination stays reachable', () => {
	const manifest = mergedManifest()
	const routeNames = new Set(
		routesFromManifest(manifest, {})
			.map((r) => r.name)
			.filter(Boolean),
	)

	/**
	 * What CnAppNav renders from this manifest for a permission set: every
	 * entry in a panel section whose permission the account holds. The
	 * panel's own filter, restated here so this test does not pass by
	 * agreeing with `panelEntriesFor` on a wrong answer.
	 *
	 * @param {string[]} permissions The account's permissions.
	 * @return {object[]} The entries.
	 */
	function panelWouldShow(permissions) {
		return manifest.menu.filter(
			(item) =>
				['main', 'footer', 'settings'].includes(item.section ?? 'main')
				&& (!item.permission || permissions.includes(item.permission)),
		)
	}

	it.each([
		['a member', ['user']],
		['an administrator', ['user', 'admin']],
	])('offers %s every entry the panel would have shown', (_who, permissions) => {
		const shown = panelWouldShow(permissions)
		expect(shown.length).toBeGreaterThan(0)
		const offered = panelEntriesFor(manifest, permissions)
		expect(offered.map((e) => e.id).sort()).toEqual(
			shown.map((e) => e.id).sort(),
		)
		for (const entry of offered) {
			const item = resolveMenuItem(entry, () => {})
			expect(item, `${entry.id} has no destination`).not.toBeNull()
			if (item.to) {
				expect(
					routeNames.has(item.to.name),
					`${entry.id} -> ${item.to.name}`,
				).toBe(true)
			} else {
				expect(Boolean(item.href || item.onClick)).toBe(true)
			}
		}
	})

	it('hides the admin entries from a member, as the panel does', () => {
		const ids = panelEntriesFor(manifest, ['user']).map((e) => e.id)
		expect(ids).not.toContain('admin-settings')
		expect(ids).not.toContain('admin-templates')
		expect(ids).toContain('Documentation')
		expect(ids).toContain('StoreMenu')
		expect(ids).toContain('ReportsMenu')
		expect(ids).toContain('FeaturesRoadmapMenu')
	})

	it('names the three footer destinations, with Reports under Advanced', () => {
		const entries = panelEntriesFor(manifest, ['user'])
		const footer = entries
			.filter((e) => e.section === 'footer')
			.map((e) => e.label)
		expect(footer).toEqual([
			'Documentation',
			'Marketplace',
			'Features & roadmap',
		])
		expect(entries.find((e) => e.id === 'ReportsMenu').section).toBe('settings')
	})

	it('opens a scheme-prefixed link in a new tab and an app path in the same one', () => {
		expect(isExternal('https://launchpad.conduction.nl')).toBe(true)
		expect(isExternal('/index.php/settings/admin/launchpad')).toBe(false)
		expect(
			resolveMenuItem({ id: 'x', label: 'X', href: 'https://a.b' }, null)
				.external,
		).toBe(true)
	})

	it('resolves an action entry to a button only when the opener exists', () => {
		const entry = {
			id: 'me',
			label: 'Me',
			action: 'user-settings',
			route: 'ignored',
		}
		expect(resolveMenuItem(entry, null)).toBeNull()
		const open = () => {}
		expect(resolveMenuItem(entry, open)).toMatchObject({ onClick: open })
	})
})
