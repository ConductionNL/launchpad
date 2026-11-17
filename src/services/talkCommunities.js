/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Talk communities: the open Talk conversations a viewer may join, read from
 * Nextcloud Talk's OCS API as the signed-in user (engagement-communities D1).
 * Talk's own visibility rules decide what each person sees; LaunchPad keeps
 * nothing about conversations.
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl, generateUrl } from '@nextcloud/router'

/** The OCS headers Talk needs to answer JSON. */
const OCS_CONFIG = { headers: { 'OCS-APIRequest': 'true', Accept: 'application/json' } }

/** Talk's `listable` values above 0 mean an open conversation (1 users, 2 everyone). */
const LISTABLE_NONE = 0

/**
 * The OCS URL of a Talk v4 endpoint.
 *
 * @param {string} path The path after `apps/spreed/api/v4/`.
 * @return {string} The URL.
 */
function talkUrl(path) {
	return generateOcsUrl(`apps/spreed/api/v4/${path}`)
}

/**
 * The rooms of an OCS answer, or an empty list.
 *
 * @param {object} response The axios response.
 * @return {Array<object>} The rooms.
 */
function roomsOf(response) {
	const data = response?.data?.ocs?.data
	return Array.isArray(data) ? data : []
}

/**
 * The member count Talk reports for a room, or null when it reports none.
 *
 * @param {object} room A Talk room.
 * @return {number|null} The count.
 */
function memberCount(room) {
	for (const key of ['participantCount', 'participantsCount', 'memberCount']) {
		if (Number.isInteger(room?.[key])) {
			return room[key]
		}
	}
	return null
}

/**
 * One community as the widget shows it.
 *
 * @param {object} room A Talk room.
 * @param {{joined: boolean, pinned: boolean}} marks Whether the viewer joined it, and whether it is pinned.
 * @return {{token: string, name: string, description: string, members: number|null, joined: boolean, pinned: boolean}} The community.
 */
function community(room, marks) {
	return {
		token: String(room.token),
		name: String(room.displayName || room.name || room.token),
		description: String(room.description || ''),
		members: memberCount(room),
		joined: marks.joined,
		pinned: marks.pinned,
	}
}

/**
 * Load the open conversations the viewer may join, with the ones they joined
 * marked, the pinned ones first in the order pinned, the rest by name.
 *
 * The listed rooms are the open conversations Talk lets the viewer find; the
 * viewer's own rooms tell which they joined, and add the open ones they are
 * already in when Talk leaves those out of the listing.
 *
 * @spec openspec/changes/engagement-communities/specs/communities-widget/spec.md#requirement-the-communities-widget-lists-open-talk-conversations-the-viewer-may-join-req-comm-001
 * @param {Array<string>} pinned The pinned tokens, in the order pinned.
 * @param {object} [opts] Options.
 * @param {object} [opts.client] An axios-like client, for tests.
 * @return {Promise<Array<object>>} The communities.
 */
export async function loadCommunities(pinned = [], opts = {}) {
	const client = opts.client || axios
	const [listed, own] = await Promise.all([
		client.get(talkUrl('listed-room'), OCS_CONFIG),
		client.get(talkUrl('room'), OCS_CONFIG),
	])
	const ownRooms = roomsOf(own)
	const joined = new Set(ownRooms.map((room) => String(room.token)))
	const pinnedOrder = new Map(
		(Array.isArray(pinned) ? pinned : []).map((token, index) => [token, index]),
	)

	const byToken = new Map()
	const add = (room) => {
		const token = String(room?.token || '')
		if (token === '' || byToken.has(token)) {
			return
		}
		byToken.set(
			token,
			community(room, { joined: joined.has(token), pinned: pinnedOrder.has(token) }),
		)
	}
	roomsOf(listed).forEach(add)
	ownRooms.filter((room) => Number(room.listable) > LISTABLE_NONE).forEach(add)

	return [...byToken.values()].sort((a, b) => compareCommunities(a, b, pinnedOrder))
}

/**
 * Pinned first in the order pinned, then by name.
 *
 * @param {object} a A community.
 * @param {object} b Another community.
 * @param {Map<string, number>} pinnedOrder Position of each pinned token.
 * @return {number} The sort order.
 */
function compareCommunities(a, b, pinnedOrder) {
	if (a.pinned !== b.pinned) {
		return a.pinned ? -1 : 1
	}
	if (a.pinned) {
		return pinnedOrder.get(a.token) - pinnedOrder.get(b.token)
	}
	return a.name.localeCompare(b.name)
}

/**
 * Join a conversation as the viewer.
 *
 * @spec openspec/changes/engagement-communities/specs/communities-widget/spec.md#requirement-one-click-joins-a-community-req-comm-002
 * @param {string} token The conversation token.
 * @param {object} [opts] Options.
 * @param {object} [opts.client] An axios-like client, for tests.
 * @return {Promise<void>} Resolves once Talk added the viewer.
 */
export async function joinCommunity(token, opts = {}) {
	const client = opts.client || axios
	await client.post(
		talkUrl(`room/${encodeURIComponent(token)}/participants/self`),
		{},
		OCS_CONFIG,
	)
}

/**
 * The Talk page of a conversation.
 *
 * @spec openspec/changes/engagement-communities/specs/communities-widget/spec.md#requirement-one-click-joins-a-community-req-comm-002
 * @param {string} token The conversation token.
 * @return {string} The URL.
 */
export function communityUrl(token) {
	return generateUrl(`/call/${encodeURIComponent(token)}`)
}
