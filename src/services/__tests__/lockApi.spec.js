/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The lock calls reach the routes DashboardLockApiController serves
 * (appinfo/routes.php, dashboardLockApi#*), and the page-hide release
 * survives the page closing (keepalive through axios' fetch adapter, so the
 * request token interceptor still applies).
 */

import axios from '@nextcloud/axios'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '../api.js'

vi.mock('@nextcloud/axios', () => ({
	default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))

vi.mock('@nextcloud/router', () => ({
	generateUrl: (path) => `/index.php${path}`,
}))

const url = '/index.php/apps/launchpad/api/dashboards/dash-uuid/lock'

beforeEach(() => vi.clearAllMocks())

describe('lock api', () => {
	it('acquires, refreshes, releases and force-releases on the lock routes', () => {
		api.acquireLock('dash-uuid')
		api.heartbeatLock('dash-uuid')
		api.releaseLock('dash-uuid')
		api.forceReleaseLock('dash-uuid')
		expect(axios.post).toHaveBeenCalledWith(url)
		expect(axios.put).toHaveBeenCalledWith(url)
		expect(axios.delete).toHaveBeenCalledWith(url)
		expect(axios.post).toHaveBeenCalledWith(`${url}/force-release`)
	})

	it('releases on page hide with a keepalive request', () => {
		api.releaseLockOnPageHide('dash-uuid')
		expect(axios.delete).toHaveBeenCalledWith(url, { adapter: 'fetch', fetchOptions: { keepalive: true } })
	})
})
