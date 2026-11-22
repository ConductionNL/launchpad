/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The workspace page decides who manages publication and says when the
 * active dashboard goes live or comes down (REQ-SCHEDUI-001). Scheduling
 * itself is in PublicationTab.spec.js.
 */

import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('../../services/api.js', () => ({
	api: {
		scheduleDashboard: vi.fn(),
		publishDashboard: vi.fn(),
		unpublishDashboard: vi.fn(),
	},
}))
vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn(), showSuccess: vi.fn() }))

let Views

/**
 * @param {object} dashboard Active dashboard.
 * @param {boolean} isAdmin Admin flag.
 * @return {object} host
 */
function makeHost(dashboard, isAdmin = false) {
	const host = {
		activeDashboard: dashboard,
		isAdmin,
	}
	for (const [name, fn] of Object.entries(Views.methods)) {
		host[name] = fn.bind(host)
	}
	for (const name of ['canManagePublication', 'publicationNote']) {
		Object.defineProperty(host, name, {
			get: () => Views.computed[name].call(host),
		})
	}
	return host
}

beforeEach(async () => {
	setActivePinia(createPinia())
	vi.clearAllMocks()
	Views = (await import('../Views.vue')).default
})

describe('Views schedule', () => {
	it('REQ-SCHEDUI-001: the owner or an administrator manages publication, others do not', () => {
		expect(makeHost({ uuid: 'd', isOwner: true }).canManagePublication).toBe(
			true,
		)
		expect(
			makeHost({ uuid: 'd', isOwner: false }, true).canManagePublication,
		).toBe(true)
		expect(makeHost({ uuid: 'd', isOwner: false }).canManagePublication).toBe(
			false,
		)
	})

	it('REQ-SCHEDUI-001: says when it goes live and comes down', () => {
		const host = makeHost({
			uuid: 'd',
			isOwner: true,
			publicationStatus: 'scheduled',
			publishAt: '2026-10-01 09:00:00',
			unpublishAt: '2026-10-02 17:00:00',
		})
		expect(host.publicationNote).toContain('Goes live on')
		expect(host.publicationNote).toContain('Comes down on')
	})
})
