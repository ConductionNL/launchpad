/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The strip button of one admin settings tab, found by the tab's id.
 *
 * The admin page draws its tabs with the library's CnAdminSettingsShell. Each
 * panel holds a `[data-tab="<id>"]` element, and the strip button names its
 * panel in `aria-controls`, so the id finds the button whatever the locale.
 */

import type { Locator, Page } from '@playwright/test'

/**
 * @param page The admin settings page.
 * @param id The tab id, as in `AdminSettings.vue`'s `beheerTabs`.
 * @return The tab's strip button.
 */
export function adminTab(page: Page, id: string): Locator {
	return page.locator(
		`xpath=//button[@role="tab"][@aria-controls=//*[*[@data-tab="${id}"]]/@id]`,
	)
}
