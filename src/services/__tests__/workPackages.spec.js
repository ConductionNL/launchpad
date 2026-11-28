/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The work packages client (work-packages-widget REQ-WP-001..002).
 */

import axios from '@nextcloud/axios'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { loadWorkPackages } from '../workPackages.js'

vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn() } }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => `/index.php${path}` }))

beforeEach(() => {
	axios.get.mockReset()
})

describe('loadWorkPackages', () => {
	it('asks launchpad with the scope, project and limit and makes the connect address absolute', async () => {
		axios.get.mockResolvedValue({ data: { state: 'not-connected', workPackages: [], connectUrl: '/settings/user/openproject' } })
		expect(await loadWorkPackages({ scope: 'mine', project: '', limit: 4 })).toEqual({ state: 'not-connected', workPackages: [], connectUrl: '/index.php/settings/user/openproject' })
		expect(axios.get).toHaveBeenCalledWith('/index.php/apps/launchpad/api/work-packages', { params: { scope: 'mine', project: '', limit: 4 } })
	})

	it('reads a failed request or an odd answer as an error', async () => {
		axios.get.mockRejectedValueOnce(new Error('500'))
		expect((await loadWorkPackages({ scope: 'mine', project: '', limit: 4 })).state).toBe('error')
		axios.get.mockResolvedValueOnce({ data: null })
		expect(await loadWorkPackages({ scope: 'mine', project: '', limit: 4 })).toEqual({ state: 'error', workPackages: [], connectUrl: '' })
	})
})
