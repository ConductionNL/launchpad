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
 * @param {object} rule one entry of the in-editor rule set:
 *   `{id?: number, ruleType: string, ruleConfig: object, isInclude: boolean}`
 * @return {{ruleType: string, ruleConfig: object, isInclude: boolean, id: (number|undefined)}}
 */
function toPreviewRule(rule) {
	return {
		id: rule.id,
		ruleType: rule.ruleType,
		ruleConfig: rule.ruleConfig,
		isInclude: rule.isInclude,
	}
}

/**
 * Build the preview composable's reactive state + `runPreview` action.
 *
 * @return {{
 *   state: {loading: boolean, error: (Error|null), result: (object|null)},
 *   runPreview: (rules: object[], context: {groups: string[], datetime: string}) => Promise<object>,
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
	 * @param {object} context `{groups: string[], datetime: string}`
	 * @return {Promise<{visible: boolean, matchedIncludeRuleIds: number[], matchedExcludeRuleIds: number[]}>}
	 *   the preview result
	 * @spec openspec/specs/conditional-visibility-editor/spec.md#requirement-req-cvui-004-preview-as-audience-and-date
	 */
	async function runPreview(rules, context) {
		state.loading = true
		state.error = null
		try {
			const body = {
				rules: (rules || []).map(toPreviewRule),
				context: {
					groups: (context && context.groups) || [],
					datetime: (context && context.datetime) || null,
				},
			}
			const { data } = await api.previewVisibility(body)
			const result = (data && data.data) ? data.data : data
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
