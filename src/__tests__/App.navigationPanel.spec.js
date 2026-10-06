/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The start page without the navigation panel (runtime-shell REQ-SHELL-009).
 *
 * Off, the panel renders; on, it is not rendered on the dashboard view and
 * still is on every other page. The stand-in `CnAppRoot` keeps the real
 * slot semantics: Vue renders a slot's default content when the override
 * holds no real node, which is exactly what the override must not rely on.
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
		template:
			'<div><slot name="menu"><nav data-testid="cn-nav" /></slot><main /></div>',
	},
}))
vi.mock('../customComponents.js', () => ({ default: {} }))
vi.mock('../services/iconCatalogue.js', () => ({ ICON_CATALOGUE: [] }))

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

function mountApp({ option, routeName, permissions = ['user'] }) {
	return mount(App, {
		props: {
			manifest: mergedManifest(),
			registry: {},
			pageTypes: {},
			permissions,
		},
		global: {
			provide: { startPageWithoutNavigation: option },
			mocks: { $route: { name: routeName } },
		},
	})
}

describe('App without the navigation panel (REQ-SHELL-009)', () => {
	it('renders the panel when the option is off', () => {
		const wrapper = mountApp({ option: false, routeName: 'Workspace' })
		expect(wrapper.find('[data-testid="cn-nav"]').exists()).toBe(true)
		// Off is today's shell exactly: the root receives no `menu` slot, so
		// the library renders its own panel as it did before this option.
		expect(
			wrapper.findComponent({ name: 'CnAppRoot' }).vm.$slots.menu,
		).toBeUndefined()
		expect(
			wrapper.find('[data-testid="launchpad-rail-suppressed"]').exists(),
		).toBe(false)
	})

	it('renders the panel when the option is missing from the initial state', () => {
		const wrapper = mount(App, {
			props: { manifest: mergedManifest(), registry: {}, pageTypes: {} },
			global: { mocks: { $route: { name: 'Workspace' } } },
		})
		expect(wrapper.find('[data-testid="cn-nav"]').exists()).toBe(true)
	})

	it.each(DASHBOARD_ROUTES)(
		'does not render the panel on the %s route when the option is on',
		(routeName) => {
			const wrapper = mountApp({ option: true, routeName })
			expect(wrapper.find('[data-testid="cn-nav"]').exists()).toBe(false)
			expect(wrapper.find('nav').exists()).toBe(false)
			const placeholder = wrapper.find(
				'[data-testid="launchpad-rail-suppressed"]',
			)
			expect(placeholder.exists()).toBe(true)
			expect(placeholder.attributes('hidden')).toBeDefined()
		},
	)

	it.each([
		'Store',
		'Reports',
		'Flows',
		'admin-settings',
		'admin-templates-index',
	])('keeps the panel on the %s page when the option is on', (routeName) => {
		const wrapper = mountApp({ option: true, routeName })
		expect(wrapper.find('[data-testid="cn-nav"]').exists()).toBe(true)
	})

	it('is what the root receives', () => {
		// The template binding is the wiring; a computed nobody binds is a
		// guard with no call site.
		const render = App.render?.toString() ?? ''
		expect(render).toContain('railSuppressed')
		expect(App.provide.call({ chromeState: {} })).toHaveProperty(
			'launchpadChrome',
		)
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

	it('names the four footer destinations of ADR-114', () => {
		const footer = panelEntriesFor(manifest, ['user'])
			.filter((e) => e.section === 'footer')
			.map((e) => e.label)
		expect(footer).toEqual([
			'Documentation',
			'Store',
			'Reports',
			'Features & roadmap',
		])
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
