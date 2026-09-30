/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The version calls reach the routes DashboardVersionApiController serves
 * (appinfo/routes.php, dashboardVersionApi#*).
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

const url = '/index.php/apps/launchpad/api/dashboards/dash-uuid/versions'

beforeEach(() => vi.clearAllMocks())

describe('versions api', () => {
	it('lists, saves with a note and restores on the version routes', () => {
		api.listVersions('dash-uuid')
		api.createVersion('dash-uuid', 'before the reorganisation')
		api.restoreVersion('dash-uuid', 3)
		expect(axios.get).toHaveBeenCalledWith(url)
		expect(axios.post).toHaveBeenCalledWith(url, {
			note: 'before the reorganisation',
		})
		expect(axios.post).toHaveBeenCalledWith(`${url}/3/restore`)
	})
})
