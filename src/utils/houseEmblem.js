/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

import { getCapabilities } from '@nextcloud/capabilities'

/**
 * The emblem of the house style thematiq publishes, else Nextcloud's logo,
 * else '' (a page without capabilities, such as a test).
 *
 * @return {string} The image address.
 */
export function houseEmblem() {
	try {
		const capabilities = getCapabilities() || {}
		return capabilities.nldesign?.logos?.emblem || capabilities.theming?.logo || ''
	} catch {
		return ''
	}
}
