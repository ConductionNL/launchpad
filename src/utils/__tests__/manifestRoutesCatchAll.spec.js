/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The catch-all keeps a dashboard's slug address (dashboard-deeplinking
 * REQ-DDL-003, REQ-DDL-006): it is an alias of the start page, not a
 * redirect that would rewrite the address to `/` in history.
 *
 * @spec openspec/specs/dashboard-deeplinking/spec.md
 */

import { describe, expect, it } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { routesFromManifest } from '../manifestRoutes.js'

const component = { render: () => null }
const manifest = {
	pages: [
		{ id: 'Workspace', route: '/' },
		{ id: 'Store', route: '/store' },
		{ id: 'dashboard-detail', route: '/dashboards/:id' },
	],
}

/**
 * A router over the given manifest, on memory history.
 *
 * @param {object} m The manifest.
 * @return {object} the router.
 */
function routerFor(m) {
	return createRouter({ history: createMemoryHistory(), routes: routesFromManifest(m, component) })
}

describe('routesFromManifest catch-all', () => {
	it('serves a slug chain as the start page and keeps its address', async () => {
		const router = routerFor(manifest)
		await router.push('/team/sales')

		expect(router.currentRoute.value.name).toBe('Workspace')
		expect(router.currentRoute.value.fullPath).toBe('/team/sales')
	})

	it('still routes the declared pages to themselves', async () => {
		const router = routerFor(manifest)
		await router.push('/store')
		expect(router.currentRoute.value.name).toBe('Store')

		await router.push('/dashboards/7')
		expect(router.currentRoute.value.name).toBe('dashboard-detail')
	})

	it('redirects to / when the manifest has no start page', async () => {
		const routes = routesFromManifest({ pages: [{ id: 'Store', route: '/store' }] }, component)

		expect(routes.at(-1)).toEqual({ path: '/:pathMatch(.*)*', redirect: '/' })
	})
})
