/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The workspace page offers "Version history" to the owner or an
 * administrator when versioning is supported, and reloads the dashboard
 * after a restore (dashboard-versioning REQ-VERSUI-001, REQ-VERSUI-003).
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
	const host = { activeDashboard: dashboard, isAdmin, versionSupport: {} }
	for (const [name, fn] of Object.entries(Views.methods)) {
		host[name] = fn.bind(host)
	}
	host.switchDashboard = vi.fn()
	Object.defineProperty(host, 'canViewVersionHistory', {
		get: () => Views.computed.canViewVersionHistory.call(host),
	})
	return host
}

beforeEach(async () => {
	vi.clearAllMocks()
	Views = (await import('../Views.vue')).default
})

describe('Views version history gate', () => {
	it('REQ-VERSUI-001: the owner gets the entry once versioning is confirmed', async () => {
		api.listVersions.mockResolvedValue({
			data: { versions: [], modeSupported: true },
		})
		const host = makeHost({ id: 1, uuid: 'u-1', isOwner: true })
		expect(host.canViewVersionHistory).toBe(false)
		await host.checkVersionSupport()
		expect(api.listVersions).toHaveBeenCalledWith('u-1')
		expect(host.canViewVersionHistory).toBe(true)
		await host.checkVersionSupport()
		expect(api.listVersions).toHaveBeenCalledTimes(1)
	})

	it('REQ-VERSUI-001: a colleague who does not own it gets no entry and no request', async () => {
		const host = makeHost({ id: 1, uuid: 'u-1', isOwner: false })
		await host.checkVersionSupport()
		expect(api.listVersions).not.toHaveBeenCalled()
		expect(host.canViewVersionHistory).toBe(false)
	})

	it('REQ-VERSUI-001: an administrator gets the entry on a dashboard they do not own', async () => {
		api.listVersions.mockResolvedValue({
			data: { versions: [], modeSupported: true },
		})
		const host = makeHost({ id: 1, uuid: 'u-1', isOwner: false }, true)
		await host.checkVersionSupport()
		expect(host.canViewVersionHistory).toBe(true)
	})

	it('REQ-VERSUI-001: an unsupported dashboard hides the entry', async () => {
		api.listVersions.mockResolvedValue({
			data: { versions: [], modeSupported: false },
		})
		const host = makeHost({ id: 1, uuid: 'u-1', isOwner: true })
		await host.checkVersionSupport()
		expect(host.canViewVersionHistory).toBe(false)
	})

	it('REQ-VERSUI-003: a restore reloads the dashboard from the server', async () => {
		const host = makeHost({ id: 5, uuid: 'u-5', isOwner: true })
		await host.onVersionRestored()
		expect(host.switchDashboard).toHaveBeenCalledWith(5)
	})
})
