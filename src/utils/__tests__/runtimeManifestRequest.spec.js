/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

import axios from '@nextcloud/axios'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { resetRuntimeManifestRequest, runtimeManifestRequest } from '../runtimeManifestRequest.js'

vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn() } }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => `/index.php${path}` }))

beforeEach(() => {
	resetRuntimeManifestRequest()
	axios.get.mockReset()
	axios.get.mockResolvedValue({ status: 200, data: { $schema: 'x' } })
})

describe('runtimeManifestRequest', () => {
	it('fetches the manifest once however many callers ask', async () => {
		const first = runtimeManifestRequest()
		const second = runtimeManifestRequest()

		expect(second).toBe(first)
		expect(axios.get).toHaveBeenCalledTimes(1)
		expect(axios.get).toHaveBeenCalledWith('/index.php/apps/launchpad/api/manifest')
		expect((await second).data).toEqual({ $schema: 'x' })
	})

	it('hands a failure to every caller without asking again', async () => {
		axios.get.mockRejectedValue(new Error('down'))

		await expect(runtimeManifestRequest()).rejects.toThrow('down')
		await expect(runtimeManifestRequest()).rejects.toThrow('down')
		expect(axios.get).toHaveBeenCalledTimes(1)
	})
})
