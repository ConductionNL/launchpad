/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Golden test for needsAttention(): every operator against a set of counts and
 * right-hand sides, compared with fixtures/needsAttention.golden.json. Set
 * RECORD_GOLDEN=1 to rewrite it.
 */

import fs from 'node:fs'
import path from 'node:path'
import { describe, expect, it } from 'vitest'
import { needsAttention } from '../attentionFeed.js'

const FIXTURE = path.resolve(__dirname, 'fixtures/needsAttention.golden.json')

const OPS = {
	gt: 'gt',
	gte: 'gte',
	lt: 'lt',
	lte: 'lte',
	eq: 'eq',
	neq: 'neq',
	'upper case': 'GT',
	unknown: 'between',
	'empty string': '',
	'prototype key': 'toString',
	constructor: 'constructor',
	proto: '__proto__',
	'own property check': 'hasOwnProperty',
	undefined,
	null: null,
	number: 1,
}

const VALUES = {
	zero: 0,
	one: 1,
	five: 5,
	negative: -2,
	'string one': '1',
	NaN,
	undefined,
	null: null,
}

/**
 * Run one case and record its result or the error it throws.
 *
 * @param {Array} args The arguments.
 * @return {object} The outcome.
 */
function outcome(args) {
	try {
		return { result: needsAttention(...args) }
	} catch (error) {
		return { error: error.message }
	}
}

describe('needsAttention (golden)', () => {
	it('matches the recorded results', () => {
		const actual = {}
		for (const [o, op] of Object.entries(OPS)) {
			for (const [c, count] of Object.entries(VALUES)) {
				for (const [v, value] of Object.entries(VALUES)) {
					actual[`${o} | ${c} | ${v}`] = outcome([count, op, value])
				}
			}
		}
		if (process.env.RECORD_GOLDEN) {
			fs.writeFileSync(FIXTURE, JSON.stringify(actual, null, '\t') + '\n')
		}
		const expected = JSON.parse(fs.readFileSync(FIXTURE, 'utf8'))
		for (const key of Object.keys(expected)) {
			expect(actual[key], key).toEqual(expected[key])
		}
		expect(Object.keys(actual)).toEqual(Object.keys(expected))
	})
})
