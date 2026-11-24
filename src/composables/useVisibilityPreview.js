/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * useVisibilityPreview — "preview as audience/date" composable for the
 * conditional-visibility-editor change (REQ-CVUI-004).
 *
 * Given the editor's CURRENT (possibly unsaved) rule set and an
 * author-chosen `(groups, datetime)` context, calls the read-only
 * `POST /api/visibility/preview` endpoint and exposes the effective
 * visibility plus which rules matched. Forwards each rule's `ruleType` /
 * `ruleConfig` / `isInclude` exactly as the editor holds them — no
 * translation layer that could drift from the shape the rows emit and the
 * CRUD endpoints persist (REQ-CVUI-004 "same rule shape" scenario).
 */

import { reactive } from 'vue'
import { api } from '../services/api.js'

/**
 * The id the preview endpoint echoes back for a rule: its server id, or for
 * a never-saved draft its negative local key, so a matched draft can be
 * named without colliding with a stored rule.
 *
 * @param {object} rule one entry of the in-editor rule set
 * @return {number|null|undefined} the correlation id
 */
export function previewRuleId(rule) {
	return rule.id ?? rule._localKey
}

/**
 * @param {object} rule one entry of the in-editor rule set:
 *   `{id?: number, _localKey?: number, ruleType: string, ruleConfig: object, isInclude: boolean}`
 * @return {{ruleType: string, ruleConfig: object, isInclude: boolean, id: (number|null|undefined)}}
 */
function toPreviewRule(rule) {
	return {
		id: previewRuleId(rule),
		ruleType: rule.ruleType,
		ruleConfig: rule.ruleConfig,
		isInclude: rule.isInclude,
	}
}

/**
 * The preview request: the rules as the editor holds them, the context, and
 * — when given — who to view as, how the rules combine and the placement.
 *
 * @param {object[]} rules the in-editor rule set
 * @param {object} context `{groups?, datetime?, userId?, match?, placementId?}`
 * @return {object} the request body
 * @spec openspec/changes/visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md#requirement-the-rules-dialog-as-lpzichtbaarheid-draws-it-req-cvui-006
 */
export function previewBody(rules, context) {
	const given = context || {}
	const body = {
		rules: (rules || []).map(toPreviewRule),
		context: previewContext(given),
	}
	if (given.match) {
		body.match = given.match
	}
	if (given.placementId !== null && given.placementId !== undefined) {
		body.placementId = Number(given.placementId)
	}
	return body
}

/**
 * The preview context: the groups and the moment, and who to view as when
 * a person is chosen.
 *
 * @param {object} context `{groups?, datetime?, userId?}`
 * @return {object} the context to send
 * @spec openspec/changes/visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md#requirement-the-rules-dialog-as-lpzichtbaarheid-draws-it-req-cvui-006
 */
function previewContext(context) {
	const sent = {
		groups: context.groups || [],
		datetime: context.datetime || null,
	}
	if (context.userId) {
		sent.userId = context.userId
	}
	return sent
}

/**
 * Build the preview composable's reactive state + `runPreview` action.
 *
 * @return {{
 *   state: {loading: boolean, error: (Error|null), result: (object|null)},
 *   runPreview: (rules: object[], context: object) => Promise<object>,
 *   reset: () => void,
 * }}
 * @spec openspec/specs/conditional-visibility-editor/spec.md#requirement-req-cvui-004-preview-as-audience-and-date
 */
export function useVisibilityPreview() {
	const state = reactive({
		loading: false,
		error: null,
		result: null,
	})

	/**
	 * Run the preview for the given (unsaved-safe) rule set and context.
	 * Never persists anything — it only POSTs to the read-only preview
	 * endpoint.
	 *
	 * @param {object[]} rules the current in-editor rule set (unsaved edits
	 *   included — REQ-CVUI-004 "Preview evaluates unsaved edits")
	 * @param {object} context `{groups?: string[], datetime: string, userId?: string,
	 *   match?: 'any'|'all', placementId?: number}`; with `userId` the rules are
	 *   evaluated as that person (View as), `match` says how the include rules
	 *   combine, `placementId` names the placement whose rules these are
	 * @return {Promise<{visible: boolean, matchedIncludeRuleIds: number[], matchedExcludeRuleIds: number[]}>}
	 *   the preview result
	 * @spec openspec/specs/conditional-visibility-editor/spec.md#requirement-req-cvui-004-preview-as-audience-and-date
	 * @spec openspec/changes/visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md#requirement-the-rules-dialog-as-lpzichtbaarheid-draws-it-req-cvui-006
	 */
	async function runPreview(rules, context) {
		state.loading = true
		state.error = null
		try {
			const body = previewBody(rules, context)
			const { data } = await api.previewVisibility(body)
			const result = data && data.data ? data.data : data
			state.result = result
			return result
		} catch (error) {
			state.error = error
			state.result = null
			throw error
		} finally {
			state.loading = false
		}
	}

	/**
	 * Clear the last preview result/error — used when the editor's rule set
	 * changes enough that a stale verdict would mislead the author.
	 *
	 * @return {void}
	 */
	function reset() {
		state.loading = false
		state.error = null
		state.result = null
	}

	return { state, runPreview, reset }
}
