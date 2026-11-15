/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Golden test for DashboardBulkOperations' confirmAction: for every action,
 * form input and response shape it records the API call made, the result
 * summary, the error message and the selection left behind, and compares them
 * with fixtures/DashboardBulkOperations.golden.json (one case per line). Set
 * RECORD_GOLDEN=1 to rewrite it.
 */

import { mount } from '@vue/test-utils'
import fs from 'node:fs'
import path from 'node:path'
import { describe, expect, it, vi } from 'vitest'
import DashboardBulkOperations from '../DashboardBulkOperations.vue'
import { api } from '../../../services/api.js'

vi.mock('../../../services/api.js', () => ({
	api: {
		getVisibleDashboards: vi.fn(),
		bulkDeleteDashboards: vi.fn(),
		bulkMoveDashboards: vi.fn(),
		bulkStatusDashboards: vi.fn(),
		bulkReindexDashboards: vi.fn(),
	},
}))

const FIXTURE = path.resolve(
	__dirname,
	'fixtures/DashboardBulkOperations.golden.json',
)

const BULK_CALLS = [
	'bulkDeleteDashboards',
	'bulkMoveDashboards',
	'bulkStatusDashboards',
	'bulkReindexDashboards',
]

const ACTIONS = ['delete', 'move', 'status', 'reindex', 'other', 'constructor']

const RESPONSES = {
	deleted: { data: { deletedCount: 2, skippedCount: 1 } },
	moved: { data: { movedCount: 3 } },
	updated: { data: { updatedCount: 4, errors: [{ uuid: 'b', error: 'x' }] } },
	reindexed: { data: { reindexedCount: 5, errors: 'not a list' } },
	wouldDelete: { data: { dryRun: true, wouldDeleteCount: 6, wouldSkipCount: 2 } },
	wouldMove: { data: { dryRun: true, wouldMoveCount: 7 } },
	wouldUpdate: { data: { dryRun: true, wouldUpdateCount: 8 } },
	wouldReindex: { data: { dryRun: 1, wouldReindexCount: 9 } },
	nullFirst: {
		data: {
			deletedCount: null,
			movedCount: 0,
			skippedCount: null,
			wouldSkipCount: 4,
		},
	},
	zeroFirst: {
		data: { deletedCount: 0, movedCount: 3, skippedCount: 0, wouldSkipCount: 4 },
	},
	allCounts: {
		data: {
			deletedCount: 1,
			movedCount: 2,
			updatedCount: 3,
			reindexedCount: 4,
			wouldDeleteCount: 5,
			wouldMoveCount: 6,
			wouldUpdateCount: 7,
			wouldReindexCount: 8,
		},
	},
	laterCounts: { data: { wouldUpdateCount: 3, wouldReindexCount: 9 } },
	movedAndUpdated: { data: { movedCount: 2, updatedCount: 3 } },
	falsyData: { data: 0 },
	emptyData: { data: {} },
	nullData: { data: null },
	noData: {},
	undefinedResponse: undefined,
	dryRunFalse: { data: { dryRun: false, updatedCount: 1 } },
}

const FAILURES = {
	serverError: { response: { data: { error: 'Server said no' } } },
	emptyError: { response: { data: { error: '' } } },
	noResponse: new Error('network'),
	nothing: undefined,
}

const FORMS = {
	plain: {},
	dryRun: { dryRun: true },
	parentEmpty: { parentUuidInput: '' },
	parentBlank: { parentUuidInput: '   ' },
	parentPadded: { parentUuidInput: '  parent-1 ' },
	parentDryRun: { parentUuidInput: 'p2', dryRun: true },
	statusDraft: { statusInput: 'draft' },
	statusScheduled: {
		statusInput: 'scheduled',
		publishAtInput: '2026-12-01T10:00',
	},
	statusScheduledDryRun: {
		statusInput: 'scheduled',
		publishAtInput: '',
		dryRun: true,
	},
	statusPublishAtIgnored: {
		statusInput: 'published',
		publishAtInput: '2026-12-01T10:00',
	},
}

/**
 * Let pending promises settle.
 */
async function settle() {
	await new Promise((resolve) => setTimeout(resolve, 0))
	await new Promise((resolve) => setTimeout(resolve, 0))
}

/**
 * Run confirmAction once and capture what it did.
 *
 * @param {object} spec The case: action, form fields and the API outcome.
 * @return {Promise<object>} The recorded calls and component state.
 */
async function runCase(spec) {
	vi.clearAllMocks()
	api.getVisibleDashboards.mockResolvedValue({
		data: [{ uuid: 'a' }, { uuid: 'b' }],
	})
	for (const name of BULK_CALLS) {
		if (spec.failure !== undefined) {
			api[name].mockRejectedValue(FAILURES[spec.failure])
		} else {
			api[name].mockResolvedValue(RESPONSES[spec.response])
		}
	}
	const wrapper = mount(DashboardBulkOperations)
	await settle()
	const loadsBefore = api.getVisibleDashboards.mock.calls.length
	await wrapper.setData({
		selectedUuids: ['a', 'b'],
		modal: { action: spec.action, title: 'T', message: 'M' },
		...FORMS[spec.form],
	})
	const pending = wrapper.vm.confirmAction()
	const runningDuring = wrapper.vm.running
	await pending
	await settle()
	const calls = BULK_CALLS.flatMap((name) =>
		api[name].mock.calls.map((args) => ({ name, args })),
	)
	const result = JSON.parse(
		JSON.stringify({
			calls,
			runningDuring,
			running: wrapper.vm.running,
			modal: wrapper.vm.modal,
			resultSummary: wrapper.vm.resultSummary,
			errorMessage: wrapper.vm.errorMessage,
			selectedUuids: wrapper.vm.selectedUuids,
			reloads: api.getVisibleDashboards.mock.calls.length - loadsBefore,
		}),
	)
	wrapper.unmount()
	return result
}

/**
 * Every case the golden covers, keyed by a readable name.
 *
 * @return {object} Case name to case spec.
 */
function cases() {
	const all = {}
	for (const action of ACTIONS) {
		for (const response of Object.keys(RESPONSES)) {
			all[`${action} / plain / ${response}`] = {
				action,
				form: 'plain',
				response,
			}
		}
		for (const failure of Object.keys(FAILURES)) {
			all[`${action} / plain / fails ${failure}`] = {
				action,
				form: 'plain',
				failure,
			}
		}
		for (const form of Object.keys(FORMS)) {
			all[`${action} / ${form} / wouldMove`] = {
				action,
				form,
				response: 'wouldMove',
			}
		}
	}
	return all
}

describe('DashboardBulkOperations confirmAction (golden)', () => {
	it('matches the recorded calls and state', async () => {
		const expected = fs.existsSync(FIXTURE)
			? JSON.parse(fs.readFileSync(FIXTURE, 'utf8'))
			: {}
		const actual = {}
		for (const [name, spec] of Object.entries(cases())) {
			actual[name] = await runCase(spec)
		}
		if (process.env.RECORD_GOLDEN === '1') {
			const lines = Object.entries(actual).map(
				([name, value]) =>
					`\t${JSON.stringify(name)}: ${JSON.stringify(value)}`,
			)
			fs.writeFileSync(FIXTURE, `{\n${lines.join(',\n')}\n}\n`)
			return
		}
		expect(Object.keys(actual)).toEqual(Object.keys(expected))
		for (const name of Object.keys(expected)) {
			expect(actual[name], name).toEqual(expected[name])
		}
	})
})
