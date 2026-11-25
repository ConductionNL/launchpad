/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The connections the Live data form offers (REQ-LIVETILE-020).
 */

import axios from '@nextcloud/axios'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fetchLiveTileConnections, lastSuccessOf, toConnectionOption } from '../liveTileConnections.js'

vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn() } }))
vi.mock('@nextcloud/router', () => ({
	generateUrl: (path, params) => path.replace(/\{(\w+)\}/g, (_, key) => params[key]),
}))

beforeEach(() => {
	axios.get.mockReset()
})

describe('lastSuccessOf', () => {
	it('prefers the row\'s own last success, else a successful last check', () => {
		expect(lastSuccessOf({ lastSuccessAt: '2026-11-25T10:38:00Z', checkedAt: 'x' })).toBe('2026-11-25T10:38:00Z')
		expect(lastSuccessOf({ status: 'ok', checkedAt: '2026-11-25T10:00:00Z' })).toBe('2026-11-25T10:00:00Z')
		expect(lastSuccessOf({ status: 'error', checkedAt: '2026-11-25T10:00:00Z' })).toBeNull()
		expect(lastSuccessOf(null)).toBeNull()
	})
})

describe('toConnectionOption', () => {
	it('uses the source id as the value and the title as the label', () => {
		expect(toConnectionOption({ sourceId: 'kcc-queue', title: 'Contact centre queue', lastSuccessAt: 't' }))
			.toEqual({ value: 'kcc-queue', label: 'Contact centre queue', lastSuccessAt: 't' })
		expect(toConnectionOption({ id: 7 })).toEqual({ value: '7', label: '7', lastSuccessAt: null })
		expect(toConnectionOption({ title: 'No id' })).toBeNull()
	})
})

describe('fetchLiveTileConnections', () => {
	it('reads integriq\'s connections of this app and leaves out rows without an id', async () => {
		axios.get.mockResolvedValue({ data: { results: [{ sourceId: 'a', title: 'A' }, { title: 'none' }] } })
		const list = await fetchLiveTileConnections()
		expect(axios.get).toHaveBeenCalledWith(
			'/apps/openregister/api/objects/integriq/app_connection',
			{ params: { app: 'launchpad', _limit: 100 } },
		)
		expect(list).toEqual([{ value: 'a', label: 'A', lastSuccessAt: null }])
	})

	it('gives an empty list when the read fails', async () => {
		axios.get.mockRejectedValue(new Error('403'))
		expect(await fetchLiveTileConnections()).toEqual([])
	})
})
