/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The widget picker's cards, kinds and counts (widget-picker-board-sync).
 *
 * @spec openspec/specs/widgets/spec.md
 */

import { describe, expect, it } from 'vitest'
import { countByKind, filterCards, isLegacyWidget, kindLabel, kindOfType, KINDS, pickerCards, roleAllows, sectionLabel, typeLine } from '../widgetPickerCards.js'

const t = (_app, text) => text

const INPUT = {
	customTypes: [
		{ type: 'pie-chart', title: 'Pie chart' },
		{ type: 'graph', title: 'Graph' },
		{ type: 'clock', title: 'Clock' },
		{ type: 'iframe', title: 'Embedded page' },
		{ type: 'people', title: 'People' },
		{ type: 'container', title: 'Container' },
		{ type: 'work-packages', title: 'My work packages' },
	],
	officePack: true,
	catalogueApps: [{ uuid: 'z1', name: 'Case system', description: 'Cases for the front office', icon: '/z.svg', iconType: 'url' }],
	discoveredApps: [{ appId: 'deck', name: 'Deck', icon: '/deck.svg' }],
	widgets: [
		{ id: 'notes', title: 'Notes', itemApiVersions: [1, 2] },
		{ id: 'oldie', title: 'Old widget', itemApiVersions: [] },
	],
	allowedWidgets: null,
	placedWidgetIds: ['notes'],
	placedAppIds: ['deck'],
}

describe('widget picker kinds', () => {
	// add-widget-dialog-with-miniatures REQ-WDG-041: Layout sits after Tiles and links.
	it('lists the eight kinds of the board in order, with their labels', () => {
		expect(KINDS).toEqual(['all', 'content', 'tiles', 'layout', 'people', 'data', 'apps', 'office'])
		expect(KINDS.map((kind) => kindLabel(kind, t))).toEqual(['All', 'Content', 'Tiles and links', 'Layout', 'People', 'Data and charts', 'From other apps', 'Office'])
		expect(kindLabel('unknown', t)).toBe('unknown')
	})

	it('puts each type in one kind, content when it names none', () => {
		expect(['pie-chart', 'graph', 'stat', 'table'].map(kindOfType)).toEqual(['data', 'data', 'data', 'data'])
		expect(['tile', 'link'].map(kindOfType)).toEqual(['tiles', 'tiles'])
		// Container groups other widgets: Layout, no longer Tiles and links.
		expect(kindOfType('container')).toBe('layout')
		expect(kindOfType('people')).toBe('people')
		expect(kindOfType('assigned-cases')).toBe('apps')
		expect(['clock', 'iframe', 'section-heading', 'something-new'].map(kindOfType)).toEqual(['content', 'content', 'content', 'content'])
	})

	it('names the three sections and gives every type one line', () => {
		expect(['launchpad', 'tiles', 'apps', 'x'].map((id) => sectionLabel(id, t))).toEqual(['Widgets from Launchpad', 'App tiles', 'From other Nextcloud apps', 'x'])
		expect(typeLine('pie-chart', t)).toBe('Shares of a whole as a pie.')
		expect(typeLine('iframe', t)).toBe('A page from another site.')
		expect(typeLine('brand-new', t)).toBe('A Launchpad widget.')
	})
})

describe('pickerCards', () => {
	it('makes one card per offer, in section order, App tiles ending with Own tile', () => {
		const cards = pickerCards(INPUT, t)
		expect(cards.map((card) => card.section)).toEqual([
			'launchpad', 'launchpad', 'launchpad', 'launchpad', 'launchpad', 'launchpad', 'launchpad', 'launchpad',
			'tiles', 'tiles', 'tiles',
			'apps', 'apps',
		])
		expect(cards.filter((card) => card.section === 'tiles').map((card) => card.title)).toEqual(['Case system', 'Deck', 'Own tile'])
		expect(cards.find((card) => card.key === 'catalogue:z1')).toMatchObject({ kind: 'tiles', line: 'Cases for the front office', action: { event: 'addCatalogueApp' } })
		expect(cards.find((card) => card.key === 'app:deck').badge).toBe('On this dashboard')
		expect(cards.find((card) => card.key === 'own-tile').action).toEqual({ event: 'addCustom', arg: 'tile' })
		expect(cards.find((card) => card.key === 'office-pack')).toMatchObject({ kind: 'office', action: { event: 'addOfficePack' } })
	})

	it('puts placed Nextcloud widgets last and badges them, and marks an older widget type', () => {
		const cards = pickerCards(INPUT, t).filter((card) => card.section === 'apps')
		expect(cards.map((card) => card.title)).toEqual(['Old widget', 'Notes'])
		expect(cards[1].badge).toBe('Already added')
		expect(cards[0].legacy).toBe(true)
		expect(cards[1].legacy).toBe(false)
		expect(cards[1].action).toEqual({ event: 'add', arg: 'notes' })
	})

	it('greys out what the role may not add, as the server decides it', () => {
		const cards = pickerCards({ ...INPUT, allowedWidgets: ['clock', 'notes'] }, t)
		const disabled = Object.fromEntries(cards.map((card) => [card.key, card.disabled]))
		expect(disabled['type:clock']).toBe(false)
		expect(disabled['type:iframe']).toBe(true)
		expect(disabled['widget:notes']).toBe(false)
		expect(disabled['widget:oldie']).toBe(true)
		// App tiles and Own tile follow `tile`; the office pack follows `container`.
		expect(disabled['catalogue:z1']).toBe(true)
		expect(disabled['own-tile']).toBe(true)
		expect(disabled['office-pack']).toBe(true)
		expect(pickerCards({ ...INPUT, allowedWidgets: ['tile'] }, t).find((card) => card.key === 'own-tile').disabled).toBe(false)
	})

	it('leaves the office pack out when it is not offered', () => {
		expect(pickerCards({ ...INPUT, officePack: false }, t).some((card) => card.key === 'office-pack')).toBe(false)
		expect(pickerCards({}, t).map((card) => card.key)).toEqual(['own-tile'])
	})
})

describe('counting and filtering', () => {
	const cards = pickerCards(INPUT, t)

	it('counts the cards of each kind, and every card under All', () => {
		expect(countByKind(cards)).toEqual({ all: 13, content: 2, tiles: 3, layout: 1, people: 1, data: 2, apps: 3, office: 1 })
	})

	it('filters to the chosen kind, and searches within it by title or line', () => {
		expect(filterCards(cards, 'data', '').map((card) => card.title)).toEqual(['Pie chart', 'Graph'])
		expect(filterCards(cards, 'data', 'PIE').map((card) => card.title)).toEqual(['Pie chart'])
		expect(filterCards(cards, 'tiles', 'front office').map((card) => card.title)).toEqual(['Case system'])
		expect(filterCards(cards, 'all', 'clock').map((card) => card.title)).toEqual(['Clock'])
		expect(filterCards(cards, 'people', 'clock')).toEqual([])
	})
})

describe('role and older widgets', () => {
	it('allows everything without a list, and only what the list names with one', () => {
		expect(roleAllows('iframe', null)).toBe(true)
		expect(roleAllows('iframe', undefined)).toBe(true)
		expect(roleAllows('iframe', [])).toBe(false)
		expect(roleAllows('iframe', ['iframe'])).toBe(true)
	})

	it('calls a widget older only when it says it has no item API', () => {
		expect(isLegacyWidget({ itemApiVersions: [] })).toBe(true)
		expect(isLegacyWidget({ itemApiVersions: [1] })).toBe(false)
		expect(isLegacyWidget({})).toBe(false)
		expect(isLegacyWidget(null)).toBe(false)
	})
})
