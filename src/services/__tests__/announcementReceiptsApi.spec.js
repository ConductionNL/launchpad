/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The receipt calls of the announcements client: the check or the x
 * confirms, seen is one batched call that skips an empty list, Seen and
 * confirmed and Send reminder (REQ-ANN-007, REQ-ANN-009).
 *
 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'

const get = vi.fn()
const post = vi.fn()
vi.mock('@nextcloud/axios', () => ({ default: { get: (...args) => get(...args), post: (...args) => post(...args) } }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => `/index.php${path}` }))

import { announcementsApi } from '../announcementsApi.js'

const BASE = '/index.php/apps/launchpad/api/announcements'

beforeEach(() => {
	get.mockReset()
	post.mockReset()
})

describe('announcement receipts client', () => {
	it('confirms with the check by default', async () => {
		post.mockResolvedValue({ data: { uuid: 'a', confirmed: true, confirmedVia: 'check' } })

		const result = await announcementsApi.confirmRead('a')

		expect(post).toHaveBeenCalledWith(`${BASE}/a/confirm`, { via: 'check' })
		expect(result.confirmedVia).toBe('check')
	})

	it('confirms with the x', async () => {
		post.mockResolvedValue({ data: { uuid: 'a', confirmed: true, confirmedVia: 'x' } })

		await announcementsApi.confirmRead('a', 'x')

		expect(post).toHaveBeenCalledWith(`${BASE}/a/confirm`, { via: 'x' })
	})

	it('sends the seen uuids in one call', async () => {
		post.mockResolvedValue({ data: { recorded: ['a'] } })

		expect(await announcementsApi.markSeen(['a', 'b'])).toEqual(['a'])
		expect(post).toHaveBeenCalledTimes(1)
		expect(post).toHaveBeenCalledWith(`${BASE}/seen`, { uuids: ['a', 'b'] })
	})

	it('sends nothing for an empty seen list', async () => {
		expect(await announcementsApi.markSeen([])).toEqual([])
		expect(await announcementsApi.markSeen(null)).toEqual([])
		expect(post).not.toHaveBeenCalled()
	})

	it('reads Seen and confirmed and sends a reminder', async () => {
		get.mockResolvedValue({ data: { seenCount: 1 } })
		post.mockResolvedValue({ data: { uuid: 'a', sent: 3 } })

		expect((await announcementsApi.receipts('a')).seenCount).toBe(1)
		expect((await announcementsApi.remind('a')).sent).toBe(3)
		expect(get).toHaveBeenCalledWith(`${BASE}/a/receipts`)
		expect(post).toHaveBeenCalledWith(`${BASE}/a/remind`)
	})
})
