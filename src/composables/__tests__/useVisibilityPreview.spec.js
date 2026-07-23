/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `useVisibilityPreview.js`
 * (conditional-visibility-editor spec, REQ-CVUI-004). Covers:
 *  - posts to POST /api/visibility/preview with the editor's rule shape
 *    unchanged (no translation layer)
 *  - maps the response into `state.result`
 *  - surfaces errors via `state.error` without throwing out of the caller
 *  - `reset()` clears loading/error/result
 */

import { describe, it, expect, beforeEach, vi } from 'vitest'
import { useVisibilityPreview } from '../useVisibilityPreview.js'
import { api } from '../../services/api.js'

vi.mock('@nextcloud/axios', () => ({
	default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))
vi.mock('@nextcloud/router', () => ({ generateUrl: (p) => `/index.php${p}` }))

vi.mock('../../services/api.js', () => ({
	api: {
		previewVisibility: vi.fn(),
	},
}))

beforeEach(() => {
	vi.clearAllMocks()
})

describe('useVisibilityPreview', () => {
	it('posts the editor rule set unchanged and the supplied context', async () => {
		api.previewVisibility.mockResolvedValue({
			data: { visible: true, matchedIncludeRuleIds: [1], matchedExcludeRuleIds: [] },
		})

		const { runPreview } = useVisibilityPreview()
		const rules = [
			{ id: 1, ruleType: 'group', ruleConfig: { groups: ['marketing'] }, isInclude: true, _localKey: undefined },
		]

		await runPreview(rules, { groups: ['marketing'], datetime: '2026-07-15T10:00:00.000Z' })

		expect(api.previewVisibility).toHaveBeenCalledWith({
			rules: [
				{ id: 1, ruleType: 'group', ruleConfig: { groups: ['marketing'] }, isInclude: true },
			],
			context: { groups: ['marketing'], datetime: '2026-07-15T10:00:00.000Z' },
		})
	})

	it('sends the exact ruleConfig object reference — no translation layer', async () => {
		api.previewVisibility.mockResolvedValue({ data: { visible: true, matchedIncludeRuleIds: [], matchedExcludeRuleIds: [] } })
		const { runPreview } = useVisibilityPreview()
		const ruleConfig = { startTime: '09:00', endTime: '17:00', days: ['mon'] }
		const rules = [{ id: 2, ruleType: 'time', ruleConfig, isInclude: true }]

		await runPreview(rules, { groups: [], datetime: null })

		const sentRule = api.previewVisibility.mock.calls[0][0].rules[0]
		expect(sentRule.ruleConfig).toBe(ruleConfig)
	})

	it('maps the response into state.result and clears loading', async () => {
		const result = { visible: false, matchedIncludeRuleIds: [], matchedExcludeRuleIds: [3] }
		api.previewVisibility.mockResolvedValue({ data: result })

		const { state, runPreview } = useVisibilityPreview()
		expect(state.loading).toBe(false)

		const promise = runPreview([], { groups: [], datetime: null })
		expect(state.loading).toBe(true)
		await promise

		expect(state.loading).toBe(false)
		expect(state.result).toEqual(result)
		expect(state.error).toBeNull()
	})

	it('defaults groups to [] and datetime to null when the context omits them', async () => {
		api.previewVisibility.mockResolvedValue({ data: { visible: true, matchedIncludeRuleIds: [], matchedExcludeRuleIds: [] } })
		const { runPreview } = useVisibilityPreview()

		await runPreview([], {})

		expect(api.previewVisibility).toHaveBeenCalledWith({
			rules: [],
			context: { groups: [], datetime: null },
		})
	})

	it('surfaces a failed request via state.error and rethrows', async () => {
		const error = new Error('boom')
		api.previewVisibility.mockRejectedValue(error)

		const { state, runPreview } = useVisibilityPreview()
		await expect(runPreview([], { groups: [], datetime: null })).rejects.toThrow('boom')

		expect(state.error).toBe(error)
		expect(state.result).toBeNull()
		expect(state.loading).toBe(false)
	})

	it('reset() clears loading/error/result', async () => {
		api.previewVisibility.mockResolvedValue({ data: { visible: true, matchedIncludeRuleIds: [], matchedExcludeRuleIds: [] } })
		const { state, runPreview, reset } = useVisibilityPreview()
		await runPreview([], { groups: [], datetime: null })
		expect(state.result).not.toBeNull()

		reset()

		expect(state.loading).toBe(false)
		expect(state.error).toBeNull()
		expect(state.result).toBeNull()
	})
})
