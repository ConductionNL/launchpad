/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * runtime-shell REQ-SHELL-011: the admin settings page's own first-visit tour.
 */

import { __resetWalkthroughCacheForTests, useWalkthrough, WALKTHROUGH_SEEN_STORAGE_PREFIX } from '@conduction/nextcloud-vue'
import { afterEach, describe, expect, it, vi } from 'vitest'
import manifest from '../../manifest.json'
import {
	ADMIN_TOUR_COMPLETION_KEY,
	ADMIN_TOUR_ID,
	ADMIN_TOUR_STORAGE_SUFFIX,
	adminTourManifest,
	adminTourStorage,
	loadAdminTourSeen,
	persistAdminTourSeen,
} from '../adminWalkthrough.js'

// The suite aliases the library to a stub; this spec runs the library's own
// walkthrough engine and persistence, with only HTTP and storage injected.
vi.mock('@conduction/nextcloud-vue', () => import('../../../node_modules/@conduction/nextcloud-vue/src/composables/useWalkthrough.js'))

/**
 * A Map-backed Storage for one test.
 *
 * @return {Storage}
 */
function memoryStorage() {
	const items = new Map()
	return {
		getItem: (key) => (items.has(key) ? items.get(key) : null),
		setItem: (key, value) => items.set(key, String(value)),
		removeItem: (key) => items.delete(key),
		items,
	}
}

afterEach(() => {
	__resetWalkthroughCacheForTests()
})

describe('the admin tour in src/manifest.json', () => {
	const tour = manifest.walkthrough.tours.find((candidate) => candidate.id === ADMIN_TOUR_ID)

	it('declares launchpad:admin-getting-started next to the Workspace tour', () => {
		expect(tour).toBeDefined()
		expect(manifest.walkthrough.tours[0].id).toBe('launchpad:getting-started')
	})

	it('is manual there, so the Workspace shell never starts it', () => {
		expect(tour.trigger).toBe('manual')
		const wt = useWalkthrough('launchpad-shell-test', manifest)
		expect(wt.autoStartTour.value.id).toBe('launchpad:getting-started')
	})

	it('starts on the general settings and walks the templates, roles, sharing and operations tabs', () => {
		expect(tour.steps.map((step) => step.id)).toEqual(['general', 'templates', 'roles', 'sharing', 'operations'])
		expect(tour.steps[0].target).toEqual({ kind: 'element', ref: 'admin-default-settings' })
		for (const step of tour.steps) {
			expect(step.title).not.toBe('')
			expect(step.body).not.toBe('')
		}
	})
})

describe('adminTourManifest', () => {
	it('hands the admin page only the admin tour, as a first-visit tour with its own completion key', () => {
		const admin = adminTourManifest(manifest)

		expect(admin.version).toBe(manifest.version)
		expect(admin.walkthrough.completionConfigKey).toBe(ADMIN_TOUR_COMPLETION_KEY)
		expect(admin.walkthrough.completionConfigKey).not.toBe(manifest.walkthrough.completionConfigKey)
		expect(admin.walkthrough.tours).toHaveLength(1)
		expect(admin.walkthrough.tours[0].id).toBe(ADMIN_TOUR_ID)
		expect(admin.walkthrough.tours[0].trigger).toBe('first-visit')
	})

	it('leaves the manifest itself unchanged', () => {
		adminTourManifest(manifest)

		expect(manifest.walkthrough.tours.find((candidate) => candidate.id === ADMIN_TOUR_ID).trigger).toBe('manual')
	})

	it('answers null without an admin tour or with walkthroughs switched off', () => {
		expect(adminTourManifest({ walkthrough: { tours: [{ id: 'other' }] } })).toBeNull()
		expect(adminTourManifest({ walkthrough: { enabled: false, tours: [{ id: ADMIN_TOUR_ID }] } })).toBeNull()
		expect(adminTourManifest(null)).toBeNull()
	})

	it('starts for an administrator who never finished it, and not for one who did', () => {
		const fresh = useWalkthrough('launchpad-admin-fresh', adminTourManifest(manifest), { seenVersion: '' })
		expect(fresh.autoStartTour.value.id).toBe(ADMIN_TOUR_ID)

		const done = useWalkthrough('launchpad-admin-done', adminTourManifest(manifest), { seenVersion: manifest.version })
		expect(done.autoStartTour.value).toBeNull()
	})
})

describe('adminTourStorage', () => {
	it('keeps the admin mirror under its own key, apart from the Workspace mirror', () => {
		const base = memoryStorage()
		const scoped = adminTourStorage(base)

		scoped.setItem('k', '1')

		expect(base.getItem('k' + ADMIN_TOUR_STORAGE_SUFFIX)).toBe('1')
		expect(base.getItem('k')).toBeNull()
		expect(scoped.getItem('k')).toBe('1')
		scoped.removeItem('k')
		expect(base.getItem('k' + ADMIN_TOUR_STORAGE_SUFFIX)).toBeNull()
	})

	it('keeps nothing without a usable storage', () => {
		const scoped = adminTourStorage(null)

		scoped.setItem('k', '1')

		expect(scoped.getItem('k')).toBeNull()
	})
})

describe('loading and recording the admin tour', () => {
	it('reads the admin preference, not the Workspace one', async () => {
		const http = { get: vi.fn().mockResolvedValue({ data: { value: null } }) }

		await loadAdminTourSeen('launchpad', { http, storage: memoryStorage() })

		expect(http.get).toHaveBeenCalledTimes(1)
		expect(http.get.mock.calls[0][0]).toMatch(/\/apps\/launchpad\/api\/preferences\/admin-walkthrough-completed-version$/)
	})

	it('is unseen for an administrator who finished only the Workspace tour', async () => {
		const storage = memoryStorage()
		storage.setItem(WALKTHROUGH_SEEN_STORAGE_PREFIX + 'launchpad', '0.2.0')
		const http = { get: vi.fn().mockResolvedValue({ data: { value: null } }) }

		expect(await loadAdminTourSeen('launchpad', { http, storage })).toBe('')
	})

	it('answers the version the server preference records', async () => {
		const http = { get: vi.fn().mockResolvedValue({ data: { value: '0.2.0' } }) }

		expect(await loadAdminTourSeen('launchpad', { http, storage: memoryStorage() })).toBe('0.2.0')
	})

	it('records the finished tour in the admin preference and the admin mirror only', async () => {
		const storage = memoryStorage()
		const http = { put: vi.fn().mockResolvedValue({}) }

		expect(await persistAdminTourSeen('launchpad', '0.2.0', { http, storage })).toBe(true)

		expect(http.put).toHaveBeenCalledTimes(1)
		expect(http.put.mock.calls[0][0]).toMatch(/\/api\/preferences\/admin-walkthrough-completed-version$/)
		expect(http.put.mock.calls[0][1]).toEqual({ value: '0.2.0' })
		expect(storage.getItem(WALKTHROUGH_SEEN_STORAGE_PREFIX + 'launchpad')).toBeNull()
		expect(storage.getItem(WALKTHROUGH_SEEN_STORAGE_PREFIX + 'launchpad' + ADMIN_TOUR_STORAGE_SUFFIX)).toBe('0.2.0')
	})
})
