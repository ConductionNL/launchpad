/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The workspace page offers "Hide for me" on a shared dashboard only, saves
 * through the personal layer and re-reads the dashboard so the server
 * applies it (dashboards-personal-hide-ui REQ-PERSUI-001, REQ-PERSUI-002).
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn(), showSuccess: vi.fn() }))

let Views
let showError

/**
 * @param {object} dashboard Active dashboard.
 * @param {object} layer Personal-layer store double.
 * @return {object} host with the real Views methods.
 */
function makeHost(dashboard, layer) {
	const host = {
		activeDashboard: dashboard,
		availableWidgets: [],
		personalLayer: layer,
	}
	for (const [name, fn] of Object.entries(Views.methods)) {
		host[name] = fn.bind(host)
	}
	host.switchDashboard = vi.fn()
	Object.defineProperty(host, 'canHideForMe', {
		get: () => Views.computed.canHideForMe.call(host),
	})
	return host
}

beforeEach(async () => {
	vi.clearAllMocks()
	Views = (await import('../Views.vue')).default
	showError = (await import('@nextcloud/dialogs')).showError
})

describe('Views personal hide', () => {
	it('REQ-PERSUI-001: offered on a shared dashboard, not on your own', () => {
		expect(makeHost({ id: 1, isOwner: false }, {}).canHideForMe).toBe(true)
		expect(makeHost({ id: 1, isOwner: true }, {}).canHideForMe).toBe(false)
	})

	it('REQ-PERSUI-001: hiding saves the layer and re-reads the dashboard', async () => {
		const layer = { hide: vi.fn().mockResolvedValue() }
		const host = makeHost({ id: 4, isOwner: false }, layer)
		await host.onHideForMe({ id: 9, widgetId: 'weather' })
		expect(layer.hide).toHaveBeenCalledWith(4, 9)
		expect(host.switchDashboard).toHaveBeenCalledWith(4)
	})

	it('REQ-PERSUI-001: a compulsory refusal names the widget and changes nothing', async () => {
		const layer = {
			hide: vi.fn().mockRejectedValue({ compulsory: true, placementId: 9 }),
		}
		const host = makeHost({ id: 4, isOwner: false }, layer)
		await host.onHideForMe({
			id: 9,
			widgetId: 'text',
			customTitle: 'Safety notice',
		})
		expect(showError).toHaveBeenCalledWith(
			expect.stringContaining('Safety notice'),
		)
		expect(host.switchDashboard).not.toHaveBeenCalled()
	})

	it('REQ-PERSUI-002: show again and reset go through the layer and re-read', async () => {
		const layer = {
			showAgain: vi.fn().mockResolvedValue(),
			reset: vi.fn().mockResolvedValue(),
		}
		const host = makeHost({ id: 4, isOwner: false }, layer)
		await host.onShowAgain(9)
		expect(layer.showAgain).toHaveBeenCalledWith(4, 9)
		await host.onResetPersonalView()
		expect(layer.reset).toHaveBeenCalledWith(4)
		expect(host.switchDashboard).toHaveBeenCalledTimes(2)
	})
})
