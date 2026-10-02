/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The widget menu's "Read confirmation…" opens the dialog for the selected
 * widget, and saving sends the placement update (REQ-ACK-007).
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'

let Views

beforeEach(async () => {
	vi.clearAllMocks()
	Views = (await import('../Views.vue')).default
})

describe('Views read confirmation', () => {
	it('opens for the selected widget and saves through the placement update', async () => {
		const placement = { id: 5, widgetId: 'text', requiresAcknowledgement: 0 }
		const host = {
			readConfirmationPlacement: null,
			grid: {
				state: { selectedWidget: placement },
				closeContextMenu: vi.fn(),
			},
		}
		for (const [name, fn] of Object.entries(Views.methods)) {
			host[name] = fn.bind(host)
		}
		host.updateWidgetPlacement = vi.fn().mockResolvedValue({})
		host.onWidgetAcknowledged = vi.fn()
		host.openReadConfirmation()
		expect(host.readConfirmationPlacement).toBe(placement)
		const payload = {
			requiresAcknowledgement: 1,
			acknowledgementPrompt: 'Read it',
			acknowledgementDeadline: null,
		}
		await host.saveReadConfirmation(payload)
		expect(host.updateWidgetPlacement).toHaveBeenCalledWith(5, payload)
		expect(host.readConfirmationPlacement).toBeNull()
	})
})
