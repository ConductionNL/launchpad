/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The children of a well or tabs widget by key (REQ-WDG-050): their embed
 * subject, every child for the embed form, and Duplicate / Remove at any depth.
 *
 * @spec openspec/changes/edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
 */

import { describe, expect, it } from 'vitest'
import { allChildren, childLabel, childSubjectId, duplicateChildIn, findChildIn, patchChildIn, removeChildIn } from '../containerChildren.js'

const content = {
	title: 'Werk',
	placements: [
		{ type: 'tile', id: 5, tileTitle: 'Mail', gridX: 0, gridY: 0, gridWidth: 2, gridHeight: 2 },
		{ type: 'text', uuid: '12-1', content: { title: 'Welkom' }, gridX: 2, gridY: 0, gridWidth: 2, gridHeight: 3 },
	],
	tabs: [
		{ id: 't1', title: 'Team', placements: [{ type: 'container', uuid: 'w', content: { placements: [{ type: 'clock', uuid: 'deep' }] } }] },
	],
}

describe('containerChildren', () => {
	it('names a stored child as <placement>.<key>, and nothing it cannot name', () => {
		expect(childSubjectId(12, { id: 5 })).toBe('12.5')
		expect(childSubjectId('12', { uuid: '12-1' })).toBe('12.12-1')
		expect(childSubjectId(12, { type: 'tile' })).toBe('')
		expect(childSubjectId('draft-1', { uuid: 'a' })).toBe('')
		expect(childSubjectId(null, { uuid: 'a' })).toBe('')
		expect(childSubjectId(12, { uuid: 'a b' })).toBe('')
	})

	it('lists every child at any depth with the titles above it', () => {
		const found = allChildren(content).map(({ child, trail }) => [childLabel(child), trail])
		expect(found).toEqual([
			['Mail', []],
			['Welkom', []],
			['container', ['Team']],
			['clock', ['Team', 'container']],
		])
		expect(allChildren(null)).toEqual([])
	})

	it('duplicates a child after itself, without its key, below the others', () => {
		const next = duplicateChildIn(content, '5')
		expect(next.placements.map((c) => c.tileTitle ?? c.type)).toEqual(['Mail', 'Mail', 'text'])
		expect(next.placements[1]).toEqual({ type: 'tile', tileTitle: 'Mail', gridX: 0, gridY: 3, gridWidth: 2, gridHeight: 2 })
		expect(content.placements).toHaveLength(2)
		expect(next.tabs).toBe(content.tabs)
	})

	it('duplicates and removes a child inside a tab and a nested well', () => {
		const doubled = duplicateChildIn(content, 'deep')
		const inner = doubled.tabs[0].placements[0].content.placements
		expect(inner.map((c) => c.type)).toEqual(['clock', 'clock'])
		expect(inner[1].uuid).toBeUndefined()
		expect(content.tabs[0].placements[0].content.placements).toHaveLength(1)

		const removed = removeChildIn(content, 'w')
		expect(removed.tabs[0].placements).toEqual([])
		expect(removed.placements).toBe(content.placements)
	})

	it('changes nothing for an unknown key', () => {
		expect(duplicateChildIn(content, 'nope')).toBeNull()
		expect(removeChildIn(content, '')).toBeNull()
	})

	it('finds a child by its key at any depth', () => {
		expect(findChildIn(content, 'deep')).toEqual({ type: 'clock', uuid: 'deep' })
		expect(findChildIn(content, '5').tileTitle).toBe('Mail')
		expect(findChildIn(content, '')).toBeNull()
		expect(findChildIn(null, 'deep')).toBeNull()
	})

	it('patches a child in place, at any depth, touching nothing else', () => {
		const next = patchChildIn(content, { key: '12-1', patch: { content: { title: 'Hallo' }, customTitle: 'Groet' } })
		expect(next.placements[1]).toMatchObject({ uuid: '12-1', content: { title: 'Hallo' }, customTitle: 'Groet', gridHeight: 3 })
		expect(next.placements[0]).toBe(content.placements[0])
		expect(content.placements[1].content.title).toBe('Welkom')

		const deep = patchChildIn(content, { key: 'deep', patch: { content: { zone: 'UTC' } } })
		expect(deep.tabs[0].placements[0].content.placements[0]).toEqual({ type: 'clock', uuid: 'deep', content: { zone: 'UTC' } })
		expect(patchChildIn(content, { key: 'nope', patch: {} })).toBeNull()
	})
})
