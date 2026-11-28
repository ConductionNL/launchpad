/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The reader's read state is read once per page: the notice banner and the
 * announcements widget share it, and a write that can change it drops it.
 *
 * @spec openspec/changes/archive/2026-11-24-announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'

const get = vi.fn()
const post = vi.fn()
vi.mock('@nextcloud/axios', () => ({ default: { get: (...args) => get(...args), post: (...args) => post(...args) } }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => `/index.php${path}` }))

import { announcementsApi, forgetReadState } from '../announcementsApi.js'

beforeEach(() => {
	get.mockReset()
	post.mockReset()
	forgetReadState()
})

describe('announcementsApi.readState', () => {
	it('asks the server once for the banner and the widget', async () => {
		get.mockResolvedValue({ data: { confirmed: ['a'], receipts: [] } })
		const [banner, widget] = await Promise.all([announcementsApi.readState(), announcementsApi.readState()])
		expect(await announcementsApi.readState()).toEqual({ confirmed: ['a'], receipts: [] })
		expect(banner).toBe(widget)
		expect(get).toHaveBeenCalledTimes(1)
		expect(get).toHaveBeenCalledWith('/index.php/apps/launchpad/api/announcements/read-state')
	})

	it('asks again after a confirmation, also when the confirmation fails', async () => {
		get.mockResolvedValue({ data: { confirmed: [], receipts: [] } })
		await announcementsApi.readState()
		post.mockResolvedValueOnce({ data: { uuid: 'a', confirmed: true } })
		await announcementsApi.confirmRead('a')
		await announcementsApi.readState()
		expect(get).toHaveBeenCalledTimes(2)

		post.mockRejectedValueOnce(new Error('403'))
		await expect(announcementsApi.confirmRead('b')).rejects.toThrow('403')
		await announcementsApi.readState()
		expect(get).toHaveBeenCalledTimes(3)
	})

	it('asks again after a failed read', async () => {
		get.mockRejectedValueOnce(new Error('offline'))
		await expect(announcementsApi.readState()).rejects.toThrow('offline')
		get.mockResolvedValueOnce({ data: { confirmed: [], receipts: ['x'] } })
		expect(await announcementsApi.readState()).toEqual({ confirmed: [], receipts: ['x'] })
		expect(get).toHaveBeenCalledTimes(2)
	})
})
