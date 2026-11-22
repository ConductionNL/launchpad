/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The wiki pages a person can open, read through OpenRegister's XWiki
 * integration. OpenRegister routes the call to the `xwiki` source of the
 * connector app, which holds the wiki's address and credentials, so the
 * browser never sees either. The person's favourite pages are kept in their
 * launchpad preference `wiki-favourites`.
 *
 * @spec openspec/changes/wiki-widget/specs/wiki-widget/spec.md
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const FAVOURITES_KEY = 'wiki-favourites'

/** The most favourites a person keeps; the preference store caps a value at 8 KB. */
export const MAX_FAVOURITES = 20

/**
 * A wiki page as the widget shows it.
 *
 * @typedef {object} WikiPage
 * @property {string} reference The page's XWiki reference.
 * @property {string} title The page title.
 * @property {string} space The space it lives in.
 * @property {string} url The page's http(s) address in the wiki, '' when unknown or of another scheme.
 */

/**
 * A page row from the integration as a {@link WikiPage}, or null without a reference.
 *
 * @param {object} row The row.
 * @return {WikiPage|null}
 * @spec openspec/changes/wiki-widget/specs/wiki-widget/spec.md#requirement-the-wiki-widget-shows-favourites-recent-pages-and-search-req-wiki-001
 */
export function wikiPage(row) {
	const reference = String(row?.reference || row?.id || '')
	if (reference === '') {
		return null
	}
	return {
		reference,
		title: String(row.title || reference),
		space: String(row.space || ''),
		url: typeof row.url === 'string' && /^https?:\/\//i.test(row.url) ? row.url : '',
	}
}

/**
 * Search the wiki. An empty query lists the most recent pages.
 *
 * @param {string} query The search text.
 * @param {number} limit The most pages.
 * @return {Promise<{state: string, pages: Array<WikiPage>}>} `state` is
 *   `ok`, `not-connected` (OpenRegister answers that no XWiki source is set
 *   up or the wiki is down) or `unavailable` (OpenRegister is not there).
 * @spec openspec/changes/wiki-widget/specs/wiki-widget/spec.md#requirement-the-wiki-widget-shows-favourites-recent-pages-and-search-req-wiki-001
 */
export async function searchWikiPages(query, limit) {
	try {
		const { data } = await axios.get(generateUrl('/apps/openregister/api/integrations/xwiki/search'), {
			params: { q: query, limit },
		})
		const rows = Array.isArray(data?.results) ? data.results : []
		return { state: 'ok', pages: rows.map(wikiPage).filter(Boolean) }
	} catch (error) {
		const status = error?.response?.status
		return { state: status === 503 ? 'not-connected' : 'unavailable', pages: [] }
	}
}

/**
 * The person's favourite pages; empty when none are kept or the read fails.
 *
 * @return {Promise<Array<WikiPage>>}
 * @spec openspec/changes/wiki-widget/specs/wiki-widget/spec.md#requirement-a-person-keeps-favourite-pages-req-wiki-002
 */
export async function readWikiFavourites() {
	try {
		const { data } = await axios.get(generateUrl(`/apps/launchpad/api/preferences/${FAVOURITES_KEY}`))
		const parsed = JSON.parse(data?.value ?? '[]')
		return Array.isArray(parsed) ? parsed.map(wikiPage).filter(Boolean) : []
	} catch {
		return []
	}
}

/**
 * Keep the person's favourite pages, at most {@link MAX_FAVOURITES}.
 *
 * @param {Array<WikiPage>} pages The favourites, newest first.
 * @return {Promise<Array<WikiPage>>} What was kept.
 * @spec openspec/changes/wiki-widget/specs/wiki-widget/spec.md#requirement-a-person-keeps-favourite-pages-req-wiki-002
 */
export async function saveWikiFavourites(pages) {
	const kept = pages.slice(0, MAX_FAVOURITES).map((page) => ({
		reference: page.reference,
		title: page.title,
		space: page.space,
		url: page.url,
	}))
	await axios.put(generateUrl(`/apps/launchpad/api/preferences/${FAVOURITES_KEY}`), { value: JSON.stringify(kept) })
	return kept
}
