/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The edit-mode draft (edit-mode-draft-and-save, design D1/D2): what changed
 * between the placements stored when edit mode began and the draft, how many
 * changes the draft pill counts, and the one request Save sends.
 *
 * @spec openspec/changes/edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-the-edit-bar-holds-a-draft-req-edit-001
 */

/**
 * The placement fields a draft may change; the server takes the same ones
 * (`WidgetPlacement::EDITABLE_FIELDS`).
 */
export const DRAFT_FIELDS = Object.freeze([
	'gridX',
	'gridY',
	'gridWidth',
	'gridHeight',
	'isVisible',
	'showTitle',
	'customTitle',
	'customIcon',
	'styleConfig',
	'content',
	'calculatedFields',
	'tileTitle',
	'tileIcon',
	'tileIconType',
	'tileBackgroundColor',
	'tileTextColor',
	'tileLinkType',
	'tileLinkValue',
	'requiresAcknowledgement',
	'acknowledgementPrompt',
	'acknowledgementDeadline',
	'reacknowledgeOnChange',
	'acknowledgementContentVersion',
	'ruleMatch',
])

/** The id prefix of a widget the draft added and the server has not stored. */
const DRAFT_ID_PREFIX = 'draft-'

let draftCounter = 0

/**
 * A new id for a widget the draft adds.
 *
 * @return {string}
 * @spec openspec/changes/edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-the-edit-bar-holds-a-draft-req-edit-001
 */
export function newDraftId() {
	draftCounter += 1
	return `${DRAFT_ID_PREFIX}${draftCounter}`
}

/**
 * Whether a placement is one the draft added (not stored yet).
 *
 * @param {object|null} placement The placement.
 * @return {boolean}
 * @spec openspec/changes/edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
 */
export function isDraftPlacement(placement) {
	return typeof placement?.id === 'string' && placement.id.startsWith(DRAFT_ID_PREFIX)
}

/**
 * A deep copy of a placement list, so the draft never shares objects with
 * the stored state.
 *
 * @param {Array<object>} placements The placements.
 * @return {Array<object>}
 * @spec openspec/changes/edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-the-edit-bar-holds-a-draft-req-edit-001
 */
export function clonePlacements(placements) {
	return JSON.parse(JSON.stringify(Array.isArray(placements) ? placements : []))
}

/**
 * A value as compared: JSON text decoded, booleans as 0 or 1, then as JSON.
 *
 * @param {*} value The value.
 * @return {string}
 */
function comparable(value) {
	let normal = value
	if (typeof normal === 'boolean') {
		normal = normal ? 1 : 0
	}
	if (typeof normal === 'string' && (normal.startsWith('{') || normal.startsWith('['))) {
		try {
			normal = JSON.parse(normal)
		} catch {
			// Not JSON after all: compare the text.
		}
	}
	return JSON.stringify(normal ?? null)
}

/**
 * The draft fields of a placement that differ from the stored one.
 *
 * @param {object} stored The placement as stored when edit mode began.
 * @param {object} draft The placement in the draft.
 * @return {object} The changed fields; empty when nothing changed.
 * @spec openspec/changes/edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-the-edit-bar-holds-a-draft-req-edit-001
 */
export function placementChanges(stored, draft) {
	const changes = {}
	for (const field of DRAFT_FIELDS) {
		if (field in draft && comparable(stored[field]) !== comparable(draft[field])) {
			changes[field] = draft[field]
		}
	}
	return changes
}

/**
 * What the draft changed: the widgets added, removed and changed (each
 * once), and whether the column count changed.
 *
 * @param {{placements: Array<object>, columns: number}} stored The state when edit mode began.
 * @param {{placements: Array<object>, columns: number}} draft The draft.
 * @return {{added: Array<object>, removed: Array<object>, changed: Array<{placement: object, fields: object}>, columns: boolean, count: number}}
 * @spec openspec/changes/edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-the-edit-bar-holds-a-draft-req-edit-001
 */
export function draftChanges(stored, draft) {
	const storedById = new Map(stored.placements.map((placement) => [String(placement.id), placement]))
	const draftIds = new Set(draft.placements.map((placement) => String(placement.id)))
	const added = draft.placements.filter((placement) => !storedById.has(String(placement.id)))
	const removed = stored.placements.filter((placement) => !draftIds.has(String(placement.id)))
	const changed = []
	for (const placement of draft.placements) {
		const before = storedById.get(String(placement.id))
		if (before) {
			const fields = placementChanges(before, placement)
			if (Object.keys(fields).length > 0) {
				changed.push({ placement, fields })
			}
		}
	}
	const columns = Number(stored.columns) !== Number(draft.columns)
	return {
		added,
		removed,
		changed,
		columns,
		count: added.length + removed.length + changed.length + (columns ? 1 : 0),
	}
}

/**
 * The body Save sends: the grid columns and the full placement list, a
 * stored widget with its id (and the version it was drafted on when it
 * changed) plus its changed fields, a new one with its type and fields.
 *
 * @param {{placements: Array<object>, columns: number}} stored The state when edit mode began.
 * @param {{placements: Array<object>, columns: number}} draft The draft.
 * @return {{draft: true, gridColumns: number, placements: Array<object>}}
 * @spec openspec/changes/edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-save-stores-the-whole-draft-at-once-req-edit-002
 */
export function draftRequestBody(stored, draft) {
	const storedById = new Map(stored.placements.map((placement) => [String(placement.id), placement]))
	const placements = draft.placements.map((placement) => {
		const before = storedById.get(String(placement.id))
		if (!before) {
			const body = { widgetId: placement.widgetId }
			for (const field of DRAFT_FIELDS) {
				if (placement[field] !== undefined) {
					body[field] = placement[field]
				}
			}
			return body
		}
		const fields = placementChanges(before, placement)
		const body = { id: placement.id, ...fields }
		if (Object.keys(fields).length > 0 && before.version !== undefined) {
			body.version = before.version
		}
		return body
	})
	return { draft: true, gridColumns: Number(draft.columns), placements }
}
