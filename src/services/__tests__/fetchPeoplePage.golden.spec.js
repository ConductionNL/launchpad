/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Golden test for fetchPeoplePage(): a set of arguments against a set of
 * answers, with the URL requested, compared with
 * fixtures/fetchPeoplePage.golden.json. Set RECORD_GOLDEN=1 to rewrite it.
 */

import axios from '@nextcloud/axios'
import fs from 'node:fs'
import path from 'node:path'
import { describe, expect, it, vi } from 'vitest'
import { fetchPeoplePage } from '../widgetDataAdapters.js'

vi.mock('@nextcloud/axios', () => ({
	default: { get: vi.fn() },
}))
vi.mock('@nextcloud/router', () => ({
	generateUrl: (path) => `/index.php${path}`,
}))

const FIXTURE = path.resolve(__dirname, 'fixtures/fetchPeoplePage.golden.json')

const ARGS = {
	none: undefined,
	empty: {},
	'all set': {
		offset: 20,
		limit: 10,
		filters: [{ field: 'group', value: 'sales' }],
		excludeDisabled: true,
		sortBy: 'displayName',
	},
	'falsy values': {
		offset: 0,
		limit: 0,
		filters: null,
		excludeDisabled: false,
		sortBy: '',
	},
	'null paging': { offset: null, limit: null, excludeDisabled: 'yes', sortBy: 5 },
	'filters object': { filters: { group: 'a & b' } },
}

const ANSWERS = {
	page: { data: { users: [{ id: 'u1' }], total: 31, hasMore: true } },
	'loose fields': { data: { users: 'x', total: '31', hasMore: 'true' } },
	'no data': { data: null },
	'no response': undefined,
}

describe('fetchPeoplePage (golden)', () => {
	it('matches the recorded results', async () => {
		const actual = {}
		for (const [a, args] of Object.entries(ARGS)) {
			for (const [r, answer] of Object.entries(ANSWERS)) {
				axios.get.mockReset()
				axios.get.mockResolvedValue(answer)
				const result = await fetchPeoplePage(args)
				actual[`${a} | ${r}`] = { result, requested: axios.get.mock.calls }
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
