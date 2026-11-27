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
	/**
	 * Every announcement for the Announcements tab, managers only (REQ-ANN-008).
	 *
	 * @return {Promise<Array<object>>}
	 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-the-announcements-tab-lists-every-announcement-req-ann-008
	 */
	async manageList() {
		return (await axios.get(`${base()}/manage`)).data.announcements ?? []
	},

	/**
	 * Delete an announcement (REQ-ANN-006).
	 *
	 * @param {string} uuid The announcement.
	 * @return {Promise<object>}
	 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-the-announcement-form-as-lpaankondiging-draws-it-req-ann-006
	 */
	async remove(uuid) {
		return (await axios.delete(`${base()}/${uuid}`)).data
	},

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

	/**
	 * The groups the announcement form offers as the target group.
	 *
	 * @return {Promise<Array<object>>} `{id, displayName}`, sorted by name.
	 * @spec openspec/changes/archive/2026-11-24-announcement-form-board-sync/specs/announcements/spec.md#requirement-the-announcement-form-as-lpaankondiging-draws-it-req-ann-006
	 */
	/**
	 * Which announcements the reader confirmed, and which read
	 * confirmations the reader may open (REQ-ANN-007).
	 *
	 * @return {Promise<{confirmed: Array<string>, receipts: Array<string>}>}
	 * @spec openspec/changes/archive/2026-11-24-announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
	 */
	async readState() {
		return (await axios.get(`${base()}/read-state`)).data
	},

	/**
	 * The check or the x for the signed-in reader: both confirm, once
	 * (REQ-ANN-007).
	 *
	 * @param {string} uuid The announcement.
	 * @param {string} [via] `check` (default) or `x`.
	 * @return {Promise<object>} `{uuid, confirmed, confirmedAt, confirmedVia}`.
	 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
	 */
	async confirmRead(uuid, via = 'check') {
		return (await axios.post(`${base()}/${uuid}/confirm`, { via })).data
	},

	/**
	 * Record the announcements that came into view as seen; sent once,
	 * after render, never blocking it (REQ-ANN-009).
	 *
	 * @param {Array<string>} uuids The announcements on screen.
	 * @return {Promise<Array<string>>} The uuids recorded by this call.
	 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
	 */
	async markSeen(uuids) {
		if (!Array.isArray(uuids) || uuids.length === 0) {
			return []
		}
		return (await axios.post(`${base()}/seen`, { uuids })).data.recorded ?? []
	},

	/**
	 * Seen and confirmed for one announcement, for managers (REQ-ANN-009).
	 *
	 * @param {string} uuid The announcement.
	 * @return {Promise<object>} The report: figures and one row per person.
	 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
	 */
	async receipts(uuid) {
		return (await axios.get(`${base()}/${uuid}/receipts`)).data
	},

	/**
	 * Seen and confirmed per announcement, for the tab's list (REQ-ANN-008).
	 *
	 * @param {Array<string>} uuids The announcements.
	 * @return {Promise<object>} `{<uuid>: {seen, confirmed}}`.
	 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-the-announcements-tab-lists-every-announcement-req-ann-008
	 */
	async receiptCounts(uuids) {
		if (!Array.isArray(uuids) || uuids.length === 0) {
			return {}
		}
		return (await axios.get(`${base()}/receipt-counts`, { params: { uuids } })).data.counts ?? {}
	},

	/**
	 * The widgets that ask readers to confirm, with dashboard, counts and
	 * deadline (REQ-ACK-008); for announcement managers.
	 *
	 * @return {Promise<Array<object>>} `{announcementKey, title, dashboard, deadline, overdue, acknowledgedCount, pendingCount}`.
	 * @spec openspec/changes/announcements-managed-in-settings/specs/dashboard-acknowledgements/spec.md#requirement-an-editor-can-see-who-has-confirmed-req-ack-008
	 */
	async acknowledgedWidgets() {
		return (await axios.get(generateUrl('/apps/launchpad/api/acknowledgements/widgets'))).data.widgets ?? []
	},

	/**
	 * The groups that hold the announcements setting through Nextcloud's admin
	 * delegation, for the Roles tab (announcements-managed-in-settings D1).
	 *
	 * @return {Promise<object>} `{available, groups: [{id, displayName}]}`.
	 * @spec openspec/changes/announcements-managed-in-settings/tasks.md#task-3
	 */
	async delegation() {
		return (await axios.get(`${base()}/delegation`)).data
	},

	/**
	 * Send reminder to everyone who has not confirmed; once a day (REQ-ANN-009).
	 *
	 * @param {string} uuid The announcement.
	 * @return {Promise<object>} `{uuid, sent, remindedAt}`.
	 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
	 */
	async remind(uuid) {
		return (await axios.post(`${base()}/${uuid}/remind`)).data
	},

	async groups() {
		return (await axios.get(`${base()}/groups`)).data.groups ?? []
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
