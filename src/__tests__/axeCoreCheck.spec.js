/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The build's accessibility gate: serious and critical axe violations fail
 * the build, moderate and minor ones only warn (embedded-analytics task 12).
 *
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-010--wcag-21-aa-compliance-enforced-in-embed-rendering
 */

import { describe, expect, it } from 'vitest'
import { BLOCKING_IMPACTS, formatEntry, summarise } from '../axe-core-check.js'

/**
 * A violation as the render spec records it.
 *
 * @param {string} id The axe rule.
 * @param {string|null} impact The axe impact.
 * @return {object}
 */
const violation = (id, impact) => ({ id, impact, help: `${id} help`, nodes: 2 })

describe('summarise', () => {
	it('blocks on critical and serious violations and warns on moderate and minor ones', () => {
		const { blocking, warnings } = summarise({
			shapes: [
				{ shape: 'image', violations: [violation('image-alt', 'critical'), violation('list', 'moderate')] },
				{ shape: 'table', violations: [violation('th-has-data-cells', 'serious'), violation('label-title-only', 'minor')] },
			],
		})
		expect(blocking.map((e) => `${e.shape}:${e.id}`)).toEqual(['image:image-alt', 'table:th-has-data-cells'])
		expect(warnings.map((e) => `${e.shape}:${e.id}`)).toEqual(['image:list', 'table:label-title-only'])
		expect(BLOCKING_IMPACTS).toEqual(['critical', 'serious'])
	})

	it('blocks on a violation without a known impact', () => {
		expect(summarise({ shapes: [{ shape: 'text', violations: [violation('new-rule', null)] }] }).blocking).toHaveLength(1)
	})

	it('finds nothing in a clean or empty report', () => {
		expect(summarise({ shapes: [{ shape: 'chart', violations: [] }] })).toEqual({ blocking: [], warnings: [] })
		expect(summarise(undefined)).toEqual({ blocking: [], warnings: [] })
	})
})

describe('formatEntry', () => {
	it('names the shape, rule, impact and element count', () => {
		expect(formatEntry({ shape: 'image', ...violation('image-alt', 'critical') }))
			.toBe('image: image-alt (critical, 2 element(s)): image-alt help')
	})
})
