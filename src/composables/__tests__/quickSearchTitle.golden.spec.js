/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Golden test for resolveWidgetTitle and activateSearchResult: a matrix of
 * placements and widget catalogs, and of opened results against fake grid
 * items, compared with fixtures/quickSearchTitle.golden.json case by case.
 * Set RECORD_GOLDEN=1 to rewrite it.
 */

import fs from 'node:fs'
import path from 'node:path'
import { afterEach, describe, expect, it, vi } from 'vitest'

vi.mock('@conduction/nextcloud-vue', () => ({
	dashboardWidgetRegistry: {
		chart: { displayName: 'Chart' },
		blank: { displayName: '' },
		bare: {},
	},
}))

vi.mock('@nextcloud/l10n', () => ({
	translate: (_app, str) => `t:${str}`,
}))

const FIXTURE = path.resolve(__dirname, 'fixtures/quickSearchTitle.golden.json')

const PLACEMENTS = {
	null: null,
	'custom tile with title': { tileType: 'custom', tileTitle: 'Mail', customTitle: 'X' },
	'custom tile without title': { tileType: 'custom', tileTitle: '' },
	'custom title': { widgetId: 'calendar', customTitle: 'My cal' },
	'empty custom title': { widgetId: 'calendar', customTitle: '' },
	'catalog widget': { widgetId: 'calendar' },
	'catalog widget without title': { widgetId: 'untitled' },
	'proxy to catalog': { widgetId: 'nc-widget', content: { widgetId: 'deals' } },
	'proxy to untitled': { widgetId: 'nc-widget', content: { widgetId: 'untitled' } },
	'proxy to unknown': { widgetId: 'nc-widget', content: { widgetId: 'nope' } },
	'proxy without id': { widgetId: 'nc-widget', content: {} },
	'registry type': { widgetId: 'chart', content: { widgetId: 'nope' } },
	'registry blank name': { widgetId: 'blank' },
	'registry without name': { widgetId: 'bare' },
	unknown: { widgetId: 'mystery' },
	'tile type other': { tileType: 'app', widgetId: 'chart' },
}

const CATALOGS = {
	undefined,
	empty: [],
	full: [
		{ id: 'calendar', title: 'Calendar' },
		{ id: 'deals', title: 'Deals overview' },
		{ id: 'untitled', title: '' },
		{ id: 'chart', title: 'Catalog chart' },
	],
}

/**
 * A fake grid item that logs what is done to it.
 *
 * @param {object} spec Which methods and attributes the item has.
 * @param {Array<string>} log Receives one entry per effect.
 * @return {object} The item.
 */
function fakeItem(spec, log) {
	const item = { attrs: { ...(spec.attrs || {}) } }
	item.hasAttribute = (name) => name in item.attrs
	item.setAttribute = (name, value) => {
		log.push(`setAttribute ${name}=${value}`)
		item.attrs[name] = value
	}
	if (spec.scroll) {
		item.scrollIntoView = (opts) => log.push(`scroll ${JSON.stringify(opts)}`)
	}
	if (spec.focus) {
		item.focus = (opts) => log.push(`focus ${JSON.stringify(opts)}`)
	}
	item.querySelector = (selector) => {
		log.push(`item.querySelector ${selector}`)
		if (!spec.link) {
			return null
		}
		return spec.link === 'clickable'
			? { click: () => log.push('link click') }
			: { click: 'not a function' }
	}
	return item
}

const ITEMS = {
	missing: null,
	link: { scroll: true, focus: true, link: 'clickable' },
	'link without click': { scroll: true, focus: true, link: 'broken' },
	'no link, focusable': { scroll: true, focus: true },
	'no link, focusable with tabindex': { focus: true, attrs: { tabindex: '0' } },
	'no link, not focusable': { scroll: true },
	bare: {},
}

const RESULTS = {
	undefined,
	'no placement': {},
	'id null': { placement: { id: null } },
	'id zero': { placement: { id: 0 } },
	'id number': { placement: { id: 12 } },
	'id with quote': { placement: { id: 'a"b' } },
}

/**
 * Run activateSearchResult with a grid (or none) holding one item.
 *
 * @param {object} result The opened result.
 * @param {{grid: boolean, item: object|null}} setup The page.
 * @return {Promise<object>} What it returned and did.
 */
async function activate(result, setup) {
	const { activateSearchResult } = await import('../useTileSearchHost.js')
	const log = []
	const grid = {
		querySelector: (selector) => {
			log.push(`grid.querySelector ${selector}`)
			return setup.item ? fakeItem(setup.item, log) : null
		},
	}
	vi.spyOn(document, 'getElementById').mockImplementation((id) => {
		log.push(`getElementById ${id}`)
		return setup.grid ? grid : null
	})
	const value = activateSearchResult(result)
	return { value, log }
}

/**
 * Record every case of the two functions.
 *
 * @return {Promise<object>} The outcomes by case name.
 */
async function record() {
	const { resolveWidgetTitle } = await import('../../utils/widgetTitle.js')
	const actual = {}
	for (const [catalogName, catalog] of Object.entries(CATALOGS)) {
		for (const [name, placement] of Object.entries(PLACEMENTS)) {
			actual[`resolveWidgetTitle | ${catalogName} | ${name}`] =
				resolveWidgetTitle(placement, catalog)
		}
	}
	for (const [resultName, result] of Object.entries(RESULTS)) {
		actual[`activateSearchResult | no grid | ${resultName}`] = await activate(
			result,
			{ grid: false, item: null },
		)
		vi.restoreAllMocks()
		for (const [itemName, item] of Object.entries(ITEMS)) {
			actual[`activateSearchResult | ${itemName} | ${resultName}`] =
				await activate(result, { grid: true, item })
			vi.restoreAllMocks()
		}
	}
	return actual
}

describe('quick search title and activation (golden)', () => {
	afterEach(() => {
		vi.restoreAllMocks()
	})

	it('matches the recorded outcomes case by case', async () => {
		const actual = await record()
		if (process.env.RECORD_GOLDEN) {
			fs.writeFileSync(FIXTURE, JSON.stringify(actual, null, '\t') + '\n')
		}
		const expected = JSON.parse(fs.readFileSync(FIXTURE, 'utf8'))
		expect(Object.keys(actual)).toEqual(Object.keys(expected))
		for (const [name, value] of Object.entries(expected)) {
			expect(actual[name], name).toEqual(value)
		}
	})
})
