// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.

/**
 * The allowed websites list of the General tab (REQ-ASET-021): each host with
 * its purpose, Embed, Fetch or Embed and fetch. Pure, so the mapping runs
 * under vitest with nothing mocked; the server normalises and checks hosts.
 *
 * @spec openspec/changes/archive/2026-11-24-allowed-websites-list/specs/admin-settings/spec.md#requirement-one-list-of-allowed-websites-with-a-purpose-per-site-req-aset-021
 */

/** @type {string[]} The purposes, in the order the select offers them. */
export const PURPOSES = ['embed', 'fetch', 'both']

/**
 * The purpose of a stored site.
 *
 * @param {{embed?: boolean, fetch?: boolean}} site The site.
 * @return {'embed'|'fetch'|'both'} The purpose; `embed` when neither is set.
 * @spec openspec/changes/archive/2026-11-24-allowed-websites-list/specs/admin-settings/spec.md#requirement-one-list-of-allowed-websites-with-a-purpose-per-site-req-aset-021
 */
export function purposeOf(site) {
	if (site?.embed === true && site?.fetch === true) {
		return 'both'
	}
	return site?.fetch === true ? 'fetch' : 'embed'
}

/**
 * A site as the server stores it.
 *
 * @param {string} host The host.
 * @param {string} purpose `embed`, `fetch` or `both`.
 * @return {{host: string, embed: boolean, fetch: boolean}} The site.
 * @spec openspec/changes/archive/2026-11-24-allowed-websites-list/specs/admin-settings/spec.md#requirement-one-list-of-allowed-websites-with-a-purpose-per-site-req-aset-021
 */
export function siteFor(host, purpose) {
	return {
		host: String(host ?? '').trim(),
		embed: purpose === 'embed' || purpose === 'both',
		fetch: purpose === 'fetch' || purpose === 'both',
	}
}

/**
 * The rows the block edits, from a server answer: `{host, purpose}`.
 *
 * @param {object} data The answer, `{sites}`.
 * @return {Array<{host: string, purpose: string}>} The rows.
 * @spec openspec/changes/archive/2026-11-24-allowed-websites-list/specs/admin-settings/spec.md#requirement-one-list-of-allowed-websites-with-a-purpose-per-site-req-aset-021
 */
export function rowsFrom(data) {
	const sites = Array.isArray(data?.sites) ? data.sites : []
	return sites
		.filter((site) => typeof site?.host === 'string' && site.host !== '')
		.map((site) => ({ host: site.host, purpose: purposeOf(site) }))
}

/**
 * Add a row; a host already on the list takes the new purpose instead of
 * appearing twice. A blank host adds nothing.
 *
 * @param {Array<{host: string, purpose: string}>} rows The rows.
 * @param {string} host The entered host.
 * @param {string} purpose The chosen purpose.
 * @return {Array<{host: string, purpose: string}>} The new rows.
 * @spec openspec/changes/archive/2026-11-24-allowed-websites-list/specs/admin-settings/spec.md#requirement-one-list-of-allowed-websites-with-a-purpose-per-site-req-aset-021
 */
export function addRow(rows, host, purpose) {
	const entered = String(host ?? '').trim()
	if (entered === '') {
		return rows
	}
	const key = entered.toLowerCase()
	if (rows.some((row) => row.host.toLowerCase() === key)) {
		return rows.map((row) => (row.host.toLowerCase() === key ? { ...row, purpose } : row))
	}
	return [...rows, { host: entered, purpose }]
}
