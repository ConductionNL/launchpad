/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The admin embed API (embedded-analytics): the telemetry switch.
 *
 * @spec openspec/changes/embedded-analytics/tasks.md#task-30
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const baseUrl = generateUrl('/apps/launchpad')

export const embedApi = {
	/**
	 * Whether embed usage telemetry is collected.
	 *
	 * @return {Promise<boolean>}
	 */
	async telemetryEnabled() {
		const { data } = await axios.get(`${baseUrl}/api/embed-settings/telemetry`)
		return data?.enabled !== false
	},

	/**
	 * Turn embed usage telemetry on or off; resolves with the stored state.
	 *
	 * @param {boolean} enabled Whether to collect.
	 * @return {Promise<boolean>}
	 */
	async setTelemetryEnabled(enabled) {
		const { data } = await axios.put(`${baseUrl}/api/embed-settings/telemetry`, { enabled })
		return data?.enabled !== false
	},
}
