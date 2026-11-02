/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Golden test for the widgets store's loadWidgetItems action: start states,
 * API answers and failures, each on a fresh store, compared with
 * fixtures/widgetItems.golden.json case by case. Set RECORD_GOLDEN=1 to
 * rewrite it.
 */

import fs from 'node:fs'
import path from 'node:path'
import { createPinia, setActivePinia } from 'pinia'
import { describe, expect, it, vi } from 'vitest'

vi.mock('../../utils/logger.js', () => ({
	logger: { warn: vi.fn(), error: vi.fn(), debug: vi.fn(), info: vi.fn() },
}))

vi.mock('../../services/api.js', () => ({
	api: { getWidgetItems: vi.fn() },
}))

vi.mock('../../services/widgetBridge.js', () => ({
	widgetBridge: { setWidgetMetadata: vi.fn() },
}))

const FIXTURE = path.resolve(__dirname, 'fixtures/widgetItems.golden.json')

const STARTS = {
	empty: {},
	'earlier items': {
		a: { items: [{ id: 1 }], emptyContentMessage: 'none', loading: false, extra: 'kept' },
		z: { items: [], loading: false },
	},
}

const IDS = {
	none: [],
	one: ['a'],
	two: ['a', 'b'],
}

const ANSWERS = {
	'full entries': {
		data: {
			a: {
				items: [{ id: 2 }],
				emptyContentMessage: 'Empty',
				halfEmptyContentMessage: 'Half',
			},
			b: { items: [] },
		},
	},
	'entry without fields': { data: { a: {} } },
	'entry with null fields': {
		data: {
			b: { items: null, emptyContentMessage: null, halfEmptyContentMessage: '' },
		},
	},
	'unrequested id': { data: { other: { items: [{ id: 3 }] } } },
	'no entries': { data: {} },
	'null data': { data: null },
	'no response': undefined,
}

/**
 * Run loadWidgetItems on a fresh store and record what it left.
 *
 * @param {object} start The widgetItems to start from.
 * @param {Array<string>} ids The ids to load.
 * @return {Promise<object>} The outcome.
 */
async function outcome(start, ids) {
	setActivePinia(createPinia())
	const { api } = await import('../../services/api.js')
	const { logger } = await import('../../utils/logger.js')
	const { useWidgetStore } = await import('../widgets.js')
	const store = useWidgetStore()
	store.widgetItems = structuredClone(start)
	let result
	try {
		result = { value: (await store.loadWidgetItems(ids)) ?? '(undefined)' }
	} catch (error) {
		result = { rejected: error.message }
	}
	return JSON.parse(
		JSON.stringify({
			result,
			widgetItems: store.widgetItems,
			calls: api.getWidgetItems.mock.calls,
			errors: logger.error.mock.calls.map(([text, error]) => [text, String(error)]),
		}),
	)
}

/**
 * Record every case.
 *
 * @return {Promise<object>} The outcomes by case name.
 */
async function record() {
	const { api } = await import('../../services/api.js')
	const actual = {}
	const replies = [
		...Object.entries(ANSWERS).map(([name, value]) => [name, { value }]),
		['fails', { error: new Error('down') }],
	]
	for (const [startName, start] of Object.entries(STARTS)) {
		for (const [idsName, ids] of Object.entries(IDS)) {
			for (const [name, reply] of replies) {
				vi.clearAllMocks()
				api.getWidgetItems.mockReset()
				if (reply.error) {
					api.getWidgetItems.mockRejectedValue(reply.error)
				} else {
					api.getWidgetItems.mockResolvedValue(reply.value)
				}
				actual[`${startName} | ${idsName} | ${name}`] = await outcome(start, ids)
			}
		}
	}
	return actual
}

describe('widgets store loadWidgetItems (golden)', () => {
	it('matches the recorded outcomes case by case', async () => {
		const actual = await record()
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
