/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Talk communities: what the widget is given from Talk's OCS answers
 * (openspec/changes/engagement-communities REQ-COMM-001..003).
 */

import { describe, expect, it, vi } from 'vitest'
import {
	communityUrl,
	joinCommunity,
	loadCommunities,
} from '../talkCommunities.js'

const ocs = (data) => ({ data: { ocs: { meta: { status: 'ok' }, data } } })

/**
 * A client answering the listed-room and room endpoints.
 *
 * @param {Array<object>} listed The open rooms Talk lists.
 * @param {Array<object>} own The viewer's rooms.
 * @return {object} The client.
 */
function client(listed, own) {
	return {
		get: vi.fn((url) =>
			Promise.resolve(ocs(url.endsWith('/listed-room') ? listed : own)),
		),
		post: vi.fn().mockResolvedValue(ocs([])),
	}
}

const DUURZAAMHEID = {
	token: 'duurzaam42',
	displayName: 'Duurzaamheid',
	description: 'Ideeën voor een groenere organisatie',
	listable: 2,
	participantCount: 14,
}
const VRAAG_ICT = { token: 'vraagict', displayName: 'Vraag het de ICT', description: '', listable: 1 }

describe('loadCommunities', () => {
	it('asks Talk as the viewer for the listed rooms and their own rooms, with the OCS header', async () => {
		const http = client([DUURZAAMHEID], [])

		await loadCommunities([], { client: http })

		const urls = http.get.mock.calls.map(([url]) => url)
		expect(urls).toEqual([
			expect.stringMatching(/\/ocs\/v2\.php\/apps\/spreed\/api\/v4\/listed-room$/),
			expect.stringMatching(/\/ocs\/v2\.php\/apps\/spreed\/api\/v4\/room$/),
		])
		for (const [, config] of http.get.mock.calls) {
			expect(config.headers['OCS-APIRequest']).toBe('true')
		}
	})

	it('lists each open room with name, description and member count, and marks the joined ones', async () => {
		const own = [{ ...VRAAG_ICT }, { token: 'privateroom', displayName: 'Team', listable: 0 }]

		const communities = await loadCommunities([], { client: client([DUURZAAMHEID], own) })

		expect(communities).toEqual([
			{
				token: 'duurzaam42',
				name: 'Duurzaamheid',
				description: 'Ideeën voor een groenere organisatie',
				members: 14,
				joined: false,
				pinned: false,
			},
			{
				token: 'vraagict',
				name: 'Vraag het de ICT',
				description: '',
				members: null,
				joined: true,
				pinned: false,
			},
		])
	})

	it('puts the pinned ones first in the order pinned, the rest by name, each once', async () => {
		const listed = [
			{ token: 'zzzzroom', displayName: 'Zeilen', listable: 2 },
			DUURZAAMHEID,
			VRAAG_ICT,
			{ token: 'aaaaroom', displayName: 'Atletiek', listable: 2 },
		]

		const communities = await loadCommunities(['vraagict', 'notvisible', 'zzzzroom'], {
			client: client(listed, [VRAAG_ICT]),
		})

		expect(communities.map((c) => [c.token, c.pinned])).toEqual([
			['vraagict', true],
			['zzzzroom', true],
			['aaaaroom', false],
			['duurzaam42', false],
		])
	})

	it('fails when Talk does', async () => {
		const http = { get: vi.fn().mockRejectedValue(new Error('Request failed with status code 404')) }

		await expect(loadCommunities([], { client: http })).rejects.toThrow('404')
	})
})

describe('joinCommunity and communityUrl', () => {
	it('joins through Talk as the viewer and opens the conversation in Talk', async () => {
		const http = client([], [])

		await joinCommunity('duurzaam42', { client: http })

		expect(http.post).toHaveBeenCalledWith(
			expect.stringMatching(/\/ocs\/v2\.php\/apps\/spreed\/api\/v4\/room\/duurzaam42\/participants\/self$/),
			{},
			expect.objectContaining({ headers: expect.objectContaining({ 'OCS-APIRequest': 'true' }) }),
		)
		expect(communityUrl('duurzaam42')).toMatch(/\/call\/duurzaam42$/)
	})
})
