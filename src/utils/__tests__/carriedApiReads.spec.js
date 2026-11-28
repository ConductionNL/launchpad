/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/archive/2026-11-27-workspace-api-reads-from-initial-state/tasks.md#task-4
 */
import { beforeEach, describe, expect, it, vi } from 'vitest'

const state = { workspaceApiReads: [] }

vi.mock('../loadInitialState.js', () => ({
	readWorkspaceApiReads: () => (Array.isArray(state.workspaceApiReads) ? state.workspaceApiReads : []),
}))
vi.mock('@nextcloud/axios', () => ({ default: { interceptors: { request: { use: vi.fn() } } } }))

const { answerCarried, installCarriedReads, requestKey, resetCarriedReads, takeCarriedRead } = await import('../carriedApiReads.js')

describe('carriedApiReads', () => {
	beforeEach(() => {
		resetCarriedReads()
		state.workspaceApiReads = [
			{ path: '/api/announcements', params: [], data: { announcements: [{ uuid: 'a' }] } },
			{ path: '/api/widgets/files/4/contents', params: { currentPath: '/', limit: 50 }, data: { items: [1] } },
		]
	})

	it('keys a request by its path below the app and its sorted query', () => {
		expect(requestKey('/index.php/apps/launchpad/api/x', { b: 2, a: '/' })).toBe('/api/x?a=%2F&b=2')
		expect(requestKey('http://host/apps/launchpad/api/x?b=2', { a: '/' })).toBe('/api/x?a=%2F&b=2')
		expect(requestKey('/ocs/v2.php/apps/notifications/api/v2/notifications')).toBeNull()
	})

	it('hands each carried read out once', () => {
		expect(takeCarriedRead('/index.php/apps/launchpad/api/announcements')).toEqual({ data: { announcements: [{ uuid: 'a' }] } })
		expect(takeCarriedRead('/index.php/apps/launchpad/api/announcements')).toBeNull()
	})

	it('matches only the carried query', () => {
		expect(takeCarriedRead('/index.php/apps/launchpad/api/widgets/files/4/contents', { currentPath: '/Documents', limit: 50 })).toBeNull()
		expect(takeCarriedRead('/index.php/apps/launchpad/api/widgets/files/4/contents', { currentPath: '/', limit: 50 })).toEqual({ data: { items: [1] } })
	})

	it('answers a carried GET without sending it', async () => {
		const config = answerCarried({ method: 'get', url: '/index.php/apps/launchpad/api/announcements' })
		const response = await config.adapter(config)
		expect(response.status).toBe(200)
		expect(response.data).toEqual({ announcements: [{ uuid: 'a' }] })
	})

	it('leaves other requests alone', () => {
		const post = answerCarried({ method: 'post', url: '/index.php/apps/launchpad/api/announcements' })
		expect(post.adapter).toBeUndefined()
		const other = answerCarried({ method: 'get', url: '/index.php/apps/launchpad/api/tiles' })
		expect(other.adapter).toBeUndefined()
	})

	it('asks for everything when the page carries nothing', () => {
		state.workspaceApiReads = 'not a list'
		expect(takeCarriedRead('/index.php/apps/launchpad/api/announcements')).toBeNull()
	})

	it('installs its interceptor once', () => {
		const client = { interceptors: { request: { use: vi.fn() } } }
		installCarriedReads(client)
		installCarriedReads(client)
		expect(client.interceptors.request.use).toHaveBeenCalledTimes(1)
	})
})
