/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * launchpad carries no model client (launchpad-ai-dashboard-assistant
 * REQ-ADA-004): no LLM SDK import and no direct model URL in a string
 * anywhere in src/ (a comment naming the rule is fine).
 */

import { readdirSync, readFileSync, statSync } from 'node:fs'
import { join } from 'node:path'
import { describe, expect, it } from 'vitest'

/**
 * @param {string} dir Directory.
 * @return {Array<string>} Source files under it, tests excluded.
 */
function sources(dir) {
	return readdirSync(dir).flatMap((name) => {
		const path = join(dir, name)
		if (statSync(path).isDirectory()) {
			return name === '__tests__' ? [] : sources(path)
		}
		return /\.(js|vue|ts)$/.test(name) ? [path] : []
	})
}

describe('no model client in launchpad', () => {
	it('REQ-ADA-004: no LLM SDK import and no direct model URL', () => {
		const pattern =
			/from\s+['"](openai|@anthropic-ai[^'"]*|@ollama[^'"]*|ollama|llphant[^'"]*)['"]|['"`][^'"`\n]*localhost:11434/
		const code = (file) =>
			readFileSync(file, 'utf8')
				.replace(/\/\*[\s\S]*?\*\//g, '')
				.replace(/^\s*\/\/.*$/gm, '')
		const offenders = sources(join(process.cwd(), 'src')).filter((file) =>
			pattern.test(code(file)),
		)
		expect(offenders).toEqual([])
	})
})
