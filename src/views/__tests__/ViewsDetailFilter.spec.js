/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Filtering the switcher by a detail asks the visible list with
 * `metadata[<key>]` and keeps what it returns (REQ-MDUI-003).
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '../../services/api.js'

vi.mock('../../services/api.js', () => ({
	api: { getVisibleDashboards: vi.fn(), getMetadataFieldDefinitions: vi.fn() },
}))

let Views

beforeEach(async () => {
	vi.clearAllMocks()
	Views = (await import('../Views.vue')).default
})

describe('Views detail filter', () => {
	it('REQ-MDUI-003: filtering to Finance keeps Payroll only, and clearing shows all', async () => {
		api.getVisibleDashboards.mockResolvedValue({
			data: [{ uuid: 'payroll', name: 'Payroll' }],
		})
		const host = { detailFilterUuids: null }
		for (const [name, fn] of Object.entries(Views.methods)) {
			host[name] = fn.bind(host)
		}
		const list = [
			{ uuid: 'payroll', name: 'Payroll' },
			{ uuid: 'onb', name: 'Onboarding' },
		]
		await host.onDetailFilter({ department: 'Finance' })
		expect(api.getVisibleDashboards).toHaveBeenCalledWith({
			department: 'Finance',
		})
		expect(host.applyDetailFilter(list).map((d) => d.name)).toEqual(['Payroll'])
		await host.onDetailFilter(null)
		expect(host.applyDetailFilter(list)).toHaveLength(2)
	})
})
