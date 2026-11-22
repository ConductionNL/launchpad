/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The wiki pages service: what OpenRegister's XWiki search answers, and the
 * favourites kept in the person's preference (wiki-widget REQ-WIKI-001..002).
 */

import axios from '@nextcloud/axios'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { MAX_FAVOURITES, readWikiFavourites, saveWikiFavourites, searchWikiPages, wikiPage } from '../wikiPages.js'

vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn(), put: vi.fn() } }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => `/index.php${path}` }))

beforeEach(() => {
	axios.get.mockReset()
	axios.put.mockReset().mockResolvedValue({ data: {} })
})

describe('wikiPages', () => {
	it('searches through OpenRegister with the query and limit', async () => {
		axios.get.mockResolvedValue({ data: { results: [{ reference: 'A.B', title: 'B', space: 'A', url: 'https://wiki/b' }, { title: 'no reference' }] } })
		expect(await searchWikiPages('kap', 5)).toEqual({ state: 'ok', pages: [{ reference: 'A.B', title: 'B', space: 'A', url: 'https://wiki/b' }] })
		expect(axios.get).toHaveBeenCalledWith('/index.php/apps/openregister/api/integrations/xwiki/search', { params: { q: 'kap', limit: 5 } })
	})

	it('reads a 503 as not connected and any other failure as unavailable', async () => {
		axios.get.mockRejectedValueOnce({ response: { status: 503 } })
		expect((await searchWikiPages('', 3)).state).toBe('not-connected')
		axios.get.mockRejectedValueOnce({ response: { status: 404 } })
		expect((await searchWikiPages('', 3)).state).toBe('unavailable')
	})

	it('keeps only an http(s) page address and titles a page by its reference without a title', () => {
		expect(wikiPage({ reference: 'X', url: 'javascript:alert(1)' })).toEqual({ reference: 'X', title: 'X', space: '', url: '' })
		expect(wikiPage({ id: 'Y', url: 'http://wiki/y' }).url).toBe('http://wiki/y')
	})

	it('reads the favourites from the preference, empty when there are none or they are broken', async () => {
		axios.get.mockResolvedValueOnce({ data: { value: JSON.stringify([{ reference: 'A', title: 'A' }]) } })
		expect(await readWikiFavourites()).toEqual([{ reference: 'A', title: 'A', space: '', url: '' }])
		expect(axios.get).toHaveBeenCalledWith('/index.php/apps/launchpad/api/preferences/wiki-favourites')
		axios.get.mockResolvedValueOnce({ data: { value: '{broken' } })
		expect(await readWikiFavourites()).toEqual([])
		axios.get.mockResolvedValueOnce({ data: { value: null } })
		expect(await readWikiFavourites()).toEqual([])
	})

	it('keeps at most the newest favourites', async () => {
		const pages = Array.from({ length: MAX_FAVOURITES + 5 }, (_, i) => ({ reference: `P${i}`, title: `P${i}`, space: '', url: '', extra: true }))
		const kept = await saveWikiFavourites(pages)
		expect(kept).toHaveLength(MAX_FAVOURITES)
		expect(kept[0]).toEqual({ reference: 'P0', title: 'P0', space: '', url: '' })
		const [, body] = axios.put.mock.calls[0]
		expect(JSON.parse(body.value)).toEqual(kept)
	})
})
