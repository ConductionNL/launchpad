/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Client for the announcement endpoints (openspec/specs/announcements):
 * the reader's list, likes, comments, follows, authoring, the reach count
 * and the editor-groups setting. Dismissed notices are kept per person in
 * the generic preferences store under `dismissed-notices`.
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const BASE = '/apps/launchpad/api/announcements'
const DISMISSED_KEY = 'dismissed-notices'

/**
 * Build an app URL.
 *
 * @param {string} path Path below /apps/launchpad/api.
 * @return {string} The URL.
 * @spec openspec/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
 */
function url(path) {
	return generateUrl(path)
}

/**
 * The announcements the caller sees now, or with `manage` every one.
 *
 * @param {{kind?: string, manage?: boolean}} options Filter.
 * @return {Promise<{announcements: object[], canAuthor: boolean, following: string[]}>} The list.
 * @spec openspec/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
 */
export async function listAnnouncements({ kind = '', manage = false } = {}) {
	const params = {}
	if (kind) {
		params.kind = kind
	}
	if (manage) {
		params.scope = 'manage'
	}
	const { data } = await axios.get(url(BASE), { params })
	return data
}

/**
 * Create a draft.
 *
 * @param {object} fields The announcement fields.
 * @return {Promise<object>} The draft.
 * @spec openspec/specs/announcements/spec.md#requirement-authors-preview-before-publishing-req-ann-004
 */
export async function createAnnouncement(fields) {
	const { data } = await axios.post(url(BASE), fields)
	return data
}

/**
 * Update an announcement.
 *
 * @param {string} uuid The announcement.
 * @param {object} fields The fields to change.
 * @return {Promise<object>} The announcement.
 * @spec openspec/specs/announcements/spec.md#requirement-authors-preview-before-publishing-req-ann-004
 */
export async function updateAnnouncement(uuid, fields) {
	const { data } = await axios.put(url(`${BASE}/${encodeURIComponent(uuid)}`), fields)
	return data
}

/**
 * Publish an announcement.
 *
 * @param {string} uuid The announcement.
 * @return {Promise<object>} The published announcement.
 * @spec openspec/specs/announcements/spec.md#requirement-authors-preview-before-publishing-req-ann-004
 */
export async function publishAnnouncement(uuid) {
	const { data } = await axios.post(url(`${BASE}/${encodeURIComponent(uuid)}/publish`))
	return data
}

/**
 * How many people the target groups reach, and whether one person is reached.
 *
 * @param {string[]} targetGroups Group ids; empty means everyone.
 * @param {string} previewUserId A user id to check, or empty.
 * @return {Promise<{reach: number, reachesPreviewUser: boolean|null}>} The reach.
 * @spec openspec/specs/announcements/spec.md#requirement-authors-preview-before-publishing-req-ann-004
 */
export async function announcementReach(targetGroups, previewUserId = '') {
	const { data } = await axios.post(url('/apps/launchpad/api/announcement-reach'), { targetGroups, previewUserId })
	return data
}

/**
 * Like or take back a like.
 *
 * @param {string} uuid The announcement.
 * @param {boolean} liked True to like.
 * @return {Promise<object>} The announcement with fresh counts.
 * @spec openspec/specs/announcements/spec.md#requirement-readers-like-and-comment-through-nextcloud-comments-req-ann-002
 */
export async function likeAnnouncement(uuid, liked) {
	const { data } = await axios.put(url(`${BASE}/${encodeURIComponent(uuid)}/like`), { liked })
	return data
}

/**
 * The comments on an announcement.
 *
 * @param {string} uuid The announcement.
 * @return {Promise<object[]>} The comments, oldest first.
 * @spec openspec/specs/announcements/spec.md#requirement-readers-like-and-comment-through-nextcloud-comments-req-ann-002
 */
export async function listAnnouncementComments(uuid) {
	const { data } = await axios.get(url(`${BASE}/${encodeURIComponent(uuid)}/comments`))
	return data.comments || []
}

/**
 * Comment on an announcement.
 *
 * @param {string} uuid The announcement.
 * @param {string} message The comment.
 * @return {Promise<object>} The stored comment.
 * @spec openspec/specs/announcements/spec.md#requirement-readers-like-and-comment-through-nextcloud-comments-req-ann-002
 */
export async function commentOnAnnouncement(uuid, message) {
	const { data } = await axios.post(url(`${BASE}/${encodeURIComponent(uuid)}/comments`), { message })
	return data
}

/**
 * Follow or stop following a category.
 *
 * @param {string} category The category.
 * @param {boolean} follow True to follow.
 * @return {Promise<string[]>} The categories now followed.
 * @spec openspec/specs/announcements/spec.md#requirement-readers-follow-categories-req-ann-003
 */
export async function followCategory(category, follow) {
	const { data } = await axios.put(url('/apps/launchpad/api/announcement-follows'), { category, follow })
	return data.following || []
}

/**
 * The editor groups (administrators).
 *
 * @return {Promise<string[]>} Group ids.
 * @spec openspec/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
 */
export async function getAnnouncementEditorGroups() {
	const { data } = await axios.get(url('/apps/launchpad/api/announcement-settings'))
	return data.groups || []
}

/**
 * Replace the editor groups (administrators).
 *
 * @param {string[]} groups Group ids.
 * @return {Promise<string[]>} The stored groups.
 * @spec openspec/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
 */
export async function saveAnnouncementEditorGroups(groups) {
	const { data } = await axios.put(url('/apps/launchpad/api/announcement-settings'), { groups })
	return data.groups || []
}

/**
 * The notices this person dismissed.
 *
 * @return {Promise<string[]>} Notice uuids.
 * @spec openspec/specs/announcements/spec.md#requirement-notices-show-above-every-targeted-dashboard-for-their-period-req-ann-005
 */
export async function getDismissedNotices() {
	try {
		const { data } = await axios.get(url(`/apps/launchpad/api/preferences/${DISMISSED_KEY}`))
		const value = data?.value
		const list = typeof value === 'string' ? JSON.parse(value || '[]') : value
		return Array.isArray(list) ? list.filter((id) => typeof id === 'string') : []
	} catch {
		return []
	}
}

/**
 * Remember the dismissed notices.
 *
 * @param {string[]} uuids Notice uuids.
 * @return {Promise<void>}
 * @spec openspec/specs/announcements/spec.md#requirement-notices-show-above-every-targeted-dashboard-for-their-period-req-ann-005
 */
export async function saveDismissedNotices(uuids) {
	await axios.put(url(`/apps/launchpad/api/preferences/${DISMISSED_KEY}`), { value: JSON.stringify(uuids) })
}
