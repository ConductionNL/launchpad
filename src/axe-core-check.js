/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The build's accessibility gate for embeds (`npm run check:a11y`, called by
 * `npm run build`). It renders representative widget shapes (chart, table,
 * text, image) through the real embed page in jsdom, runs axe-core on each
 * (tests/a11y/embedWidgetShapes.a11y.spec.js, which writes
 * docs/accessibility/axe-results.json), then fails on serious or critical
 * violations and warns on moderate or minor ones.
 *
 * jsdom has no layout, so axe cannot measure colour contrast here; it lists
 * that rule as incomplete. Theme contrast is enforced when a tenant theme is
 * saved (TenantThemeService::validateContrastRatio).
 *
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-010--wcag-21-aa-compliance-enforced-in-embed-rendering
 */

import { readFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import process from 'node:process'
import { fileURLToPath, pathToFileURL } from 'node:url'

/** The axe impacts that fail the build (high and critical). */
export const BLOCKING_IMPACTS = ['critical', 'serious']

/** The axe rule tags the check runs: WCAG 2.0 and 2.1, levels A and AA. */
export const WCAG_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']

/**
 * Split a report's violations into those that fail the build and those that
 * only warn. A violation without a known impact blocks.
 *
 * @param {{shapes: Array<{shape: string, violations: Array<object>}>}} report The report the render spec writes.
 * @return {{blocking: Array<object>, warnings: Array<object>}} One entry per shape and rule.
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-010--wcag-21-aa-compliance-enforced-in-embed-rendering
 */
export function summarise(report) {
	const blocking = []
	const warnings = []
	for (const { shape, violations } of report?.shapes ?? []) {
		for (const violation of violations ?? []) {
			const entry = { shape, id: violation.id, impact: violation.impact, help: violation.help, nodes: violation.nodes }
			const isWarning = ['moderate', 'minor'].includes(violation.impact)
			;(isWarning ? warnings : blocking).push(entry)
		}
	}
	return { blocking, warnings }
}

/**
 * A violation as one line of build output.
 *
 * @param {{shape: string, id: string, impact: string, help: string, nodes: number}} entry The violation.
 * @return {string}
 */
export function formatEntry(entry) {
	return `${entry.shape}: ${entry.id} (${entry.impact ?? 'unknown'}, ${entry.nodes} element(s)): ${entry.help}`
}

/**
 * Print one line of the check's output.
 *
 * @param {NodeJS.WriteStream} stream Where to print it.
 * @param {string} line The line.
 */
function say(stream, line) {
	stream.write(`[check:a11y] ${line}\n`)
}

/**
 * Render the shapes, then judge the report. Exits 1 when the render spec
 * fails or any blocking violation is found.
 *
 * @return {Promise<void>}
 */
async function main() {
	const root = resolve(dirname(fileURLToPath(import.meta.url)), '..')
	const reportPath = resolve(root, 'docs/accessibility/axe-results.json')
	const { startVitest } = await import('vitest/node')
	const vitest = await startVitest('test', [], {
		root,
		config: resolve(root, 'vitest.a11y.config.js'),
		run: true,
		watch: false,
	})
	const renderFailed = (process.exitCode ?? 0) !== 0
	await vitest?.close()
	if (renderFailed) {
		say(process.stderr, 'Rendering the widget shapes failed; see the test output above.')
		process.exit(1)
	}

	const { blocking, warnings } = summarise(JSON.parse(readFileSync(reportPath, 'utf8')))
	warnings.forEach((entry) => say(process.stderr, `warning ${formatEntry(entry)}`))
	blocking.forEach((entry) => say(process.stderr, `violation ${formatEntry(entry)}`))
	if (blocking.length > 0) {
		say(process.stderr, `${blocking.length} serious or critical violation(s); report in docs/accessibility/axe-results.json`)
		process.exit(1)
	}
	say(process.stdout, `No serious or critical violations, ${warnings.length} warning(s); report in docs/accessibility/axe-results.json`)
}

if (process.argv[1] && import.meta.url === pathToFileURL(resolve(process.argv[1])).href) {
	main().catch((error) => {
		say(process.stderr, String(error?.stack ?? error))
		process.exit(1)
	})
}
