/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The admin embed API client calls the token and theme routes with the
 * right verbs, paths and bodies (embedded-analytics tasks 15, 16, 17, 27).
 *
 * @spec openspec/changes/embedded-analytics/tasks.md#task-15
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { embedApi } from '../embedApi.js'

const http = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() }))
vi.mock('@nextcloud/axios', () => ({ default: http }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => `/index.php${path}` }))

const base = '/index.php/apps/launchpad/api'

describe('embedApi', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		for (const verb of Object.values(http)) {
			verb.mockResolvedValue({ data: { ok: true } })
		}
	})

	it('lists tokens and themes from results, empty when the answer has none', async () => {
		http.get.mockResolvedValueOnce({ data: { results: [{ id: 't-1' }] } }).mockResolvedValueOnce({ data: {} })
		expect(await embedApi.listTokens()).toEqual([{ id: 't-1' }])
		expect(await embedApi.listThemes()).toEqual([])
		expect(http.get.mock.calls).toEqual([[`${base}/embed-tokens`], [`${base}/tenant-themes`]])
	})

	it('sends each token action to its route', async () => {
		await embedApi.createToken({ name: 'Zeist' })
		await embedApi.updateToken('t/1', { name: 'Z' })
		await embedApi.revokeToken('t-1', 'other', '')
		await embedApi.revokeToken('t-1', 'compromised', 'gelekt')
		await embedApi.unrevokeToken('t-1')
		await embedApi.reissueToken('t-1')
		await embedApi.tokenUsage('t-1')

		expect(http.post.mock.calls).toEqual([
			[`${base}/embed-tokens`, { name: 'Zeist' }],
			[`${base}/embed-tokens/t-1/revoke`, { reason: 'other', notes: null }],
			[`${base}/embed-tokens/t-1/revoke`, { reason: 'compromised', notes: 'gelekt' }],
			[`${base}/embed-tokens/t-1/unrevoke`],
			[`${base}/embed-tokens/t-1/re-issue`],
		])
		expect(http.put).toHaveBeenCalledWith(`${base}/embed-tokens/t%2F1`, { name: 'Z' })
		expect(http.get).toHaveBeenCalledWith(`${base}/embed-tokens/t-1/usage`)
	})

	it('sends each theme action to its route, forcing a delete only when asked', async () => {
		await embedApi.createTheme({ name: 'Zeist' })
		await embedApi.updateTheme('th-1', { name: 'Zeist' })
		await embedApi.deleteTheme('th-1')
		await embedApi.deleteTheme('th-1', true)

		expect(http.post).toHaveBeenCalledWith(`${base}/tenant-themes`, { name: 'Zeist' })
		expect(http.put).toHaveBeenCalledWith(`${base}/tenant-themes/th-1`, { name: 'Zeist' })
		expect(http.delete.mock.calls).toEqual([
			[`${base}/tenant-themes/th-1`, { params: {} }],
			[`${base}/tenant-themes/th-1`, { params: { force: true } }],
		])
	})
})
