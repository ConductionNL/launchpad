/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A dashboard switch starts the target's reactions and (when somebody else
 * owns it) personal-layer reads beside the dashboard read (REQ-INIT-012).
 *
 * @spec openspec/changes/archive/2026-11-27-dashboard-switch-reads-early/tasks.md#task-3
 */
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '../../services/api.js'
import { dropEarlyReads, startEarlyRead } from '../../utils/carriedApiReads.js'
import { startSwitchReads, useDashboardStore } from '../dashboard.js'

vi.mock('@nextcloud/router', () => ({
	generateUrl: (path) => `/index.php${path}`,
}))

vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn(), showSuccess: vi.fn() }))

vi.mock('../../utils/logger.js', () => ({
	logger: { error: vi.fn(), warn: vi.fn(), info: vi.fn(), debug: vi.fn() },
}))

vi.mock('../../utils/carriedApiReads.js', () => ({
	dropEarlyReads: vi.fn(),
	startEarlyRead: vi.fn(),
}))

vi.mock('../../services/api.js', () => ({
	api: {
		getDashboardById: vi.fn(),
		activateDashboard: vi.fn(),
		setActiveDashboardPreference: vi.fn(),
	},
}))

const BASE = '/index.php/apps/launchpad/api/dashboards'

beforeEach(() => {
	setActivePinia(createPinia())
	vi.clearAllMocks()
})

describe('startSwitchReads', () => {
	it('starts the reactions and the personal layer of a dashboard somebody else owns', () => {
		startSwitchReads({ id: 7, uuid: 'u-7', source: 'group', isOwner: false })

		expect(dropEarlyReads).toHaveBeenCalledTimes(1)
		expect(startEarlyRead.mock.calls.map(([url]) => url)).toEqual([
			`${BASE}/u-7/reactions`,
			`${BASE}/7/personal-layer`,
		])
	})

	it('starts only the reactions of an own dashboard', () => {
		startSwitchReads({ id: 3, uuid: 'u-3', source: 'user', isOwner: true })

		expect(startEarlyRead.mock.calls.map(([url]) => url)).toEqual([`${BASE}/u-3/reactions`])
	})

	it('starts nothing for an unknown target or one without a uuid, but still drops the last switch\'s reads', () => {
		startSwitchReads(undefined)
		startSwitchReads({ id: 4, source: 'user' })

		expect(dropEarlyReads).toHaveBeenCalledTimes(2)
		expect(startEarlyRead).not.toHaveBeenCalled()
	})
})

describe('switchDashboard', () => {
	it('starts the follow-up reads before the dashboard read answers', async () => {
		const store = useDashboardStore()
		store.dashboards = [{ id: 7, uuid: 'u-7', source: 'group', isOwner: false }]
		let answer
		api.getDashboardById.mockReturnValue(new Promise((resolve) => { answer = resolve }))
		api.setActiveDashboardPreference.mockResolvedValue({})

		const switching = store.switchDashboard(7)
		expect(startEarlyRead).toHaveBeenCalledTimes(2)
		expect(api.activateDashboard).not.toHaveBeenCalled()

		answer({ data: { dashboard: { id: 7, uuid: 'u-7' }, placements: [], isOwner: false, permissionLevel: 'view' } })
		await switching
		expect(store.activeDashboard.uuid).toBe('u-7')
	})
})
