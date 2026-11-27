/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The row helpers the More menu kept from the removed dashboards sidebar.
 *
 * @spec openspec/changes/more-menu-favourites/tasks.md#task-9
 */

import { describe, expect, it, vi } from 'vitest'
import { embedAdminHref, isOwnedDashboard } from '../dashboardRows.js'

vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => `/index.php${path}` }))

const dashboard = { id: 7, uuid: 'dash-uuid', name: 'Mijn week', isOwner: true }

describe('embedAdminHref', () => {
	it('pre-selects the dashboard, or one widget of it when given a placement', () => {
		expect(embedAdminHref(dashboard, { id: 12 }))
			.toBe('/index.php/settings/admin/launchpad?tab=embeds&embedType=widget&embedId=12&embedDashboard=7#embeds')
		expect(embedAdminHref(dashboard))
			.toBe('/index.php/settings/admin/launchpad?tab=embeds&embedType=dashboard&embedId=dash-uuid&embedDashboard=7#embeds')
	})
})

describe('isOwnedDashboard', () => {
	it('owns every personal row, and another row only when the dashboard says so', () => {
		expect(isOwnedDashboard({}, 'user')).toBe(true)
		expect(isOwnedDashboard({ isOwner: true }, 'group')).toBe(true)
		expect(isOwnedDashboard({ isOwner: false }, 'shared')).toBe(false)
		expect(isOwnedDashboard(null, 'group')).toBe(false)
	})
})
