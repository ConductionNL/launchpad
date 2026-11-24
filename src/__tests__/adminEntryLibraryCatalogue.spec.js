/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Every webpack entry that renders library components registers the library
 * catalogue itself. The administration renders inside the main entry
 * (src/main.js), so that call covers its shell chrome too.
 */
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

describe('the library catalogue', () => {
	it.each(['main.js'])('is registered by src/%s', (entry) => {
		const source = readFileSync(resolve(__dirname, '..', entry), 'utf8')
		expect(source).toMatch(
			/import\s*\{[^}]*\bregisterTranslations\b[^}]*\}\s*from\s*'@conduction\/nextcloud-vue'/,
		)
		expect(source).toMatch(/^\s*registerTranslations\(\)/m)
	})
})
