/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The workspace page offers the Versions tab of the dashboard settings to
 * the owner or an administrator (board LpVersies; the tab itself says when a
 * dashboard keeps no versions), and reloads the dashboard after a restore
 * (dashboard-versioning REQ-VERSUI-001, REQ-VERSUI-003).
 * The real Views.vue computed and methods run against a small host.
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '../../services/api.js'

vi.mock('../../services/api.js', () => ({
	api: { listVersions: vi.fn() },
}))

let Views

/**
 * @param {object} dashboard Active dashboard.
 * @param {boolean} isAdmin Admin flag.
 * @return {object} host with Views methods and the gate computed.
 */
function makeHost(dashboard, isAdmin = false) {
	const host = { activeDashboard: dashboard, isAdmin }
	for (const [name, fn] of Object.entries(Views.methods)) {
		host[name] = fn.bind(host)
	}
	host.switchDashboard = vi.fn()
	Object.defineProperty(host, 'canManagePublication', {
		get: () => Views.computed.canManagePublication.call(host),
	})
	return host
}

beforeEach(async () => {
	vi.clearAllMocks()
	Views = (await import('../Views.vue')).default
})

describe('Views Versions tab gate', () => {
	it('REQ-VERSUI-001: the owner and an administrator get the Versions tab, without asking the server first', () => {
		expect(makeHost({ id: 1, uuid: 'u-1', isOwner: true }).canManagePublication).toBe(true)
		expect(makeHost({ id: 1, uuid: 'u-1', isOwner: false }, true).canManagePublication).toBe(true)
		expect(api.listVersions).not.toHaveBeenCalled()
	})

	it('REQ-VERSUI-001: a colleague who does not own it, or a dashboard without a uuid, gets no tab', () => {
		expect(makeHost({ id: 1, uuid: 'u-1', isOwner: false }).canManagePublication).toBe(false)
		expect(makeHost({ id: 1, isOwner: true }).canManagePublication).toBe(false)
	})

	it('REQ-VERSUI-003: a restore reloads the dashboard from the server', async () => {
		const host = makeHost({ id: 5, uuid: 'u-5', isOwner: true })
		await host.onVersionRestored()
		expect(host.switchDashboard).toHaveBeenCalledWith(5)
	})
})
