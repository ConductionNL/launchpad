/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Client for the announcements API (engagement-announcements): the list a
 * reader sees, likes and comments, followed categories, authoring with the
 * preview's reach count, and the notices a reader dismissed, kept in the
 * per-user preference `dismissed-notices`.
 *
 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const base = () => generateUrl('/apps/launchpad/api/announcements')
const DISMISSED_KEY = 'dismissed-notices'

export const announcementsApi = {
	/** @return {Promise<object>} `{announcements, drafts, followedCategories, canAuthor, reactions}`. */
	async list() {
		const { data } = await axios.get(base())
		return data
	},

	/** @param {string} uuid The announcement. @return {Promise<object>} `{likes, liked}`. */
	async like(uuid) {
		return (await axios.post(`${base()}/${uuid}/like`)).data
	},

	/** @param {string} uuid The announcement. @return {Promise<object>} `{likes, liked}`. */
	async unlike(uuid) {
		return (await axios.delete(`${base()}/${uuid}/like`)).data
	},

	/** @param {string} uuid The announcement. @return {Promise<Array>} The comments, oldest first. */
	async comments(uuid) {
		return (await axios.get(`${base()}/${uuid}/comments`)).data.comments ?? []
	},

	/**
	 * @param {string} uuid The announcement. @param {string} message The text. @return {Promise<object>} The new comment.
	 * @param message
	 */
	async comment(uuid, message) {
		return (await axios.post(`${base()}/${uuid}/comments`, { message })).data
	},

	/** @param {string} category The category. @return {Promise<Array<string>>} The followed categories. */
	async follow(category) {
		return (await axios.post(`${base()}/follows`, { category })).data.followedCategories ?? []
	},

	/** @param {string} category The category. @return {Promise<Array<string>>} The followed categories. */
	async unfollow(category) {
		return (await axios.delete(`${base()}/follows/${encodeURIComponent(category)}`)).data.followedCategories ?? []
	},

	/** @param {object} input The author fields. @return {Promise<object>} The draft. */
	async create(input) {
		return (await axios.post(base(), input)).data
	},

	/**
	 * @param {string} uuid The announcement. @param {object} input The author fields. @return {Promise<object>} The announcement.
	 * @param input
	 */
	async update(uuid, input) {
		return (await axios.put(`${base()}/${uuid}`, input)).data
	},

	/** @param {string} uuid The announcement. @return {Promise<object>} The published announcement. */
	async publish(uuid) {
		return (await axios.post(`${base()}/${uuid}/publish`)).data
	},

	/** @param {Array<string>} groups The target groups. @return {Promise<number>} How many people they reach. */
	async reach(groups) {
		return (await axios.get(`${base()}/reach`, { params: { groups } })).data.reach ?? 0
	},

	/**
	 * "Preview as": the reach and whether the targeting reaches one person.
	 *
	 * @param {Array<string>} groups The target groups.
	 * @param {string} user The person.
	 * @return {Promise<object>} `{reach, reachesUser}`.
	 */
	async reachFor(groups, user) {
		return (await axios.get(`${base()}/reach`, { params: { groups, user } })).data
	},

	/** @return {Promise<Array<string>>} The uuids of the notices this reader dismissed. */
	async dismissedNotices() {
		const { data } = await axios.get(generateUrl(`/apps/launchpad/api/preferences/${DISMISSED_KEY}`))
		try {
			const parsed = JSON.parse(data?.value ?? '[]')
			return Array.isArray(parsed) ? parsed.filter((uuid) => typeof uuid === 'string') : []
		} catch {
			return []
		}
	},

	/** @param {Array<string>} uuids The dismissed notices to store. @return {Promise<void>} */
	async saveDismissedNotices(uuids) {
		await axios.put(generateUrl(`/apps/launchpad/api/preferences/${DISMISSED_KEY}`), { value: JSON.stringify(uuids) })
	},
}
