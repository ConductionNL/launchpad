/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The visible list sends the detail filter as `metadata[<key>]`, which PHP
 * reads as the `metadata` array DashboardApiController passes on.
 */

import axios from '@nextcloud/axios'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '../api.js'

vi.mock('@nextcloud/axios', () => ({
	default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => `/index.php${path}` }))

beforeEach(() => vi.clearAllMocks())

describe('details api', () => {
	it('filters the visible list with metadata params, and asks plainly without', () => {
		api.getVisibleDashboards({ department: 'Finance' })
		expect(axios.get).toHaveBeenCalledWith(
			'/index.php/apps/launchpad/api/dashboards/visible',
			{ params: { metadata: { department: 'Finance' } } },
		)
		api.getVisibleDashboards()
		expect(axios.get).toHaveBeenLastCalledWith(
			'/index.php/apps/launchpad/api/dashboards/visible',
		)
	})

	it('reads field definitions from the endpoint open to every user', () => {
		api.getMetadataFieldDefinitions()
		expect(axios.get).toHaveBeenCalledWith(
			'/index.php/apps/launchpad/api/metadata-fields',
		)
	})
})
