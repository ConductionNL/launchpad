/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * With pretty URLs on, `generateUrl` drops `/index.php`, but a page opened
 * through the front controller has its router mounted under it. The deep
 * link the page writes keeps the prefix it was opened on, so the address
 * stays under the router's base. A unified search result's
 * `#dashboard/<uuid>` link opens the dashboard it names.
 *
 * @spec openspec/specs/dashboard-deeplinking/spec.md
 */

import { beforeAll, describe, expect, it, vi } from 'vitest'

vi.mock('../../services/api.js', () => ({
	api: { getDashboardPath: vi.fn(), getDashboardByPath: vi.fn() },
}))
vi.mock('@nextcloud/router', () => ({
	generateUrl: (path) => path,
	generateOcsUrl: (path) => `/ocs/v2.php${path}`,
	imagePath: (app, file) => `/${app}/img/${file}`,
	generateFilePath: (app, type, file) => `/${app}/${type}/${file}`,
	linkTo: (app, file) => `/${app}/${file}`,
}))
vi.mock('../../stores/tileSearch.js', () => ({
	useTileSearchStore: vi.fn(),
}))
vi.mock('../../utils/logger.js', () => ({
	logger: { debug: vi.fn(), info: vi.fn(), warn: vi.fn(), error: vi.fn() },
}))

let Views

/**
 * A host carrying the real Views methods.
 *
 * @param {object} state Data and overrides.
 * @return {object} the host.
 */
function makeHost(state = {}) {
	const host = { activeDashboard: null }
	for (const [name, fn] of Object.entries(Views.methods)) {
		host[name] = fn.bind(host)
	}
	return Object.assign(host, state)
}

beforeAll(async () => {
	window.history.replaceState(null, '', '/index.php/apps/launchpad/team/hr')
	Views = (await import('../Views.vue')).default
})

describe('Views deep link under the front controller', () => {
	it('builds the address under the prefix the page was opened on', () => {
		expect(makeHost().buildDeepLinkUrl('team/hr')).toBe('/index.php/apps/launchpad/team/hr')
	})

	it('leaves the boot address alone when it already is the deep link', () => {
		const replace = vi.spyOn(window.history, 'replaceState')
		makeHost({ injectedDeepLinkPath: 'team/hr', activeDashboard: { uuid: 'u-hr' } })
			.replaceUrlFromInitialState()
		expect(replace).not.toHaveBeenCalled()
		expect(window.location.pathname).toBe('/index.php/apps/launchpad/team/hr')
	})
})

describe('Views unified search link', () => {
	it('opens the dashboard a #dashboard/<uuid> hash names', async () => {
		const switchDashboard = vi.fn()
		const host = makeHost({
			activeDashboard: { uuid: 'u-other' },
			dashboards: [{ id: 1, uuid: 'u-other' }, { id: 2, uuid: 'u-target' }],
			switchDashboard,
		})
		await host.openHashLinkedDashboard('#dashboard/u-target;widget=7')
		expect(switchDashboard).toHaveBeenCalledWith(2)
	})

	it('keeps the open dashboard for its own uuid, an unknown uuid or another hash', async () => {
		const switchDashboard = vi.fn()
		const host = makeHost({
			activeDashboard: { uuid: 'u-other' },
			dashboards: [{ id: 1, uuid: 'u-other' }],
			switchDashboard,
		})
		await host.openHashLinkedDashboard('#dashboard/u-other')
		await host.openHashLinkedDashboard('#dashboard/u-gone')
		await host.openHashLinkedDashboard('#user-settings')
		expect(switchDashboard).not.toHaveBeenCalled()
	})
})
