/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A slug address opens the dashboard it names even though the router's
 * catch-all has already redirected the address to `/` when the workspace
 * page is created (REQ-DDL-002).
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('../../services/api.js', () => ({
	api: { getDefaultDashboardPreference: vi.fn(async () => ({ data: { uuid: '' } })) },
}))
vi.mock('../../stores/dashboard.js', () => ({
	useDashboardStore: () => ({
		loadDashboards: vi.fn(async () => {}),
		fetchPendingAcknowledgements: vi.fn(),
	}),
}))
vi.mock('../../stores/widgets.js', () => ({
	useWidgetStore: () => ({ loadAvailableWidgets: vi.fn(async () => {}) }),
}))
vi.mock('../../stores/tiles.js', () => ({
	useTileStore: () => ({ loadTiles: vi.fn(async () => {}) }),
}))
vi.mock('../../utils/logger.js', () => ({
	logger: { debug: vi.fn(), info: vi.fn(), warn: vi.fn(), error: vi.fn() },
}))

beforeEach(() => {
	vi.resetModules()
})

describe('Views boot address', () => {
	it('hands the deep-link check the address the page was opened on, not the redirected one', async () => {
		window.history.replaceState(null, '', '/index.php/apps/launchpad/team/a')
		const Views = (await import('../Views.vue')).default
		// The router's catch-all redirect, before the route component exists.
		window.history.replaceState(null, '', '/index.php/apps/launchpad/')

		const openDeepLinkedDashboard = vi.fn(async () => {})
		const host = { grid: {}, canEdit: false, openDeepLinkedDashboard }
		await Views.created.call(host)

		expect(openDeepLinkedDashboard).toHaveBeenCalledWith(
			'/index.php/apps/launchpad/team/a',
		)
	})
})
