/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The communal CnAddWidgetModal names a type from the SHARED registry (its
 * type select), not from LaunchPad's decorated copy, so the shared entry must
 * carry the localised name too (board LpWidgetInstellen: a Dutch dialog said
 * "Live-data tile").
 */

import { describe, expect, it, vi } from 'vitest'

describe('widgetRegistry — shared registry names are localised', () => {
	it('writes the translated displayName into the shared entry of every offered type', async () => {
		vi.resetModules()
		globalThis.t = (_app, key) => (key === 'Live-data tile' ? 'Live gegevens' : key)
		const { listWidgetTypes, widgetRegistry } = await import('../widgetRegistry.js')
		const shared = (await import('@conduction/nextcloud-vue')).dashboardWidgetRegistry
		expect(shared.livetile.displayName).toBe('Live gegevens')
		expect(widgetRegistry.livetile.displayName).toBe('Live gegevens')
		for (const type of listWidgetTypes()) {
			expect(shared[type].displayName, type).toBe(widgetRegistry[type].displayName)
		}
	})
})

describe('widgetRegistry — the live-data form owns its title', () => {
	it('declares ownsTitle so the modal does not repeat Title and Show title', async () => {
		const { widgetRegistry } = await import('../widgetRegistry.js')
		expect(widgetRegistry.livetile.ownsTitle).toBe(true)
	})
})
