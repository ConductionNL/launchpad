/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The org navigation store's first load: from the initial state when the
 * page carries it, from the API when it does not.
 *
 * @spec openspec/changes/org-navigation-from-initial-state/tasks.md#task-4
 */

import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '../../services/api.js'
import { takeOrgNavigation } from '../../utils/loadInitialState.js'
import { useOrgNavigationStore } from '../orgNavigation.js'

vi.mock('../../services/api.js', () => ({
	api: {
		getOrgNavigation: vi.fn(),
		getOrgNavigationPosition: vi.fn(),
	},
}))
vi.mock('../../utils/loadInitialState.js', () => ({ takeOrgNavigation: vi.fn() }))

beforeEach(() => {
	setActivePinia(createPinia())
	vi.resetAllMocks()
})

describe('orgNavigation store first load', () => {
	it('takes the position and the tree the page carries, without asking the API', async () => {
		takeOrgNavigation.mockReturnValue({ position: 'left', tree: [{ id: 'n1' }], language: 'en' })
		const store = useOrgNavigationStore()

		await store.loadFirst()

		expect(store.position).toBe('left')
		expect(store.tree).toEqual([{ id: 'n1' }])
		expect(store.language).toBe('en')
		expect(api.getOrgNavigation).not.toHaveBeenCalled()
		expect(api.getOrgNavigationPosition).not.toHaveBeenCalled()
	})

	it('keeps the hidden default for a position it does not know', async () => {
		takeOrgNavigation.mockReturnValue({ position: 'sideways', tree: [], language: 'nl' })
		const store = useOrgNavigationStore()

		await store.loadFirst()

		expect(store.position).toBe('hidden')
	})

	it('asks the API for both when the page carries none', async () => {
		takeOrgNavigation.mockReturnValue(null)
		api.getOrgNavigationPosition.mockResolvedValue({ data: { position: 'top' } })
		api.getOrgNavigation.mockResolvedValue({ data: { tree: [{ id: 'n2' }], language: 'nl' } })
		const store = useOrgNavigationStore()

		await store.loadFirst()

		expect(api.getOrgNavigationPosition).toHaveBeenCalledTimes(1)
		expect(api.getOrgNavigation).toHaveBeenCalledWith('nl')
		expect(store.position).toBe('top')
		expect(store.tree).toEqual([{ id: 'n2' }])
	})
})
