/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A key appears once in the Dutch catalogues. Parallel pull requests each
 * appended the same key; git merged both lines without a conflict, and the
 * LAST copy won at runtime. In nl.js that made "Set as default" read
 * "Instellen als standaard" and "View mode" read "Weergave", while nl.json
 * and the rest of the app said otherwise. JSON.parse and a JS object cannot
 * see a duplicate, so this reads the raw text. Approach copied from
 * nextcloud-vue tests/l10n/catalogueNoDuplicateKeys.spec.js.
 */
import { readFileSync } from 'fs'
import { join } from 'path'
import { describe, expect, it } from 'vitest'

const DIR = join(__dirname, '../../l10n')

/**
 * The keys that occur more than once in one object of a JSON-shaped text.
 *
 * @param {string} text The raw object text.
 * @return {string[]} The duplicated keys.
 */
function duplicateKeys(text) {
	const duplicates = []
	const seenStack = [new Set()]
	const re = /"((?:[^"\\]|\\.)*)"\s*:|[{}]/g
	let match
	while ((match = re.exec(text)) !== null) {
		if (match[0] === '{') {
			seenStack.push(new Set())
		} else if (match[0] === '}') {
			seenStack.pop()
		} else {
			const seen = seenStack[seenStack.length - 1]
			if (seen.has(match[1])) {
				duplicates.push(match[1])
			}
			seen.add(match[1])
		}
	}
	return duplicates
}

describe('Dutch l10n catalogues', () => {
	it('nl.json has no key twice', () => {
		const text = readFileSync(join(DIR, 'nl.json'), 'utf8')
		JSON.parse(text)
		expect(duplicateKeys(text)).toEqual([])
	})

	it('nl.js has no key twice', () => {
		const text = readFileSync(join(DIR, 'nl.js'), 'utf8')
		// The translations object: from the first "{" to the "}" before the plural rule.
		const body = text.slice(text.indexOf('{'), text.lastIndexOf('},'))
		expect(duplicateKeys(body)).toEqual([])
	})

	it('finds a duplicate when there is one', () => {
		expect(duplicateKeys('{"a": "1", "b": "2", "a": "3"}')).toEqual(['a'])
	})
})
