/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Where the More menu sits on a page other than the dashboard view
 * (runtime-shell REQ-SHELL-009, board LpStore): as the last button of the
 * page header's action row when the page draws one, after Publish; at the
 * top end of the content otherwise.
 */

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { afterEach, describe, expect, it, vi } from 'vitest'

const page = vi.hoisted(() => ({ withHeaderRow: true }))

vi.mock('@conduction/nextcloud-vue', () => ({
	CnAppRoot: {
		name: 'CnAppRoot',
		props: ['hideMenu', 'pageGround'],
		data: () => ({ page }),
		template: `<div id="app-content-vue">
			<div v-if="page.withHeaderRow" class="cn-page-header">
				<div class="cn-page-header__text"><h1>Marketplace</h1></div>
				<div class="cn-store-page__header-actions" data-testid="header-row">
					<button data-testid="store-publish">Publish</button>
				</div>
			</div>
			<div v-else class="cn-page-header"><div class="cn-page-header__text"><h1>Flows</h1></div></div>
			<slot name="header-actions" />
		</div>`,
	},
	CnCredentials: { name: 'CnCredentials', template: '<div />' },
	CnNotificationPreferences: { name: 'CnNotificationPreferences', template: '<div />' },
}))
vi.mock('../customComponents.js', () => ({ default: {} }))
vi.mock('../services/iconCatalogue.js', () => ({ ICON_CATALOGUE: [], LAZY_ICON_CATALOGUE: [], loadIconCatalogue: () => Promise.resolve([]) }))

import App from '../App.vue'

/**
 * Mount the app on a page other than the dashboard view.
 *
 * @return {import('@vue/test-utils').VueWrapper} The mounted app.
 */
function mountStore() {
	return mount(App, {
		attachTo: document.body,
		props: { manifest: { pages: [], menu: [] }, registry: {}, pageTypes: {}, permissions: ['user'] },
		global: {
			plugins: [createPinia()],
			mocks: { $route: { name: 'Store' } },
		},
	})
}

afterEach(() => {
	document.body.innerHTML = ''
})

describe('the More menu on a page with a header action row', () => {
	it('is the last button of that row, after Publish', async () => {
		page.withHeaderRow = true
		const wrapper = mountStore()
		await flushPromises()
		const row = document.querySelector('[data-testid="header-row"]')
		const menu = document.querySelector('[data-testid="launchpad-page-menu"]')
		expect(menu.parentElement).toBe(row)
		expect(row.lastElementChild).toBe(menu)
		expect(menu.classList.contains('launchpad-page-menu--in-header')).toBe(true)
		wrapper.unmount()
	})
})

describe('the More menu on a page without a header action row', () => {
	it('stays at the top end of the content', async () => {
		page.withHeaderRow = false
		const wrapper = mountStore()
		await flushPromises()
		const menu = document.querySelector('[data-testid="launchpad-page-menu"]')
		expect(menu.parentElement.id).toBe('app-content-vue')
		expect(menu.classList.contains('launchpad-page-menu--in-header')).toBe(false)
		wrapper.unmount()
	})
})
