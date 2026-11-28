/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The HTTP client's early reads: GETs started before the component that
 * wants them renders, answered once from the request in flight.
 *
 * @spec openspec/changes/archive/2026-11-27-dashboard-switch-reads-early/tasks.md#task-3
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('../loadInitialState.js', () => ({ readWorkspaceApiReads: () => [] }))
vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn(), interceptors: { request: { use: vi.fn() } } } }))

const { EARLY_TTL_MS, answerCarried, dropEarlyReads, resetCarriedReads, startEarlyRead, takeEarlyRead } = await import('../carriedApiReads.js')

const URL = '/index.php/apps/launchpad/api/dashboards/u-1/reactions'

/**
 * A client whose GET answers with the given promise and records its configs.
 *
 * @param {Promise<object>} answer What every GET resolves to.
 * @return {{get: Function}} The client.
 */
function clientAnswering(answer) {
	return { get: vi.fn(() => answer) }
}

describe('carriedApiReads early reads', () => {
	beforeEach(() => {
		resetCarriedReads()
	})

	afterEach(() => {
		vi.useRealTimers()
	})

	it('sends the early GET once, marked so it is not answered from its own entry', () => {
		const client = clientAnswering(Promise.resolve({ data: {} }))
		startEarlyRead(URL, {}, client)

		expect(client.get).toHaveBeenCalledTimes(1)
		const [url, config] = client.get.mock.calls[0]
		expect(url).toBe(URL)
		expect(config.launchpadEarlyRead).toBe(true)
		// The early request passes the interceptor untouched.
		const own = { method: 'get', url, ...config }
		expect(answerCarried(own).adapter).toBeUndefined()
	})

	it('answers the first matching GET from the early read, once', async () => {
		startEarlyRead(URL, {}, clientAnswering(Promise.resolve({ data: { '🎉': 2 }, status: 200 })))

		const config = answerCarried({ method: 'get', url: URL })
		expect(typeof config.adapter).toBe('function')
		const response = await config.adapter()
		expect(response.data).toEqual({ '🎉': 2 })
		expect(response.config).toBe(config)

		expect(answerCarried({ method: 'get', url: URL }).adapter).toBeUndefined()
	})

	it('matches path and query only', () => {
		startEarlyRead(URL, { a: 1 }, clientAnswering(Promise.resolve({ data: {} })))
		expect(takeEarlyRead(URL, { a: 2 })).toBeNull()
		expect(takeEarlyRead(URL + '?a=1')).not.toBeNull()
	})

	it('answers a matching GET with the early read\'s failure', async () => {
		const failure = new Error('403')
		startEarlyRead(URL, {}, clientAnswering(Promise.reject(failure)))

		const config = answerCarried({ method: 'get', url: URL })
		await expect(config.adapter()).rejects.toBe(failure)
	})

	it('lets a GET go out once the early read has expired', () => {
		vi.useFakeTimers()
		vi.setSystemTime(new Date('2026-11-27T10:00:00Z'))
		startEarlyRead(URL, {}, clientAnswering(Promise.resolve({ data: {} })))
		vi.setSystemTime(Date.now() + EARLY_TTL_MS + 1)

		expect(answerCarried({ method: 'get', url: URL }).adapter).toBeUndefined()
	})

	it('drops what nobody took', () => {
		startEarlyRead(URL, {}, clientAnswering(Promise.resolve({ data: {} })))
		dropEarlyReads()
		expect(takeEarlyRead(URL)).toBeNull()
	})

	it('starts nothing when the client cannot send, so the GET goes out later', () => {
		startEarlyRead(URL, {}, { get: () => { throw new Error('offline') } })
		expect(takeEarlyRead(URL)).toBeNull()
	})

	it('leaves other methods and URLs outside the app alone', () => {
		const client = clientAnswering(Promise.resolve({ data: {} }))
		startEarlyRead('/ocs/v2.php/apps/notifications/api/v2/notifications', {}, client)
		expect(client.get).not.toHaveBeenCalled()

		startEarlyRead(URL, {}, client)
		expect(answerCarried({ method: 'post', url: URL }).adapter).toBeUndefined()
		expect(takeEarlyRead(URL)).not.toBeNull()
	})
})
