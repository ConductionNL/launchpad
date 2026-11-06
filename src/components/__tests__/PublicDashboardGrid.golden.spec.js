/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Golden test for PublicDashboardGrid's items: the normalised display item of
 * every placement shape, and the rendered markup of a mixed grid, compared
 * with fixtures/PublicDashboardGrid.golden.json (one case per line). Set
 * RECORD_GOLDEN=1 to rewrite it.
 */

import { mount } from '@vue/test-utils'
import fs from 'node:fs'
import path from 'node:path'
import { describe, expect, it } from 'vitest'
import PublicDashboardGrid from '../PublicDashboardGrid.vue'

const FIXTURE = path.resolve(__dirname, 'fixtures/PublicDashboardGrid.golden.json')

const WIDGET_IDS = [
	'tile-1',
	'tile-',
	'label',
	'header',
	'text',
	'image',
	'link',
	'divider',
	'calendar',
	'restricted',
	'Tile-1',
	'tile1',
	'',
	undefined,
	null,
	0,
	false,
]

const TILE_TYPES = [undefined, '', 'app', 0, 1]

const CONTENTS = {
	none: undefined,
	nullContent: null,
	stringContent: 'text',
	empty: {},
	labelOnly: { label: 'L' },
	titleOnly: { title: 'T' },
	labelAndTitle: { label: 'L', title: 'T' },
	textAndBody: { text: 'X', body: 'B', url: 'https://u' },
	bodyOnly: { body: 'B' },
	emptyStrings: { label: '', title: '', text: '', body: '', url: '' },
	array: ['a'],
	falsyValues: { label: 0, text: 0, url: 0 },
}

const FIELDS = {
	bare: {},
	customTitle: { customTitle: 'C', tileTitle: 'TT' },
	tileTitle: { customTitle: '', tileTitle: 'TT' },
	sized: { gridWidth: 6, gridHeight: 2 },
	zeroSize: { gridWidth: 0, gridHeight: 0 },
	hiddenTitle0: { showTitle: 0 },
	hiddenTitleFalse: { showTitle: false },
	shownTitle: { showTitle: 1 },
	nullTitle: { showTitle: null },
	tileLook: {
		tileIcon: '/i.svg',
		tileBackgroundColor: '#fff',
		tileTextColor: '#000',
		tileLinkValue: 'https://t',
	},
	tileLookFalsy: { tileIcon: 0, tileBackgroundColor: false, tileTextColor: false },
	tileLookEmpty: {
		tileIcon: '',
		tileBackgroundColor: null,
		tileTextColor: 0,
		tileLinkValue: undefined,
	},
}

/**
 * The normalised items for a list of placements.
 *
 * @param {Array<object>} placements The placements to normalise.
 * @return {Array<object>} The display items, JSON round-tripped.
 */
function itemsOf(placements) {
	return JSON.parse(
		JSON.stringify(PublicDashboardGrid.computed.items.call({ placements })),
	)
}

/**
 * Every single-placement case, keyed by a readable name.
 *
 * @return {object} Case name to placement.
 */
function placementCases() {
	const all = {}
	for (const widgetId of WIDGET_IDS) {
		for (const tileType of TILE_TYPES) {
			all[`widget ${String(widgetId)} / tileType ${String(tileType)}`] = {
				id: 1,
				widgetId,
				tileType,
			}
		}
		for (const [name, content] of Object.entries(CONTENTS)) {
			all[`widget ${String(widgetId)} / content ${name}`] = {
				id: 2,
				widgetId,
				content,
			}
		}
	}
	for (const [name, fields] of Object.entries(FIELDS)) {
		for (const widgetId of ['tile-9', 'text', 'calendar']) {
			all[`fields ${name} / widget ${widgetId}`] = {
				id: 3,
				widgetId,
				content: { title: 'CT', text: 'CX' },
				...fields,
			}
		}
	}
	return all
}

const MIXED = [
	{ id: 5, widgetId: 'text', gridX: 2, gridY: 1, content: { text: 'Hello' } },
	{
		id: 4,
		widgetId: 'tile-1',
		gridX: 0,
		gridY: 1,
		tileTitle: 'Mail',
		tileIcon: '/m.svg',
	},
	{ id: 3, widgetId: 'header', gridX: 1, gridY: 0, content: { label: 'Head' } },
	{ id: 2, widgetId: 'calendar', gridX: 0, gridY: 0, isVisible: 1 },
	{
		id: 1,
		widgetId: 'image',
		gridX: 3,
		gridY: 0,
		content: { url: '/p.png', title: 'Pic' },
	},
	{
		id: 6,
		widgetId: 'link',
		gridX: 0,
		gridY: 2,
		isVisible: 0,
		content: { url: '/x' },
	},
	{ id: 7, widgetId: 'link', gridX: 1, gridY: 2, isVisible: false },
	{
		id: 8,
		widgetId: 'divider',
		gridX: 2,
		gridY: 2,
		showTitle: 1,
		customTitle: 'D',
	},
	{
		id: 9,
		widgetId: 'link',
		gridX: 3,
		gridY: 2,
		isVisible: null,
		content: { url: '/y' },
	},
]

describe('PublicDashboardGrid items (golden)', () => {
	it('matches the recorded items and markup', () => {
		const actual = {}
		for (const [name, placement] of Object.entries(placementCases())) {
			actual[name] = itemsOf([placement])
		}
		actual['mixed / items'] = itemsOf(MIXED)
		actual['empty / items'] = itemsOf([])
		const wrapper = mount(PublicDashboardGrid, { props: { placements: MIXED } })
		actual['mixed / markup'] = wrapper.html()
		wrapper.unmount()
		if (process.env.RECORD_GOLDEN === '1') {
			const lines = Object.entries(actual).map(
				([name, value]) =>
					`\t${JSON.stringify(name)}: ${JSON.stringify(value)}`,
			)
			fs.writeFileSync(FIXTURE, `{\n${lines.join(',\n')}\n}\n`)
			return
		}
		const expected = JSON.parse(fs.readFileSync(FIXTURE, 'utf8'))
		expect(Object.keys(actual)).toEqual(Object.keys(expected))
		for (const name of Object.keys(expected)) {
			expect(actual[name], name).toEqual(expected[name])
		}
	})
})
