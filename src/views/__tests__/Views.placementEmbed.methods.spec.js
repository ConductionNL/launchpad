/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The workspace gives each widget the Beheer ▸ Embeds link for an embed
 * token of that one widget, only for an administrator (embedded-analytics
 * task 40). Run against the real Views method on a stand-in host.
 *
 * @spec openspec/changes/embedded-analytics/tasks.md#task-40
 */

import { beforeAll, describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/router', async (importOriginal) => ({
	...(await importOriginal()),
	generateUrl: (path) => `/index.php${path}`,
}))
vi.mock('../../services/api.js', () => ({ api: {} }))
vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn(), showSuccess: vi.fn() }))

let Views

beforeAll(async () => {
	Views = (await import('../Views.vue')).default
})

const hrefFor = (host, placement) => Views.methods.placementEmbedHref.call(host, placement)
const dashboard = { id: 7, uuid: 'dash-uuid' }

describe('Views placementEmbedHref', () => {
	it('links an administrator to a widget token for the placement', () => {
		expect(hrefFor({ isAdmin: true, activeDashboard: dashboard }, { id: 5 }))
			.toBe('/index.php/apps/launchpad/admin/embeds?tab=embeds&embedType=widget&embedId=5&embedDashboard=7')
	})

	it('gives nothing to a non-administrator or without a saved dashboard and placement', () => {
		expect(hrefFor({ isAdmin: false, activeDashboard: dashboard }, { id: 5 })).toBe('')
		expect(hrefFor({ isAdmin: null, activeDashboard: dashboard }, { id: 5 })).toBe('')
		expect(hrefFor({ isAdmin: true, activeDashboard: null }, { id: 5 })).toBe('')
		expect(hrefFor({ isAdmin: true, activeDashboard: dashboard }, {})).toBe('')
	})
})
