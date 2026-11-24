/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

import { getCapabilities } from '@nextcloud/capabilities'

/**
 * The name of the theme this instance uses: the active thematiq theme when
 * thematiq publishes one, else Nextcloud's theming name, else '' (a page
 * without capabilities, such as a test).
 *
 * @return {string} The theme name.
 * @spec openspec/changes/dashboard-theme-readonly/specs/dashboards/spec.md#requirement-the-details-tab-shows-the-instance-theme-read-only-req-thm-004
 */
export function instanceThemeName() {
	try {
		const capabilities = getCapabilities() || {}
		const thematiq = capabilities.nldesign?.tokenSet?.name
		if (typeof thematiq === 'string' && thematiq.trim() !== '') {
			return thematiq.trim()
		}
		const theming = capabilities.theming?.name
		return typeof theming === 'string' ? theming.trim() : ''
	} catch {
		return ''
	}
}
