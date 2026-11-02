/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Golden test for the props contract of every widget renderer and form: per
 * prop its type, whether it is required, and its default (a factory is
 * called twice, recording the value and whether the two calls give separate
 * objects). Compared with fixtures/widgetProps.golden.json case by case.
 * Set RECORD_GOLDEN=1 to rewrite it.
 */

import fs from 'node:fs'
import path from 'node:path'
import { describe, expect, it } from 'vitest'

const FIXTURE = path.resolve(__dirname, 'fixtures/widgetProps.golden.json')

const COMPONENTS = import.meta.glob('../*.vue')

/**
 * The type of a prop as names.
 *
 * @param {unknown} type The prop's `type` option.
 * @return {Array<string>|string|null} The constructor names.
 */
function typeNames(type) {
	if (Array.isArray(type)) {
		return type.map((entry) => entry.name)
	}
	return type ? type.name : null
}

/**
 * The recorded contract of one prop definition.
 *
 * @param {object|Function} definition The prop definition.
 * @return {object} Its type, required flag and default.
 */
function contractOf(definition) {
	if (typeof definition === 'function') {
		return { type: definition.name }
	}
	const contract = {
		type: typeNames(definition.type),
		required: definition.required === true,
	}
	if (!('default' in definition)) {
		return contract
	}
	const value = definition.default
	if (typeof value === 'function' && definition.type !== Function) {
		const first = value()
		const second = value()
		contract.default = first === undefined ? '(undefined)' : first
		contract.fresh = typeof first === 'object' && first !== null ? first !== second : 'n/a'
		contract.frozen = Object.isFrozen(first)
	} else {
		contract.default = value
	}
	return contract
}

describe('widget renderer and form props (golden)', () => {
	it('matches the recorded contracts case by case', async () => {
		const actual = {}
		for (const [file, load] of Object.entries(COMPONENTS).sort()) {
			const component = (await load()).default
			for (const [name, definition] of Object.entries(component.props || {})) {
				actual[`${path.basename(file)} | ${name}`] = JSON.parse(
					JSON.stringify(contractOf(definition)),
				)
			}
		}
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
