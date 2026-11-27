/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The screen link of one dashboard (REQ-KIOSK-006): read, create and revoke.
 *
 * @spec openspec/changes/dashboard-show-on-a-screen/tasks.md#task-5
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const linkUrl = (uuid) => generateUrl('/apps/launchpad/api/dashboards/{uuid}/screen-link', { uuid })

export const screenLinkApi = {
	/**
	 * The dashboard's active screen link, or null when it has none (204).
	 *
	 * @param {string} uuid The dashboard UUID.
	 * @return {Promise<object|null>}
	 * @spec openspec/changes/dashboard-show-on-a-screen/tasks.md#task-5
	 */
	async get(uuid) {
		const response = await axios.get(linkUrl(uuid))
		if (response.status === 204 || !response.data?.token) {
			return null
		}
		return response.data
	},

	/**
	 * Create the screen link, or get the existing one.
	 *
	 * @param {string} uuid The dashboard UUID.
	 * @return {Promise<object>}
	 * @spec openspec/changes/dashboard-show-on-a-screen/tasks.md#task-5
	 */
	async create(uuid) {
		const { data } = await axios.post(linkUrl(uuid))
		return data
	},

	/**
	 * Revoke the screen link.
	 *
	 * @param {string} uuid The dashboard UUID.
	 * @return {Promise<void>}
	 * @spec openspec/changes/dashboard-show-on-a-screen/tasks.md#task-5
	 */
	async revoke(uuid) {
		await axios.delete(linkUrl(uuid))
	},
}
