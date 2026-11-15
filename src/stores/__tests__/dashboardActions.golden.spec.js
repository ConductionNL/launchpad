/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Golden test for the dashboard store's forkDashboard, applyPublicationPatch,
 * effectivePublicationStatus, dashboardByPath, fetchDashboardMetadata and updateDashboardMetadata
 * actions: each case starts from a fresh store, and the result, the state it
 * leaves, the API calls, the toasts and the log lines are compared with
 * fixtures/dashboardActions.golden.json. Set RECORD_GOLDEN=1 to rewrite it.
 */

import fs from 'node:fs'
import path from 'node:path'
import { createPinia, setActivePinia } from 'pinia'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/dialogs', () => ({
	showError: vi.fn(),
	showSuccess: vi.fn(),
}))

vi.mock('@nextcloud/l10n', () => ({
	translate: (_app, str, vars) => `${str}${vars ? JSON.stringify(vars) : ''}`,
	translatePlural: (_app, sing, plur, n) => (n === 1 ? sing : plur),
}))

vi.mock('../../utils/logger.js', () => ({
	logger: { warn: vi.fn(), error: vi.fn(), debug: vi.fn(), info: vi.fn() },
}))

vi.mock('../../services/api.js', () => ({
	api: {
		forkDashboard: vi.fn(),
		getDashboardByPath: vi.fn(),
		getDashboardMetadata: vi.fn(),
		updateDashboardMetadata: vi.fn(),
	},
}))

const FIXTURE = path.resolve(__dirname, 'fixtures/dashboardActions.golden.json')

/**
 * A rejected request as axios gives it.
 *
 * @param {number} status The HTTP status.
 * @param {object} data The response body.
 * @return {Error} The error.
 */
function httpError(status, data) {
	return Object.assign(new Error(`Request failed with status ${status}`), {
		response: { status, data },
	})
}

const FAILURES = {
	'personal dashboards disabled': httpError(403, {
		error: 'personal_dashboards_disabled',
	}),
	'other 403': httpError(403, { error: 'forbidden' }),
	'403 without body': httpError(403, null),
	'not found': httpError(404, {}),
	'server error with message': httpError(500, { message: 'Bad key' }),
	'validation with empty message': httpError(400, { message: '' }),
	'no response': new Error('offline'),
	null: null,
}

const START = {
	dashboards: [{ id: 1, uuid: 'a', publicationStatus: 'draft' }],
	activeDashboard: { id: 1, uuid: 'a' },
	widgetPlacements: [{ id: 9 }],
	permissionLevel: 'view',
	metadataByDashboard: { old: { k: 'v' } },
	pathCache: { cached: { id: 'hit' }, nulled: null },
}

const FORK_ANSWERS = {
	'fork with dashboard': {
		data: { dashboard: { id: 2, uuid: 'b', name: 'Copy' } },
	},
	'fork without dashboard': { data: {} },
	'no data': { data: null },
	'no response': undefined,
}

const PATCHES = {
	null: null,
	'no uuid': { publicationStatus: 'published' },
	'active and listed': {
		uuid: 'a',
		publicationStatus: 'scheduled',
		publishAt: '2030-01-01',
		publishedAt: null,
		unpublishAt: '2031-01-01',
		extra: 'ignored',
	},
	'listed without unpublish': { uuid: 'a', publicationStatus: 'published' },
	'unknown uuid': { uuid: 'zz', publicationStatus: 'draft', unpublishAt: null },
}

const PATCH_STATES = {
	start: {},
	'active differs': { activeDashboard: { uuid: 'other' } },
	'no active': { activeDashboard: null },
	'active only': { dashboards: [], activeDashboard: { uuid: 'a', name: 'A' } },
}

const STATUS_CASES = {
	null: null,
	'no status': {},
	draft: { publicationStatus: 'draft' },
	published: { publicationStatus: 'published' },
	'scheduled without date': { publicationStatus: 'scheduled' },
	'scheduled past': {
		publicationStatus: 'scheduled',
		publishAt: '2026-01-01T00:00:00Z',
	},
	'scheduled exactly now': {
		publicationStatus: 'scheduled',
		publishAt: '2026-06-01T12:00:00Z',
	},
	'scheduled future': {
		publicationStatus: 'scheduled',
		publishAt: '2027-01-01T00:00:00Z',
	},
	'scheduled invalid date': { publicationStatus: 'scheduled', publishAt: 'soon' },
}

const PATHS = {
	undefined,
	empty: '',
	slashes: '///',
	trimmed: '/team/sales/',
	'double trailing': 'team//',
	cached: 'cached',
	'cached null': '/nulled',
	number: 42,
}

const PATH_ANSWERS = {
	found: { data: { dashboard: { id: 5 } } },
	'no dashboard': { data: {} },
	'no data': {},
	'no response': undefined,
}

const METADATA_ANSWERS = {
	map: { data: { a: '1', b: 2 } },
	array: { data: ['x'] },
	string: { data: 'text' },
	number: { data: 5 },
	null: { data: null },
	'no response': undefined,
}

/**
 * The JSON-safe form of a value: errors as their message, undefined as a tag.
 *
 * @param {unknown} value The value.
 * @return {unknown} The plain value.
 */
function plain(value) {
	return JSON.parse(
		JSON.stringify(value, (_key, entry) => {
			if (entry instanceof Error) {
				return `Error: ${entry.message}`
			}
			return entry === undefined ? '(undefined)' : entry
		}) ?? 'null',
	)
}

/**
 * Run one store case on a fresh store and record everything it touched.
 *
 * @param {object} state The state to start from.
 * @param {(store: object) => unknown} run Runs the case.
 * @return {Promise<object>} The outcome.
 */
async function outcome(state, run) {
	setActivePinia(createPinia())
	const { api } = await import('../../services/api.js')
	const { logger } = await import('../../utils/logger.js')
	const { showError } = await import('@nextcloud/dialogs')
	const { useDashboardStore } = await import('../dashboard.js')
	const store = useDashboardStore()
	Object.assign(store, structuredClone({ ...START, ...state }))
	let result
	try {
		result = { value: await run(store) }
	} catch (error) {
		result = { rejected: error === null ? 'null' : error.message }
	}
	const calls = {}
	for (const [name, fn] of Object.entries(api)) {
		if (fn.mock.calls.length > 0) {
			calls[name] = fn.mock.calls
		}
	}
	return plain({
		result,
		state: {
			dashboards: store.dashboards,
			activeDashboard: store.activeDashboard,
			widgetPlacements: store.widgetPlacements,
			permissionLevel: store.permissionLevel,
			loading: store.loading,
			metadataByDashboard: store.metadataByDashboard,
			pathCache: store.pathCache,
		},
		calls,
		toasts: showError.mock.calls,
		errors: logger.error.mock.calls,
	})
}

/**
 * Reset every mock, then let one API method resolve or reject.
 *
 * @param {string} name The API method.
 * @param {{value?: unknown, error?: unknown}} answer Resolves with value, or rejects with error.
 * @return {Promise<void>}
 */
async function answer(name, answer) {
	vi.clearAllMocks()
	const { api } = await import('../../services/api.js')
	for (const fn of Object.values(api)) {
		fn.mockReset()
	}
	if ('error' in answer) {
		api[name].mockRejectedValue(answer.error)
	} else {
		api[name].mockResolvedValue(answer.value)
	}
}

/**
 * Every API answer of one action: the resolved answers, then the failures.
 *
 * @param {object} answers Resolved answers by case name.
 * @return {Array<Array>} Pairs of case name and answer.
 */
function answersAndFailures(answers) {
	return [
		...Object.entries(answers).map(([name, value]) => [name, { value }]),
		...Object.entries(FAILURES).map(([name, error]) => [
			`fails: ${name}`,
			{ error },
		]),
	]
}

/**
 * Record every case of the actions this golden covers.
 *
 * @return {Promise<object>} The outcomes by case name.
 */
async function record() {
	const actual = {}
	for (const [name, reply] of answersAndFailures(FORK_ANSWERS)) {
		await answer('forkDashboard', reply)
		actual[`forkDashboard | ${name}`] = await outcome({}, (store) =>
			store.forkDashboard('src', name.startsWith('fails') ? null : 'Mine'),
		)
	}
	for (const [stateName, state] of Object.entries(PATCH_STATES)) {
		for (const [name, patch] of Object.entries(PATCHES)) {
			await answer('forkDashboard', { value: undefined })
			actual[`applyPublicationPatch | ${stateName} | ${name}`] = await outcome(
				state,
				(store) => store.applyPublicationPatch(structuredClone(patch)),
			)
		}
	}
	const now = vi
		.spyOn(Date, 'now')
		.mockReturnValue(Date.parse('2026-06-01T12:00:00Z'))
	for (const [name, dashboard] of Object.entries(STATUS_CASES)) {
		actual[`effectivePublicationStatus | ${name}`] = await outcome({}, (store) =>
			store.effectivePublicationStatus(dashboard),
		)
	}
	now.mockRestore()
	for (const [pathName, value] of Object.entries(PATHS)) {
		for (const [name, reply] of answersAndFailures(PATH_ANSWERS)) {
			await answer('getDashboardByPath', reply)
			actual[`dashboardByPath | ${pathName} | ${name}`] = await outcome(
				{},
				(store) => store.dashboardByPath(value),
			)
		}
	}
	for (const uuid of ['', 'old', 'u2']) {
		for (const [name, reply] of answersAndFailures(METADATA_ANSWERS)) {
			await answer('getDashboardMetadata', reply)
			actual[`fetchDashboardMetadata | ${uuid} | ${name}`] = await outcome(
				{},
				(store) => store.fetchDashboardMetadata(uuid),
			)
			await answer('updateDashboardMetadata', reply)
			actual[`updateDashboardMetadata | ${uuid} | ${name}`] = await outcome(
				{},
				(store) => store.updateDashboardMetadata(uuid, { k: 'new' }),
			)
		}
	}
	return actual
}

describe('dashboard store actions (golden)', () => {
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
