/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Golden test for the orgNavigation store's updateTree action: a set of
 * languages and API answers and failures, each on a fresh store, compared with
 * fixtures/orgNavigationUpdateTree.golden.json. Set RECORD_GOLDEN=1 to rewrite it.
 */

import fs from 'node:fs'
import path from 'node:path'
import { createPinia, setActivePinia } from 'pinia'
import { describe, expect, it, vi } from 'vitest'

vi.mock('../../services/api.js', () => ({
	api: { updateOrgNavigation: vi.fn() },
}))

const FIXTURE = path.resolve(
	__dirname,
	'fixtures/orgNavigationUpdateTree.golden.json',
)

const TREE = [{ id: 'n1', label: 'Home' }]

const LANGUAGES = {
	given: { lang: 'en', state: {} },
	'from the store': { lang: undefined, state: { language: 'de' } },
	'store language empty': { lang: '', state: { language: '' } },
}

const ANSWERS = {
	'tree and language': {
		data: { tree: [{ id: 'n2' }], language: 'fr' },
	},
	'tree only': { data: { tree: [] } },
	'tree not a list': { data: { tree: 'x', language: '' } },
	'no data': { data: null },
	'no response': undefined,
}

const FAILURES = {
	'server error text': { response: { data: { error: 'Not allowed' } } },
	'server error empty': { response: { data: { error: '' } } },
	'response without data': { response: {} },
	'no response': new Error('offline'),
	null: null,
}

/**
 * Run updateTree on a fresh store and record what it returned and left.
 *
 * @param {object} language The language case.
 * @return {Promise<object>} The outcome.
 */
async function outcome(language) {
	setActivePinia(createPinia())
	const { api } = await import('../../services/api.js')
	const { useOrgNavigationStore } = await import('../orgNavigation.js')
	const store = useOrgNavigationStore()
	Object.assign(store, { error: 'earlier' }, language.state)
	const result = await store.updateTree(TREE, language.lang)
	return {
		result,
		calls: api.updateOrgNavigation.mock.calls,
		state: {
			tree: store.tree,
			language: store.language,
			error: store.error,
			loading: store.loading,
		},
	}
}

describe('orgNavigation updateTree (golden)', () => {
	it('matches the recorded results', async () => {
		const { api } = await import('../../services/api.js')
		const actual = {}
		for (const [l, language] of Object.entries(LANGUAGES)) {
			for (const [a, answer] of Object.entries(ANSWERS)) {
				api.updateOrgNavigation.mockReset()
				api.updateOrgNavigation.mockResolvedValue(answer)
				actual[`${l} | ${a}`] = await outcome(language)
			}
			for (const [f, failure] of Object.entries(FAILURES)) {
				api.updateOrgNavigation.mockReset()
				api.updateOrgNavigation.mockRejectedValue(failure)
				actual[`${l} | ${f}`] = await outcome(language)
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
