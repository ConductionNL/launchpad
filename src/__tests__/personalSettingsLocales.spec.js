/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The personal settings dialog (summary email, hidden widgets) and the
 * summary mail read in every shipped language: each locale's .json and .js
 * catalogue carries every string, with its placeholders, and the plural line
 * has one form per plural of the locale.
 *
 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
 */

import fs from 'fs'
import path from 'path'
import { describe, expect, it } from 'vitest'

const PLURAL = '_You have hidden %n widget on shared dashboards_::_You have hidden %n widgets on shared dashboards_'
const KEYS = [
	'Hidden widgets',
	'Your hidden widgets could not be loaded.',
	'You have not hidden any widgets on shared dashboards.',
	PLURAL,
	'Show all again',
	'Your change could not be saved.',
	'Summary by email',
	'A mail with new announcements and what changed on your dashboards',
	'How often?',
	'Daily',
	'Weekly',
	'At 07:30, only when something changed',
	'On Monday at 07:30',
	'You have no email address, so no summary can be sent to you. Add one in your Nextcloud account.',
	'Your daily LaunchPad summary',
	'Your weekly LaunchPad summary',
	'Nothing new was published and nothing changed on your dashboards.',
	'New announcements',
	'What changed on your dashboards',
	'Open LaunchPad',
	'You get this mail because you asked for it in your LaunchPad settings.',
]

const L10N = path.resolve(__dirname, '../../l10n')
const LOCALES = fs.readdirSync(L10N)
	.filter((file) => /^[a-z]{2}(_[A-Z]{2})?\.json$/.test(file) && file !== 'en.json')
	.map((file) => file.slice(0, -5))

/**
 * Read a locale's .js catalogue the way Nextcloud registers it.
 *
 * @param {string} locale The locale.
 * @return {{translations: object, nplurals: number}}
 */
function readJs(locale) {
	let registered = null
	const OC = { L10N: { register: (_app, translations, pluralForm) => { registered = { translations, pluralForm } } } }
	new Function('OC', fs.readFileSync(path.join(L10N, `${locale}.js`), 'utf8'))(OC)
	return { translations: registered.translations, nplurals: Number(/nplurals=(\d+)/.exec(registered.pluralForm)[1]) }
}

const placeholders = (text) => [...text.matchAll(/%n|\{\w+\}/g)].map((m) => m[0]).sort()

describe('Personal settings strings in every shipped locale', () => {
	it('covers the shipped locales', () => {
		expect(LOCALES.length).toBeGreaterThanOrEqual(36)
	})

	it.each(LOCALES)('%s translates every string in .json and .js', (locale) => {
		const json = JSON.parse(fs.readFileSync(path.join(L10N, `${locale}.json`), 'utf8')).translations
		const js = readJs(locale)
		for (const catalogue of [json, js.translations]) {
			for (const key of KEYS.filter((k) => k !== PLURAL)) {
				expect(typeof catalogue[key], `${locale}: ${key}`).toBe('string')
				expect(catalogue[key]).not.toBe('')
				expect(placeholders(catalogue[key])).toEqual(placeholders(key))
			}
			expect(Array.isArray(catalogue[PLURAL]), `${locale}: plural`).toBe(true)
			expect(catalogue[PLURAL]).toHaveLength(js.nplurals)
			catalogue[PLURAL].forEach((form) => expect(form).toContain('%n'))
		}
	})
})
