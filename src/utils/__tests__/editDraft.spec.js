/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The edit-mode draft helpers: the change count of the draft pill and the
 * one request Save sends (REQ-EDIT-001, REQ-EDIT-002).
 *
 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-the-edit-bar-holds-a-draft-req-edit-001
 */

import { describe, expect, it } from 'vitest'
import {
	clonePlacements,
	draftChanges,
	draftRequestBody,
	draftRulesCount,
	isDraftPlacement,
	newDraftId,
	placementChanges,
	withDraftRules,
} from '../editDraft.js'

const stored = {
	columns: 12,
	placements: [
		{ id: 1, widgetId: 'text', gridX: 0, gridY: 0, gridWidth: 4, gridHeight: 2, content: { text: 'Hallo' }, version: 3 },
		{ id: 2, widgetId: 'clock', gridX: 4, gridY: 0, gridWidth: 2, gridHeight: 2, version: 1 },
		{ id: 3, widgetId: 'files', gridX: 0, gridY: 2, gridWidth: 6, gridHeight: 4, version: 0 },
	],
}

describe('editDraft', () => {
	it('counts nothing for an untouched draft', () => {
		const draft = { columns: 12, placements: clonePlacements(stored.placements) }
		expect(draftChanges(stored, draft).count).toBe(0)
	})

	it('counts each moved, configured, added and removed widget once, and the columns once', () => {
		const placements = clonePlacements(stored.placements)
		placements[0].gridY = 4
		placements[0].gridWidth = 6
		placements[1].content = { timezone: 'Europe/Amsterdam' }
		placements.splice(2, 1)
		placements.push({ id: newDraftId(), widgetId: 'weather', gridX: 0, gridY: 8, gridWidth: 4, gridHeight: 3 })
		const changes = draftChanges(stored, { columns: 8, placements })
		expect(changes.changed).toHaveLength(2)
		expect(changes.removed.map((p) => p.id)).toEqual([3])
		expect(changes.added).toHaveLength(1)
		expect(changes.columns).toBe(true)
		expect(changes.count).toBe(5)
	})

	it('compares JSON text and objects, and booleans as 0 or 1, by value', () => {
		expect(placementChanges({ content: '{"a":1}', isVisible: 1 }, { content: { a: 1 }, isVisible: true })).toEqual({})
		expect(placementChanges({ content: '{"a":1}' }, { content: { a: 2 } })).toEqual({ content: { a: 2 } })
	})

	it('marks a draft id', () => {
		expect(isDraftPlacement({ id: newDraftId() })).toBe(true)
		expect(isDraftPlacement({ id: 4 })).toBe(false)
		expect(isDraftPlacement(null)).toBe(false)
	})

	// @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-save-stores-the-whole-draft-at-once-req-edit-002
	it('sends the full list: kept widgets by id, changed ones with their version, new ones by type', () => {
		const placements = clonePlacements(stored.placements)
		placements[0].gridY = 4
		placements.splice(2, 1)
		placements.push({ id: 'draft-x', widgetId: 'weather', gridX: 0, gridY: 8, gridWidth: 4, gridHeight: 3, customTitle: 'Weer' })
		expect(draftRequestBody(stored, { columns: 8, placements })).toEqual({
			draft: true,
			gridColumns: 8,
			placements: [
				{ id: 1, gridY: 4, version: 3 },
				{ id: 2 },
				{ widgetId: 'weather', gridX: 0, gridY: 8, gridWidth: 4, gridHeight: 3, customTitle: 'Weer' },
			],
		})
	})
})

// @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-the-edit-bar-holds-a-draft-req-edit-001
describe('editDraft — visibility rules in the draft', () => {
	it('counts the widgets whose rules the draft changed, not removed ones', () => {
		const draft = { rules: { 1: [], 2: [{ ruleType: 'group' }], 9: [] } }
		expect(draftRulesCount(draft, [{ id: 1 }, { id: 2 }])).toBe(2)
		expect(draftRulesCount({}, [{ id: 1 }])).toBe(0)
	})

	it('puts each changed widget\'s rules on its placement in the Save body', () => {
		const body = { draft: true, gridColumns: 12, placements: [{ id: 1 }, { id: 2, gridY: 3 }, { widgetId: 'text' }] }
		const draft = { rules: { 2: [{ id: 5, ruleType: 'group', ruleConfig: { groups: ['hr'] }, isInclude: false, _inDraft: true }] } }
		expect(withDraftRules(body, draft).placements).toEqual([
			{ id: 1 },
			{ id: 2, gridY: 3, rules: [{ ruleType: 'group', ruleConfig: { groups: ['hr'] }, isInclude: false }] },
			{ widgetId: 'text' },
		])
		expect(withDraftRules(body, {}).placements).toEqual(body.placements)
	})
})
