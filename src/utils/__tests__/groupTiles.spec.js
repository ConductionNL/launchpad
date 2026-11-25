/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A tile inside a group read into the tile form and written back into the
 * group with the group's sort order (tile-form-board-sync REQ-TILE-041).
 */

import { describe, expect, it } from 'vitest'
import {
	groupChildFromTile,
	groupChildToTile,
	isGroupTileChild,
	withGroupTileRemoved,
	withGroupTileSaved,
} from '../groupTiles.js'

const caseSystem = {
	type: 'tile',
	gridX: 2,
	gridY: 0,
	gridWidth: 2,
	gridHeight: 2,
	content: {
		title: 'Case system',
		icon: 'M1 1',
		iconType: 'svg',
		backgroundColor: '#123456',
		textColor: '#ffffff',
		linkType: 'url',
		linkValue: 'https://cases.example.org',
		healthPingEnabled: true,
		internalUrl: 'http://cases.local',
		pinned: true,
	},
}

const bookmark = {
	type: 'link',
	gridX: 0,
	gridY: 0,
	content: { label: 'Intranet', url: 'https://intranet.example.org', actionType: 'external' },
}

describe('isGroupTileChild', () => {
	it('takes a tile and a link that opens one address', () => {
		expect(isGroupTileChild(caseSystem)).toBe(true)
		expect(isGroupTileChild(bookmark)).toBe(true)
		expect(isGroupTileChild({ type: 'link', content: { url: '/apps/files' } })).toBe(true)
	})

	it('leaves an office New tile, an action link, a link list and other widgets alone', () => {
		expect(isGroupTileChild({ type: 'tile', content: { linkType: 'create', linkValue: 'docx' } })).toBe(false)
		expect(isGroupTileChild({ type: 'link', content: { url: 'x', actionType: 'createFile' } })).toBe(false)
		expect(isGroupTileChild({ type: 'link', content: { url: 'x', links: [{ url: 'y' }] } })).toBe(false)
		expect(isGroupTileChild({ type: 'link', content: { url: ' ' } })).toBe(false)
		expect(isGroupTileChild({ type: 'label', content: { text: 'Hi' } })).toBe(false)
		expect(isGroupTileChild(null)).toBe(false)
		expect(isGroupTileChild('tile')).toBe(false)
	})
})

describe('groupChildToTile', () => {
	it('reads the fields the form edits from a tile child', () => {
		expect(groupChildToTile(caseSystem)).toEqual({
			title: 'Case system',
			icon: 'M1 1',
			iconType: 'svg',
			backgroundColor: '#123456',
			textColor: '#ffffff',
			linkType: 'url',
			linkValue: 'https://cases.example.org',
		})
	})

	it('reads a link child as a web address tile', () => {
		expect(groupChildToTile(bookmark)).toEqual({
			title: 'Intranet',
			iconType: 'svg',
			linkType: 'url',
			linkValue: 'https://intranet.example.org',
		})
	})
})

describe('groupChildFromTile', () => {
	it('writes the form result and keeps every key the form does not edit, and the layout', () => {
		const saved = groupChildFromTile(caseSystem, { ...groupChildToTile(caseSystem), title: 'Cases', healthPingEnabled: false })
		expect(saved.type).toBe('tile')
		expect(saved.gridX).toBe(2)
		expect(saved.content.title).toBe('Cases')
		// Launch, availability and office-network settings are kept, not dropped.
		expect(saved.content.healthPingEnabled).toBe(true)
		expect(saved.content.internalUrl).toBe('http://cases.local')
		expect(saved.content.pinned).toBe(true)
	})

	it('turns a link child into a tile node without its link keys', () => {
		const saved = groupChildFromTile(bookmark, { ...groupChildToTile(bookmark), icon: 'M2 2' })
		expect(saved.type).toBe('tile')
		expect(saved.gridX).toBe(0)
		expect(saved.content).toEqual({
			title: 'Intranet',
			icon: 'M2 2',
			iconType: 'svg',
			linkType: 'url',
			linkValue: 'https://intranet.example.org',
		})
	})

	it('paints a theme colour as its CSS variable and drops an emptied field', () => {
		const saved = groupChildFromTile(caseSystem, { ...groupChildToTile(caseSystem), colourToken: '--sc-primary', textColor: '' })
		expect(saved.content.colourToken).toBe('--sc-primary')
		expect(saved.content.backgroundColor).toBe('var(--sc-primary, #3669a5)')
		expect('textColor' in saved.content).toBe(false)
	})
})

describe('withGroupTileSaved', () => {
	const group = { title: 'Work apps', sortBy: 'manual', placements: [bookmark, caseSystem] }

	it('replaces the one child and writes the group sort order', () => {
		const content = withGroupTileSaved(group, 1, { ...groupChildToTile(caseSystem), groupSortBy: 'last-used' })
		expect(content.title).toBe('Work apps')
		expect(content.sortBy).toBe('last-used')
		expect(content.placements[0]).toBe(bookmark)
		expect(content.placements[1].content.title).toBe('Case system')
		// The stored content is not changed in place.
		expect(group.sortBy).toBe('manual')
		expect(group.placements[1]).toBe(caseSystem)
	})

	it('keeps the sort order when the form carries none, and normalises an unknown one', () => {
		expect(withGroupTileSaved(group, 0, groupChildToTile(bookmark)).sortBy).toBe('manual')
		expect(withGroupTileSaved(group, 0, { ...groupChildToTile(bookmark), groupSortBy: 'sideways' }).sortBy).toBe('manual')
	})

	it('answers null for a child that is not there or not a tile', () => {
		expect(withGroupTileSaved(group, 5, {})).toBeNull()
		expect(withGroupTileSaved({ placements: [{ type: 'label' }] }, 0, {})).toBeNull()
		expect(withGroupTileSaved({}, 0, {})).toBeNull()
	})
})

describe('withGroupTileRemoved', () => {
	it('removes the one child', () => {
		const content = withGroupTileRemoved({ title: 'Work apps', placements: [bookmark, caseSystem] }, 0)
		expect(content).toEqual({ title: 'Work apps', placements: [caseSystem] })
	})

	it('answers null for a child that is not a tile', () => {
		expect(withGroupTileRemoved({ placements: [{ type: 'label' }] }, 0)).toBeNull()
		expect(withGroupTileRemoved(null, 0)).toBeNull()
	})
})
