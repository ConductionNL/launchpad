/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Golden test for the styles of RolePermissionsSection and
 * RoleLayoutDefaultsSection: every rule each component gets from its style
 * blocks (a `src` block read from its file), whether the block is scoped,
 * and for each selector that extends another (`.a` and `.a--b`) which comes
 * first, compared with fixtures/roleSectionStyles.golden.json.
 * Set RECORD_GOLDEN=1 to rewrite it.
 */

import fs from 'node:fs'
import path from 'node:path'
import { describe, expect, it } from 'vitest'

const FIXTURE = path.resolve(__dirname, 'fixtures/roleSectionStyles.golden.json')
const ADMIN = path.resolve(__dirname, '..')
const COMPONENTS = ['RolePermissionsSection.vue', 'RoleLayoutDefaultsSection.vue']

/**
 * The style blocks of a single-file component, in order, `src` blocks read
 * from their file.
 *
 * @param {string} file The component file name.
 * @return {Array<{scoped: boolean, css: string}>} The blocks.
 */
function styleBlocks(file) {
	const source = fs.readFileSync(path.join(ADMIN, file), 'utf8')
	const blocks = []
	for (const match of source.matchAll(/<style([^>]*)>([\s\S]*?)<\/style>/g)) {
		const attrs = match[1]
		const src = /src="([^"]+)"/.exec(attrs)
		blocks.push({
			scoped: /\bscoped\b/.test(attrs),
			css: src ? fs.readFileSync(path.join(ADMIN, src[1]), 'utf8') : match[2],
		})
	}
	return blocks
}

/**
 * The rules of a stylesheet as selector and normalised declarations.
 *
 * @param {string} css The stylesheet.
 * @return {Array<Array<string>>} Pairs of selector and declarations.
 */
function rules(css) {
	const out = []
	const text = css.replace(/\/\*[\s\S]*?\*\//g, '')
	for (const match of text.matchAll(/([^{}]+)\{([^{}]*)\}/g)) {
		const declarations = match[2]
			.split(';')
			.map((d) => d.trim().replace(/\s+/g, ' '))
			.filter(Boolean)
			.join('; ')
		out.push([match[1].trim().replace(/\s+/g, ' '), declarations])
	}
	return out
}

/**
 * What one component gets: its rules by selector, the scoped flags, and the
 * order of selectors that extend one another.
 *
 * @param {string} file The component file name.
 * @return {object} The contract.
 */
function contract(file) {
	const blocks = styleBlocks(file)
	const all = blocks.flatMap((block) =>
		rules(block.css).map(([selector, body]) => ({ selector, body, scoped: block.scoped })),
	)
	const bySelector = {}
	for (const rule of all) {
		bySelector[rule.selector] = { body: rule.body, scoped: rule.scoped }
	}
	const selectors = all.map((rule) => rule.selector)
	const order = []
	for (const base of selectors) {
		for (const extended of selectors) {
			if (extended !== base && extended.startsWith(base)) {
				order.push(
					`${base} ${selectors.indexOf(base) < selectors.indexOf(extended) ? 'before' : 'after'} ${extended}`,
				)
			}
		}
	}
	return {
		rules: Object.fromEntries(Object.entries(bySelector).sort()),
		count: all.length,
		order: order.sort(),
	}
}

describe('admin role section styles (golden)', () => {
	it('matches the recorded rules case by case', () => {
		const actual = Object.fromEntries(COMPONENTS.map((file) => [file, contract(file)]))
		if (process.env.RECORD_GOLDEN) {
			fs.writeFileSync(FIXTURE, JSON.stringify(actual, null, '\t') + '\n')
		}
		const expected = JSON.parse(fs.readFileSync(FIXTURE, 'utf8'))
		expect(Object.keys(actual)).toEqual(Object.keys(expected))
		for (const [name, value] of Object.entries(expected)) {
			expect(actual[name], name).toEqual(value)
		}
	})
})
