/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * New dashboard at the foot of More's full Dashboards list (REQ-SHELL-015):
 * offered only with personal dashboards allowed and the quota not reached,
 * administrators included, and it creates "My workspace".
 *
 * @spec openspec/changes/archive/2026-11-28-more-menu-favourites/tasks.md#task-3
 */

import { createPinia, setActivePinia } from 'pinia'
import { beforeAll, describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn(), showSuccess: vi.fn() }))

let Views

beforeAll(async () => {
	setActivePinia(createPinia())
	Views = (await import('../Views.vue')).default
}, 60_000)

describe('Views New dashboard', () => {
	const canCreate = (state) => Views.computed.canCreatePersonalDashboard.call(state)

	it('is offered with personal dashboards on and the quota not reached', () => {
		expect(canCreate({ allowUserDashboards: true, dashboardQuotaReached: false })).toBe(true)
	})

	it('is not offered with personal dashboards off, also not to an administrator', () => {
		expect(canCreate({ allowUserDashboards: false, isAdmin: true, dashboardQuotaReached: false })).toBe(false)
	})

	it('is not offered once the quota is reached', () => {
		expect(canCreate({ allowUserDashboards: true, dashboardQuotaReached: true })).toBe(false)
	})

	it('creates "My workspace", and a refusal the store already reported stays quiet', async () => {
		const createDashboard = vi.fn().mockResolvedValue(undefined)
		await Views.methods.createWorkspaceDashboard.call({ createDashboard })
		expect(createDashboard).toHaveBeenCalledWith('My workspace')
		const refused = vi.fn().mockRejectedValue(new Error('403'))
		await expect(Views.methods.createWorkspaceDashboard.call({ createDashboard: refused })).resolves.toBeUndefined()
	})
})
