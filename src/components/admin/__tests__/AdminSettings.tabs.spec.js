/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The admin page opens on Algemeen (board LpBeheerAlgemeen): the setup
 * banner, the defaults and group-shared dashboards live in that first tab
 * instead of above the tab strip.
 */

import { readFileSync } from 'fs'
import { join } from 'path'
import { describe, expect, it } from 'vitest'
import AdminSettings from '../AdminSettings.vue'

const ctx = { t: (_app, text) => text }

describe('AdminSettings tabs', () => {
	it('puts General first', () => {
		const tabs = AdminSettings.computed.beheerTabs.call(ctx)
		expect(tabs[0]).toEqual({ slug: 'general', label: 'General' })
		expect(tabs[1].slug).toBe('templates')
	})

	it('opens on General', () => {
		const render = AdminSettings.render?.toString() ?? ''
		expect(render).toContain('defaultTab: "general"')
	})

	it('draws the defaults inside the General slot', () => {
		const source = readFileSync(join(__dirname, '../AdminSettings.vue'), 'utf8')
		const general = source.indexOf('<template #general>')
		const defaults = source.indexOf('data-testid="admin-default-settings"')
		const templates = source.indexOf('<template #templates>')
		expect(general).toBeGreaterThan(-1)
		expect(defaults).toBeGreaterThan(general)
		expect(templates).toBeGreaterThan(defaults)
	})
})
