/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The workspace page's tree actions (dashboard-tree-navigation): deleting a
 * parent says how many go with it (409 `dashboard_has_children` with
 * `childCount`, then `cascade`), a refused parent or web address name is
 * explained (REQ-TREEUI-003, DashboardTreeService::ERR_* messages), and a
 * breadcrumb opens its dashboard (REQ-TREEUI-002).
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '../../services/api.js'

vi.mock('../../services/api.js', () => ({
	api: { deleteDashboard: vi.fn(), updateDashboard: vi.fn() },
}))

let Views

/**
 * @return {object} host with the real Views methods.
 */
function makeHost() {
	const host = {
		dashboards: [
			{ id: 1, uuid: 'hr', name: 'HR', source: 'group' },
			{
				id: 2,
				uuid: 'onb',
				name: 'Onboarding',
				source: 'group',
				parentUuid: 'hr',
			},
		],
		deleteTarget: null,
		deleteChildCount: 0,
		configSaveError: '',
		isConfigModalOpen: true,
	}
	for (const [name, fn] of Object.entries(Views.methods)) {
		host[name] = fn.bind(host)
	}
	host.loadDashboards = vi.fn()
	host.createDashboard = vi.fn()
	host.switchDashboard = vi.fn()
	return host
}

function refused(status, data) {
	return Object.assign(new Error('x'), { response: { status, data } })
}

beforeEach(async () => {
	vi.clearAllMocks()
	Views = (await import('../Views.vue')).default
})

describe('Views tree actions', () => {
	it('deleting a parent asks again with the number of dashboards under it, then cascades', async () => {
		api.deleteDashboard.mockRejectedValueOnce(
			refused(409, {
				status: 'error',
				error: 'dashboard_has_children',
				message: 'has children',
				childCount: 2,
			}),
		)
		api.deleteDashboard.mockResolvedValueOnce({ data: { status: 'ok' } })
		const host = makeHost()
		await host.onSidebarDeleteDashboard(1)
		expect(host.deleteTarget.name).toBe('HR')
		await host.confirmDeleteDashboard()
		expect(api.deleteDashboard).toHaveBeenLastCalledWith(1, false)
		expect(host.deleteChildCount).toBe(2)
		expect(host.deleteTarget).not.toBeNull()
		await host.confirmDeleteDashboard()
		expect(api.deleteDashboard).toHaveBeenLastCalledWith(1, true)
		expect(host.deleteTarget).toBeNull()
		expect(host.loadDashboards).toHaveBeenCalled()
	})

	it('REQ-TREEUI-003: a cycle is explained and the dialog stays open', async () => {
		api.updateDashboard.mockRejectedValue(
			refused(400, {
				status: 'error',
				error: 'invalid_argument',
				message: 'Setting this parent would create a cycle',
			}),
		)
		const host = makeHost()
		await host.saveDashboardConfig({
			id: 1,
			name: 'HR',
			description: '',
			icon: null,
			parentUuid: 'onb',
			slug: 'hr',
		})
		expect(api.updateDashboard).toHaveBeenCalledWith(1, {
			name: 'HR',
			description: '',
			icon: null,
			parentUuid: 'onb',
			slug: 'hr',
		})
		expect(host.configSaveError).toContain('cannot be its parent')
		expect(host.isConfigModalOpen).toBe(true)
	})

	it('REQ-TREEUI-003: a taken web address name is explained', async () => {
		api.updateDashboard.mockRejectedValue(
			refused(400, {
				error: 'invalid_argument',
				message: 'Slug must be unique among siblings',
			}),
		)
		const host = makeHost()
		await host.saveDashboardConfig({
			id: 2,
			name: 'Onboarding',
			description: '',
			icon: null,
			parentUuid: 'hr',
			slug: 'x',
		})
		expect(host.configSaveError).toContain('already uses this web address name')
	})

	it('REQ-TREEUI-002: a breadcrumb opens that dashboard', async () => {
		const host = makeHost()
		await host.onBreadcrumbNavigate('hr')
		expect(host.switchDashboard).toHaveBeenCalledWith(1)
	})
})
