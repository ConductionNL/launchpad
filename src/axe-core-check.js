/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The accessibility check of the embed render (embedded-analytics task 12):
 * axe-core runs over a rendered element and its findings are sorted by
 * impact. Serious and critical violations fail the check, moderate and minor
 * ones are reported as warnings.
 *
 * jsdom has no layout, so axe reports colour contrast as incomplete rather
 * than as a violation; contrast is covered by the tenant theme's 4.5:1 check
 * (`TenantThemeValidator`).
 *
 * @spec openspec/changes/embedded-analytics/tasks.md#task-12
 */

import axe from 'axe-core'

export const FAILING_IMPACTS = ['serious', 'critical']

export const RULE_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice']

/**
 * Run axe-core over an element of the current document.
 *
 * @param {Element} element The rendered element.
 * @return {Promise<object>} The axe results.
 * @spec openspec/changes/embedded-analytics/tasks.md#task-12
 */
export function runAxe(element) {
	return axe.run(element, {
		runOnly: { type: 'tag', values: RULE_TAGS },
		resultTypes: ['violations'],
	})
}

/**
 * Sort axe violations into failures (serious, critical) and warnings
 * (moderate, minor), each as `{id, impact, help, nodes}` with the offending
 * nodes' selectors.
 *
 * @param {object} results The axe results.
 * @return {{failures: Array<object>, warnings: Array<object>}}
 * @spec openspec/changes/embedded-analytics/tasks.md#task-12
 */
export function classify(results) {
	const findings = (results.violations || []).map((violation) => ({
		id: violation.id,
		impact: violation.impact || 'minor',
		help: violation.help,
		nodes: violation.nodes.map((node) => node.target.join(' ')),
	}))
	return {
		failures: findings.filter((finding) => FAILING_IMPACTS.includes(finding.impact)),
		warnings: findings.filter((finding) => !FAILING_IMPACTS.includes(finding.impact)),
	}
}
