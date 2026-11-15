/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Golden test for queryGraphql(): a set of call parameters against answers
 * and request failures, each with the request sent, compared with
 * fixtures/queryGraphql.golden.json. Set RECORD_GOLDEN=1 to rewrite it.
 */

import axios from '@nextcloud/axios'
import fs from 'node:fs'
import path from 'node:path'
import { describe, expect, it, vi } from 'vitest'
import { queryGraphql } from '../graphqlClient.js'

vi.mock('@nextcloud/axios', () => ({
	default: { post: vi.fn() },
}))
vi.mock('@nextcloud/router', () => ({
	generateUrl: (path, params = {}) =>
		Object.entries(params).reduce(
			(acc, [key, value]) => acc.replace(`{${key}}`, value),
			path,
		),
}))

const FIXTURE = path.resolve(__dirname, 'fixtures/queryGraphql.golden.json')

const PARAMS = {
	'app and query': { app: 'financeq', query: 'query X { x }' },
	'with variables and timeout': {
		app: 'dossiq',
		query: 'query Y { y }',
		variables: { period: 'month' },
		timeoutMs: 100,
	},
	'null variables': { app: 'a', query: 'q', variables: null },
	'blank app': { app: '  ', query: 'q' },
	'missing app': { query: 'q' },
	'numeric app': { app: 7, query: 'q' },
}

const ANSWERS = {
	data: { data: { data: { rows: [1, 2] } } },
	'null data': { data: { data: null } },
	'no data key': { data: {} },
	'no body': { data: null },
	'no response': undefined,
	errors: { data: { errors: [{ message: 'bad field' }, { message: 'two' }] } },
	'error without message': { data: { errors: [{}], data: { x: 1 } } },
	'null first error': { data: { errors: [null] } },
	'empty errors': { data: { errors: [], data: { x: 2 } } },
	'errors not a list': { data: { errors: 'oops', data: { x: 3 } } },
}

const FAILURES = {
	404: Object.assign(new Error('Not Found'), { response: { status: 404 } }),
	'404 as text': Object.assign(new Error('Not Found'), {
		response: { status: '404' },
	}),
	500: Object.assign(new Error('Server Error'), { response: { status: 500 } }),
	timeout: Object.assign(new Error('timeout of 5000ms exceeded'), {
		code: 'ECONNABORTED',
	}),
	'404 and timeout': Object.assign(new Error('both'), {
		code: 'ECONNABORTED',
		response: { status: 404 },
	}),
	'no message': Object.assign(new Error(''), { code: 'ERR_NETWORK' }),
	null: null,
	text: 'down',
}

/**
 * Run one case and record what it resolved to, or the error it rejected
 * with, and the request it sent.
 *
 * @param {object} params The call parameters.
 * @return {Promise<object>} The outcome.
 */
async function outcome(params) {
	try {
		const result = await queryGraphql(params)
		return { result, sent: axios.post.mock.calls }
	} catch (error) {
		return {
			rejected: {
				name: error.name,
				code: error.code,
				app: error.app,
				message: error.message,
			},
			sent: axios.post.mock.calls,
		}
	}
}

describe('queryGraphql (golden)', () => {
	it('matches the recorded results', async () => {
		const actual = {}
		for (const [p, params] of Object.entries(PARAMS)) {
			for (const [a, answer] of Object.entries(ANSWERS)) {
				axios.post.mockReset()
				axios.post.mockResolvedValue(answer)
				actual[`${p} | ${a}`] = await outcome(params)
			}
			for (const [f, failure] of Object.entries(FAILURES)) {
				axios.post.mockReset()
				axios.post.mockRejectedValue(failure)
				actual[`${p} | ${f}`] = await outcome(params)
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
