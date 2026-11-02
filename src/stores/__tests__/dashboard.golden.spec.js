/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Golden test for the dashboard store's resolveActive getter and its
 * loadDashboards, createDashboard, addWidgetToDashboard and addTileToDashboard
 * actions: each case starts from a fresh store, and the result, the state it
 * leaves, the API calls, the toasts and the log lines are compared with
 * fixtures/dashboard.golden.json. Set RECORD_GOLDEN=1 to rewrite it.
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
		getDashboards: vi.fn(),
		getVisibleDashboards: vi.fn(),
		getActiveDashboard: vi.fn(),
		createDashboard: vi.fn(),
		addWidget: vi.fn(),
		addTile: vi.fn(),
		updateWidgetPlacement: vi.fn(),
	},
}))

const FIXTURE = path.resolve(__dirname, 'fixtures/dashboard.golden.json')

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

const ROWS = [
	{ id: 1, uuid: 'u1', source: 'user', isDefault: 0 },
	{ id: 2, uuid: 'g1', source: 'group', groupId: 'sales', isDefault: 0 },
	{ id: 3, uuid: 'g2', source: 'group', groupId: 'sales', isDefault: '1' },
	{ id: 4, uuid: 'd1', source: 'default', isDefault: 0 },
	{ id: 5, uuid: 'd2', source: 'default', isDefault: 1 },
	{ id: 6, uuid: 'g3', source: 'group', groupId: 'hr', isDefault: 1 },
	{ id: 7, uuid: 's1', source: 'shared', isDefault: 1 },
]

/**
 * The rows at the given positions of ROWS.
 *
 * @param {Array<number>} picks Positions.
 * @return {Array<object>} The rows.
 */
function rows(picks) {
	return picks.map((index) => ROWS[index])
}

const RESOLVE_STATES = {
	'no list': { dashboards: null },
	'empty list': { dashboards: [] },
	'active still visible by id': {
		dashboards: rows([0, 1, 2]),
		activeDashboard: { id: 2 },
		primaryGroup: 'sales',
	},
	'active still visible by uuid': {
		dashboards: rows([0, 1]),
		activeDashboard: { id: 'g1' },
	},
	'active id zero': {
		dashboards: [{ id: 0, source: 'user' }, ...rows([4])],
		activeDashboard: { id: 0 },
	},
	'active gone': {
		dashboards: rows([0, 3]),
		activeDashboard: { id: 99 },
	},
	'active null id': {
		dashboards: rows([0, 3]),
		activeDashboard: { id: null },
	},
	'primary group default': {
		dashboards: rows([0, 1, 2, 4, 5]),
		primaryGroup: 'sales',
	},
	'other group default only': {
		dashboards: rows([0, 5, 6]),
		primaryGroup: 'sales',
	},
	'default default without primary': {
		dashboards: rows([0, 1, 2, 4, 5]),
	},
	'first in primary group': {
		dashboards: rows([0, 3, 1, 2]),
		primaryGroup: 'sales',
	},
	'primary group without rows': {
		dashboards: rows([0, 3, 6]),
		primaryGroup: 'finance',
	},
	'first default-group': { dashboards: rows([0, 1, 3]) },
	'first personal': { dashboards: rows([1, 6, 0]) },
	'only shared': { dashboards: rows([6]) },
	'shared default only': { dashboards: rows([1, 6]).concat([ROWS[6]]) },
	'primary group null': {
		dashboards: rows([1, 0]),
		primaryGroup: null,
	},
}

const LOAD_CASES = {
	'visible list with quota': {
		visible: {
			data: {
				items: [{ id: 1 }, { id: 2, source: 'group' }],
				quota: { maxDashboards: 5, dashboardsUsed: 2 },
			},
		},
		active: {
			data: {
				dashboard: { id: 1, name: 'Mine' },
				placements: [{ id: 10 }],
				permissionLevel: 'view',
				isOwner: false,
				sharedBy: 'bob',
				hasVariants: true,
			},
		},
	},
	'visible bare array': {
		visible: {
			data: [
				{ id: 1, source: null },
				{ id: 3, source: 'default' },
			],
		},
		active: { data: { dashboard: { id: 3 } } },
	},
	'visible object without items': {
		visible: { data: { quota: { maxWidgetsPerDashboard: 9 } } },
		active: { data: null },
	},
	'visible null payload': {
		visible: { data: null },
		active: { data: { dashboard: null, hasVariants: 'yes' } },
	},
	'visible fails, fallback works': {
		visibleError: new Error('visible down'),
		fallback: { data: { items: [{ id: 8 }], quota: null } },
		active: { data: { dashboard: { id: 8 }, placements: null } },
	},
	'both fail': {
		visibleError: new Error('visible down'),
		fallbackError: new Error('list down'),
	},
	'active fails': {
		visible: { data: [{ id: 1 }] },
		activeError: new Error('active down'),
	},
	'items null': {
		visible: { data: { items: null, quota: { dashboardsUsed: 1 } } },
		active: {},
	},
}

const CREATE_CASES = {
	'string name': { payload: 'Team', answer: { data: { dashboard: { id: 1 } } } },
	'default name': {
		payload: undefined,
		answer: { data: { dashboard: { id: 2, source: 'group' }, placements: [] } },
	},
	'object payload': {
		payload: { name: 'Board', description: 'About', icon: 'star' },
		answer: {
			data: { dashboard: { id: 3 }, placements: [{ id: 30 }, { id: 31 }] },
		},
	},
	'object without name or icon': {
		payload: { description: null },
		answer: { data: { dashboard: { id: 4, source: null } } },
	},
	'personal dashboards disabled': {
		payload: 'X',
		error: httpError(403, { error: 'personal_dashboards_disabled' }),
	},
	'other 403': { payload: 'X', error: httpError(403, { error: 'forbidden' }) },
	'quota exceeded': {
		payload: 'X',
		error: httpError(409, { error: 'quota_exceeded', limit: 3 }),
	},
	'other 409': { payload: 'X', error: httpError(409, { error: 'conflict' }) },
	'no response': { payload: 'X', error: new Error('offline') },
	'answer without dashboard': { payload: 'X', answer: { data: {} } },
}

const WIDGETS = {
	'widget id': 'calendar',
	'custom payload': { type: 'label', content: { text: 'Hi' } },
	'custom without content': { type: 'text', content: null },
	'object without type': { kind: 'x' },
	null: null,
}

const POSITIONS = {
	none: null,
	explicit: { x: 2, y: 3, w: 5, h: 6 },
	'explicit without size': { x: 0, y: 0 },
	'explicit zero size': { x: 1, y: 1, w: 0, h: 0 },
	'size only': { w: 3, h: 1 },
	'x only': { x: 4 },
	'non-finite y': { x: 1, y: Infinity, w: 2 },
	'zero size only': { w: 0, h: null },
}

const STATES = {
	empty: { activeDashboard: { id: 7 }, widgetPlacements: [] },
	'with placements': {
		activeDashboard: { id: 7, gridColumns: 6 },
		widgetPlacements: [
			{ id: 1, gridX: 0, gridY: 0, gridWidth: 4, gridHeight: 2 },
			{ id: 2, gridX: 4, gridY: 1, gridWidth: 2, gridHeight: 5 },
		],
	},
	'no active dashboard': { activeDashboard: null, widgetPlacements: [] },
}

const FAILURES = {
	'widget quota': httpError(409, {
		error: 'quota_exceeded',
		quota: 'widgets',
		limit: 12,
	}),
	'other error': new Error('server down'),
}

/**
 * The JSON-safe form of a value: errors as their message, functions dropped.
 *
 * @param {unknown} value The value.
 * @return {unknown} The plain value.
 */
function plain(value) {
	return JSON.parse(
		JSON.stringify(value, (_key, entry) =>
			entry instanceof Error ? `Error: ${entry.message}` : entry,
		) ?? 'null',
	)
}

/**
 * Wait for the promises a store action left running.
 *
 * @return {Promise<void>}
 */
function settle() {
	return new Promise((resolve) => setTimeout(resolve, 0))
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
	Object.assign(store, state)
	let result
	try {
		result = { value: await run(store) }
	} catch (error) {
		result = { rejected: error.message }
	}
	await settle()
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
			quota: store.quota,
			permissionLevel: store.permissionLevel,
			loading: store.loading,
		},
		calls,
		toasts: showError.mock.calls,
		warnings: logger.warn.mock.calls,
		errors: logger.error.mock.calls,
	})
}

/**
 * Reset every mock, then let the API answer the way one case needs.
 *
 * @param {object} answers Per API method: `{value}` resolves, `{error}` rejects.
 * @return {Promise<void>}
 */
async function answer(answers) {
	vi.clearAllMocks()
	const { api } = await import('../../services/api.js')
	for (const fn of Object.values(api)) {
		fn.mockReset()
		fn.mockResolvedValue({ data: null })
	}
	for (const [name, { value, error }] of Object.entries(answers)) {
		if (error) {
			api[name].mockRejectedValue(error)
		} else {
			api[name].mockResolvedValue(value)
		}
	}
}

/**
 * The API answers of one loadDashboards case.
 *
 * @param {object} spec The case.
 * @return {object} The answers.
 */
function loadAnswers(spec) {
	return {
		getVisibleDashboards: spec.visibleError
			? { error: spec.visibleError }
			: { value: spec.visible },
		getDashboards: spec.fallbackError
			? { error: spec.fallbackError }
			: { value: spec.fallback },
		getActiveDashboard: spec.activeError
			? { error: spec.activeError }
			: { value: spec.active },
	}
}

/**
 * Record every case of the cases this golden covers.
 *
 * @return {Promise<object>} The outcomes by case name.
 */
async function record() {
	const actual = {}
	for (const [name, state] of Object.entries(RESOLVE_STATES)) {
		await answer({})
		actual[`resolveActive | ${name}`] = await outcome(
			state,
			(store) => store.resolveActive,
		)
	}
	for (const [name, spec] of Object.entries(LOAD_CASES)) {
		await answer(loadAnswers(spec))
		actual[`loadDashboards | ${name}`] = await outcome(
			{ dashboards: [{ id: 'old' }], quota: { maxDashboards: 1 } },
			(store) => store.loadDashboards(),
		)
	}
	for (const [name, spec] of Object.entries(CREATE_CASES)) {
		await answer({
			createDashboard: spec.error
				? { error: spec.error }
				: { value: spec.answer },
			getVisibleDashboards: { value: { data: [{ id: 'reloaded' }] } },
		})
		actual[`createDashboard | ${name}`] = await outcome(
			{ dashboards: [{ id: 0 }] },
			(store) => store.createDashboard(spec.payload),
		)
	}
	for (const [s, state] of Object.entries(STATES)) {
		for (const [p, position] of Object.entries(POSITIONS)) {
			for (const [w, widget] of Object.entries(WIDGETS)) {
				await answer({ addWidget: { value: { data: { id: 'new' } } } })
				actual[`addWidgetToDashboard | ${s} | ${p} | ${w}`] = await outcome(
					structuredClone(state),
					(store) => store.addWidgetToDashboard(widget, position),
				)
			}
			await answer({ addTile: { value: { data: { id: 'tile' } } } })
			actual[`addTileToDashboard | ${s} | ${p}`] = await outcome(
				structuredClone(state),
				(store) =>
					store.addTileToDashboard({ title: 'T', icon: 'i' }, position),
			)
		}
		for (const [f, error] of Object.entries(FAILURES)) {
			await answer({ addWidget: { error }, addTile: { error } })
			actual[`addWidgetToDashboard | ${s} | failure | ${f}`] = await outcome(
				structuredClone(state),
				(store) => store.addWidgetToDashboard('calendar'),
			)
			await answer({ addWidget: { error }, addTile: { error } })
			actual[`addTileToDashboard | ${s} | failure | ${f}`] = await outcome(
				structuredClone(state),
				(store) => store.addTileToDashboard({ title: 'T' }),
			)
		}
	}
	return actual
}

describe('dashboard store (golden)', () => {
	it('matches the recorded results', async () => {
		const actual = await record()
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
