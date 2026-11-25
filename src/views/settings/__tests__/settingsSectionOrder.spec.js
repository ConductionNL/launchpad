/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * personal-settings REQ-PSET-001: the app's own sections of the settings
 * dialog follow board LpPersoonlijk (Samenvatting per e-mail, Gebruikscijfers,
 * Verborgen widgets, Mijn profiel), then the library's credentials and the
 * rest. Read from App.vue's source, so the test does not mount the whole app.
 */

import { describe, expect, it } from 'vitest'
import appSource from '../../../App.vue?raw'

/**
 * The component tags inside the #user-settings-sections slot, in order.
 *
 * @return {string[]} The tag names.
 */
function slotSections() {
	const slot = appSource.match(/<template #user-settings-sections>([\s\S]*?)<\/template>/)
	expect(slot).not.toBeNull()
	return [...slot[1].matchAll(/<([A-Z][A-Za-z]+)\s*\/>/g)].map((match) => match[1])
}

describe('settings dialog section order', () => {
	it('puts the app sections in the board order', () => {
		expect(slotSections()).toEqual([
			'DigestSettings',
			'AnalyticsOptOutSettings',
			'HiddenWidgetsSettings',
			'ProfileFieldsSettings',
		])
	})

	it('lets the library draw credentials right after them, then the rest', () => {
		const keys = appSource.match(/userSettingsSections\(\) \{\s*return (\[[^\]]*\])/)
		expect(keys).not.toBeNull()
		expect(JSON.parse(keys[1].replace(/'/g, '"'))).toEqual(['app', 'credentials', 'notifications', 'walkthrough', 'integrations'])
	})

	it('registers no section in Nextcloud personal settings', () => {
		expect(appSource).not.toMatch(/settings\/user/)
	})
})
