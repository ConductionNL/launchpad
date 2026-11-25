/**
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * The English browser catalogue carries only what changes a string.
 *
 * Every page loads l10n/en.js for an English reader. An entry that maps a
 * string to itself changes nothing, because a missing key already reads as
 * the key, yet it cost 147 KB on every page (ADR-124 speed budget). So en.js
 * keeps only renamed message ids and plurals, and l10n/en.json holds every
 * English string: that is the source the parity check and the translators
 * read.
 */

import * as fs from 'fs'
import * as path from 'path'
import { describe, expect, it } from 'vitest'
import * as vm from 'vm'

const L10N = path.resolve(__dirname, '../../l10n')

/**
 * Loads an OC.L10N.register(...) catalogue the way the browser does.
 *
 * @param file The catalogue file name under l10n/.
 * @return The registered translations.
 */
function loadBrowserCatalogue(file: string): Record<string, string | string[]> {
	let captured: Record<string, string | string[]> = {}
	const sandbox = { OC: { L10N: { register: (_app: string, translations: Record<string, string | string[]>) => { captured = translations } } } }
	vm.createContext(sandbox)
	vm.runInContext(fs.readFileSync(path.join(L10N, file), 'utf8'), sandbox)
	return captured
}

const enJs = loadBrowserCatalogue('en.js')
const enJson = JSON.parse(fs.readFileSync(path.join(L10N, 'en.json'), 'utf8')).translations
const nlJs = loadBrowserCatalogue('nl.js')

describe('l10n/en.js', () => {
	it('holds no entry that maps a string to itself', () => {
		const identity = Object.keys(enJs).filter((key) => enJs[key] === key)
		expect(identity).toEqual([])
	})

	it('stays small', () => {
		expect(fs.statSync(path.join(L10N, 'en.js')).size).toBeLessThan(16 * 1024)
	})

	it('still answers the renamed message ids in English', () => {
		expect(enJs.share_not_found).toBe('Share not found')
		expect(enJs['_%n member_::_%n members_']).toEqual(['%n member', '%n members'])
	})
})

describe('l10n/en.json', () => {
	it('holds the English strings en.js used to carry alone', () => {
		for (const key of ['Look', 'Accent color', 'Custom CSS', 'Enter colors like #1f5c3a.']) {
			expect(enJson[key]).toBe(key)
		}
	})

	it('keeps the Dutch translation of those strings for the server side', () => {
		const nlJson = JSON.parse(fs.readFileSync(path.join(L10N, 'nl.json'), 'utf8')).translations
		expect(nlJson['Accent color']).toBe(nlJs['Accent color'])
		expect(nlJson['Accent color']).not.toBe('Accent color')
	})
})
