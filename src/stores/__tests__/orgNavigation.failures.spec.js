/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for the orgNavigation store's failure and fallback
 * paths: a failed tree load, a failed or unknown position load, and the
 * position save's fallbacks and error messages.
 */

import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '../../services/api.js'
import { useOrgNavigationStore } from '../orgNavigation.js'

vi.mock('@nextcloud/router', () => ({
	generateUrl: (path) => `/index.php${path}`,
}))

vi.mock('../../services/api.js', () => ({
	api: {
		getOrgNavigation: vi.fn(),
		updateOrgNavigation: vi.fn(),
		getOrgNavigationPosition: vi.fn(),
		updateOrgNavigationPosition: vi.fn(),
	},
}))

beforeEach(() => {
	setActivePinia(createPinia())
	vi.resetAllMocks()
})

describe('orgNavigation store failures', () => {
	it('a failed tree load empties the tree and keeps the error message', async () => {
		const store = useOrgNavigationStore()
		store.tree = [{ id: 'old' }]
		api.getOrgNavigation.mockRejectedValue(new Error('Gone'))

		await store.fetchTree('en')

		expect(api.getOrgNavigation).toHaveBeenCalledWith('en')
		expect(store.tree).toEqual([])
		expect(store.error).toBe('Gone')
		expect(store.loading).toBe(false)
	})

	it('a failed tree load without a message uses a generic one', async () => {
		const store = useOrgNavigationStore()
		api.getOrgNavigation.mockRejectedValue({})

		await store.fetchTree()

		expect(store.error).toBe('Failed to load navigation')
	})

	it('an unknown position from the server keeps the current one', async () => {
		const store = useOrgNavigationStore()
		store.position = 'left'
		api.getOrgNavigationPosition.mockResolvedValue({ data: { position: 'bottom' } })

		await store.fetchPosition()

		expect(store.position).toBe('left')
	})

	it('a failed position load keeps the position and records the error', async () => {
		const store = useOrgNavigationStore()
		store.position = 'top'
		api.getOrgNavigationPosition.mockRejectedValueOnce(new Error('Timeout'))
		await store.fetchPosition()
		expect(store.position).toBe('top')
		expect(store.error).toBe('Timeout')

		api.getOrgNavigationPosition.mockRejectedValueOnce(null)
		await store.fetchPosition()
		expect(store.error).toBe('Failed to load position')
	})

	it('saving an unsupported position is refused without a request', async () => {
		const store = useOrgNavigationStore()

		const ok = await store.updatePosition('bottom')

		expect(ok).toBe(false)
		expect(store.error).toBe('Unsupported position')
		expect(api.updateOrgNavigationPosition).not.toHaveBeenCalled()
	})

	it('a saved position without one in the response keeps the requested one', async () => {
		const store = useOrgNavigationStore()
		store.error = 'old'
		api.updateOrgNavigationPosition.mockResolvedValue({ data: {} })

		const ok = await store.updatePosition('right')

		expect(ok).toBe(true)
		expect(store.position).toBe('right')
		expect(store.error).toBeNull()
	})

	it('a failed position save shows the server error, else a generic one', async () => {
		const store = useOrgNavigationStore()
		api.updateOrgNavigationPosition.mockRejectedValueOnce({
			response: { data: { error: 'Admins only' } },
		})
		expect(await store.updatePosition('left')).toBe(false)
		expect(store.error).toBe('Admins only')

		api.updateOrgNavigationPosition.mockRejectedValueOnce(new Error('x'))
		expect(await store.updatePosition('left')).toBe(false)
		expect(store.error).toBe('Failed to save position')
	})
})
