/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for the role-feature permission store: loading the
 * permissions and layout defaults (wrapped or bare payloads), saving one
 * (replacing the entry for the same group, or the same group and widget),
 * deleting one, and the error and busy flags around every call.
 */

import axios from '@nextcloud/axios'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useRoleFeaturePermissionStore } from '../roleFeaturePermissions.js'

vi.mock('@nextcloud/axios', () => ({
	default: { get: vi.fn(), post: vi.fn(), delete: vi.fn() },
}))

vi.mock('@nextcloud/router', () => ({
	generateUrl: (path) => `/index.php${path}`,
}))

const PERMS = '/index.php/apps/launchpad/api/role-feature-permissions'
const DEFAULTS = '/index.php/apps/launchpad/api/role-layout-defaults'

describe('roleFeaturePermissions store', () => {
	let store

	beforeEach(() => {
		vi.clearAllMocks()
		setActivePinia(createPinia())
		store = useRoleFeaturePermissionStore()
	})

	it('loads permissions from a wrapped or bare payload, and nothing from a non-list', async () => {
		axios.get.mockResolvedValueOnce({ data: { data: [{ id: 1 }] } })
		await store.loadPermissions()
		expect(store.permissions).toEqual([{ id: 1 }])

		axios.get.mockResolvedValueOnce({ data: [{ id: 2 }] })
		await store.loadPermissions()
		expect(store.permissions).toEqual([{ id: 2 }])

		axios.get.mockResolvedValueOnce({ data: { data: 'nope' } })
		await store.loadPermissions()
		expect(store.permissions).toEqual([])
		expect(axios.get).toHaveBeenCalledWith(PERMS)
		expect(store.loading).toBe(false)
	})

	it('loads layout defaults the same way', async () => {
		axios.get.mockResolvedValueOnce({ data: [{ id: 'd1' }] })
		await store.loadLayoutDefaults()
		expect(axios.get).toHaveBeenCalledWith(DEFAULTS)
		expect(store.layoutDefaults).toEqual([{ id: 'd1' }])
	})

	it('records the error of a failed load and rethrows it', async () => {
		axios.get.mockRejectedValueOnce(new Error('offline'))
		await expect(store.loadPermissions()).rejects.toThrow('offline')
		expect([store.error, store.loading]).toEqual(['offline', false])

		axios.get.mockRejectedValueOnce({})
		await expect(store.loadLayoutDefaults()).rejects.toEqual({})
		expect(store.error).toBe('Failed to load role-layout defaults')
	})

	it('saves a permission, replacing the entry of the same group', async () => {
		store.permissions = [{ id: 1, groupId: 'admin', features: [] }, { id: 2, groupId: 'staff' }]
		axios.post.mockResolvedValueOnce({ data: { data: { id: 1, groupId: 'admin', features: ['x'] } } })
		axios.post.mockResolvedValueOnce({ data: { id: 3, groupId: 'guests' } })

		const saved = await store.savePermission({ groupId: 'admin', features: ['x'] })
		await store.savePermission({ groupId: 'guests' })

		expect(saved).toEqual({ id: 1, groupId: 'admin', features: ['x'] })
		expect(axios.post).toHaveBeenCalledWith(PERMS, { groupId: 'admin', features: ['x'] })
		expect(store.permissions.map((p) => [p.id, p.groupId])).toEqual([[1, 'admin'], [2, 'staff'], [3, 'guests']])
		expect(store.permissions[0].features).toEqual(['x'])
		expect(store.saving).toBe(false)
	})

	it('saves a layout default keyed by group and widget', async () => {
		store.layoutDefaults = [{ id: 'a', groupId: 'g', widgetId: 'w1', x: 0 }]
		axios.post.mockResolvedValueOnce({ data: { id: 'a', groupId: 'g', widgetId: 'w1', x: 4 } })
		axios.post.mockResolvedValueOnce({ data: { id: 'b', groupId: 'g', widgetId: 'w2' } })

		await store.saveLayoutDefault({ groupId: 'g', widgetId: 'w1', x: 4 })
		await store.saveLayoutDefault({ groupId: 'g', widgetId: 'w2' })

		expect(axios.post).toHaveBeenCalledWith(DEFAULTS, { groupId: 'g', widgetId: 'w1', x: 4 })
		expect(store.layoutDefaults).toEqual([
			{ id: 'a', groupId: 'g', widgetId: 'w1', x: 4 },
			{ id: 'b', groupId: 'g', widgetId: 'w2' },
		])
	})

	it('deletes permissions and layout defaults by id', async () => {
		axios.delete.mockResolvedValue({})
		store.permissions = [{ id: 1 }, { id: 2 }]
		store.layoutDefaults = [{ id: 'a' }, { id: 'b' }]

		await store.deletePermission(1)
		await store.deleteLayoutDefault('b')

		expect(axios.delete.mock.calls).toEqual([[`${PERMS}/1`], [`${DEFAULTS}/b`]])
		expect(store.permissions).toEqual([{ id: 2 }])
		expect(store.layoutDefaults).toEqual([{ id: 'a' }])
	})

	it('keeps the lists and records the error when a write fails', async () => {
		store.permissions = [{ id: 1 }]
		store.layoutDefaults = [{ id: 'a' }]
		const cases = [
			[() => store.savePermission({}), axios.post, 'Failed to save permission'],
			[() => store.deletePermission(1), axios.delete, 'Failed to delete permission'],
			[() => store.saveLayoutDefault({}), axios.post, 'Failed to save layout default'],
			[() => store.deleteLayoutDefault('a'), axios.delete, 'Failed to delete layout default'],
		]

		for (const [call, method, message] of cases) {
			method.mockRejectedValueOnce({})
			await expect(call()).rejects.toEqual({})
			expect([store.error, store.saving]).toEqual([message, false])
		}

		axios.post.mockRejectedValueOnce(new Error('conflict'))
		await expect(store.savePermission({})).rejects.toThrow('conflict')
		expect(store.error).toBe('conflict')
		expect(store.permissions).toEqual([{ id: 1 }])
		expect(store.layoutDefaults).toEqual([{ id: 'a' }])
	})
})
