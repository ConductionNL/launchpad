/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * ScheduledExportsModal (scheduled-exports tasks 13-14): the list is read
 * from GET /api/exports and shows name, subject, next run and last-run
 * status; Run now, Delete, Edit and View runs reach the API; the run
 * history lists GET /api/exports/{id}/runs with a delivery summary and a
 * run opens its detail. Only HTTP, toasts, the router and l10n are mocked.
 */

import axios from '@nextcloud/axios'
import { showSuccess, showWarning } from '@nextcloud/dialogs'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import ScheduledExportsModal from '../ScheduledExportsModal.vue'

vi.mock('@nextcloud/axios', () => ({
	default: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))
vi.mock('@nextcloud/router', () => ({
	generateUrl: (path) => `/index.php${path}`,
	generateRemoteUrl: (service) => `/remote.php/${service}`,
}))
vi.mock('@nextcloud/l10n', () => ({
	t: (_app, text, vars = {}) =>
		text.replace(/\{(\w+)\}/g, (_m, k) => vars[k] ?? `{${k}}`),
}))
vi.mock('@nextcloud/dialogs', () => ({
	showError: vi.fn(),
	showInfo: vi.fn(),
	showSuccess: vi.fn(),
	showWarning: vi.fn(),
}))
vi.mock(
	'@conduction/nextcloud-vue',
	() => import('./helpers/scheduledExportStubs.js'),
)

const BASE = '/index.php/apps/launchpad/api/exports'

const exports = [
	{
		id: 'e1',
		name: 'Weekly bezwaren',
		subjectType: 'dashboard',
		subjectId: 'd-1',
		enabled: true,
		nextRunAt: '2026-11-16T09:00:00+01:00',
		lastRunStatus: 'success',
		recurrence: { type: 'weekly', daysOfWeek: ['MO'], timeOfDay: '09:00' },
		renderTargets: [{ format: 'pdf' }],
		recipients: [
			{
				id: 'r1',
				channel: 'email',
				channelConfig: { addresses: ['a@gemeente.nl'] },
			},
		],
	},
	{
		id: 'e2',
		name: 'Service desk snapshot',
		subjectType: 'widget',
		subjectId: '7',
		enabled: false,
		nextRunAt: null,
		lastRunStatus: 'partial',
		recurrence: { type: 'daily', timeOfDay: '08:00' },
		renderTargets: [{ format: 'png' }],
		recipients: [
			{
				channel: 'webhook',
				channelConfig: { url: 'https://hooks.example.org' },
			},
		],
	},
]

const runs = [
	{
		id: 'run-2',
		exportId: 'e1',
		startedAt: '2026-11-09T09:00:00+01:00',
		status: 'partial',
		triggeredBy: 'schedule',
		deliveries: [
			{
				recipientId: 'r1',
				channel: 'email',
				status: 'success',
				attemptCount: 1,
			},
			{
				recipientId: 'r2',
				channel: 'webhook',
				status: 'failed-permanent',
				attemptCount: 5,
				error: 'HTTP 503',
			},
			{
				recipientId: 'r3',
				channel: 'sftp',
				status: 'retrying',
				attemptCount: 2,
			},
		],
		renderArtefacts: [],
	},
	{
		id: 'run-1',
		exportId: 'e1',
		startedAt: '2026-11-02T09:00:00+01:00',
		status: 'partial',
		triggeredBy: 'schedule',
		skipReason: 'The previous run was still running',
		deliveries: [],
		renderArtefacts: [],
	},
]

/**
 * Mount the list with GET /api/exports answering the two exports.
 *
 * @return {Promise<object>} The wrapper, after the list loaded.
 */
async function mountList() {
	axios.get.mockImplementation(async (url) => {
		if (url === BASE) {
			return { data: { results: exports.map((e) => ({ ...e })), total: 2 } }
		}
		if (url === `${BASE}/e1/runs`) {
			return { data: { results: runs, total: 2 } }
		}
		if (url === `${BASE}/e1/runs/run-2`) {
			return { data: runs[0] }
		}
		throw new Error(`unexpected GET ${url}`)
	})
	const wrapper = mount(ScheduledExportsModal, {
		props: {
			open: true,
			subjects: [
				{ type: 'dashboard', id: 'd-1', label: 'Bezwaren' },
				{ type: 'widget', id: '7', label: 'KPI' },
			],
			defaultSubject: { type: 'dashboard', id: 'd-1' },
		},
	})
	await flushPromises()
	return wrapper
}

describe('ScheduledExportsModal', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		setActivePinia(createPinia())
	})

	it('lists the exports from the API with subject, next run and last-run status', async () => {
		const wrapper = await mountList()

		expect(axios.get).toHaveBeenCalledWith(BASE, { params: { limit: 200 } })
		const rows = wrapper.findAll('tr.row')
		expect(rows).toHaveLength(2)
		expect(rows[0].find('[data-testid="scheduled-export-name"]').text()).toBe(
			'Weekly bezwaren',
		)
		expect(rows[0].text()).toContain('Dashboard: Bezwaren')
		expect(
			rows[0].find('[data-testid="scheduled-export-next"]').text(),
		).not.toBe('Off')
		expect(
			rows[0].find('[data-testid="export-status"]').attributes('data-status'),
		).toBe('success')
		expect(rows[0].find('[data-testid="export-status"]').text()).toBe(
			'Succeeded',
		)
		expect(rows[1].text()).toContain('Widget: KPI')
		expect(rows[1].find('[data-testid="scheduled-export-next"]').text()).toBe(
			'Off',
		)
		expect(rows[1].find('[data-testid="export-status"]').text()).toBe(
			'Partly failed',
		)
	})

	it('"Run now" POSTs the run, reports how it went and reads the list again', async () => {
		const wrapper = await mountList()
		axios.post.mockResolvedValue({ data: { id: 'run-9', status: 'success' } })

		await wrapper
			.findAll('[data-testid="scheduled-export-run"]')[0]
			.trigger('click')
		await flushPromises()

		expect(axios.post).toHaveBeenCalledWith(`${BASE}/e1/run`)
		expect(showSuccess).toHaveBeenCalledWith(
			'Weekly bezwaren ran and was delivered.',
		)
		expect(axios.get.mock.calls.filter(([url]) => url === BASE)).toHaveLength(2)

		axios.post.mockResolvedValue({ data: { id: 'run-10', status: 'partial' } })
		await wrapper
			.findAll('[data-testid="scheduled-export-run"]')[1]
			.trigger('click')
		await flushPromises()
		expect(axios.post).toHaveBeenLastCalledWith(`${BASE}/e2/run`)
		expect(showWarning).toHaveBeenCalledWith(
			'Service desk snapshot ran, but not every delivery succeeded.',
		)
	})

	it('deletes an export after confirmation', async () => {
		const wrapper = await mountList()
		axios.delete.mockResolvedValue({ data: { deleted: true } })

		await wrapper
			.findAll('[data-testid="scheduled-export-delete"]')[1]
			.trigger('click')
		expect(axios.delete).not.toHaveBeenCalled()
		await wrapper.find('[data-testid="confirm"]').trigger('click')
		await flushPromises()

		expect(axios.delete).toHaveBeenCalledWith(`${BASE}/e2`)
		expect(wrapper.findAll('tr.row')).toHaveLength(1)
	})

	it('opens the editor on the chosen export and for a new one', async () => {
		const wrapper = await mountList()

		await wrapper
			.findAll('[data-testid="scheduled-export-edit"]')[0]
			.trigger('click')
		expect(wrapper.find('[data-testid="export-editor"]').exists()).toBe(true)
		expect(wrapper.find('[data-testid="export-name"]').element.value).toBe(
			'Weekly bezwaren',
		)
		await wrapper
			.findComponent({ name: 'ScheduledExportModal' })
			.vm.$emit('close')
		expect(wrapper.find('[data-testid="export-editor"]').exists()).toBe(false)

		await wrapper.find('[data-testid="scheduled-exports-new"]').trigger('click')
		expect(wrapper.find('[data-testid="export-name"]').element.value).toBe('')
	})

	it('"View runs" lists the run history with a delivery summary, and a run opens its detail', async () => {
		const wrapper = await mountList()

		await wrapper
			.findAll('[data-testid="scheduled-export-runs"]')[0]
			.trigger('click')
		await flushPromises()

		expect(axios.get).toHaveBeenCalledWith(`${BASE}/e1/runs`, {
			params: { limit: 200 },
		})
		expect(wrapper.find('[data-testid="export-run-history"]').exists()).toBe(
			true,
		)
		const rows = wrapper.findAll('tr.row')
		expect(rows).toHaveLength(2)
		expect(rows[0].text()).toContain('On schedule')
		expect(rows[0].findAll('td')[3].text()).toBe('3')
		expect(rows[0].find('[data-testid="export-run-outcome"]').text()).toBe(
			'delivered: 1, failed: 1, waiting: 1',
		)
		expect(rows[1].find('[data-testid="export-run-outcome"]').text()).toBe(
			'Skipped: The previous run was still running',
		)

		await rows[0].find('[data-testid="export-run-open"]').trigger('click')
		await flushPromises()
		expect(wrapper.find('[data-testid="run-detail"]').exists()).toBe(true)
		expect(axios.get).toHaveBeenCalledWith(`${BASE}/e1/runs/run-2`)

		await wrapper.find('[data-testid="export-runs-back"]').trigger('click')
		expect(wrapper.find('[data-testid="export-run-history"]').exists()).toBe(
			false,
		)
		expect(
			wrapper.findAll('[data-testid="scheduled-export-name"]'),
		).toHaveLength(2)
	})

	it('a row click in the run history also opens the run', async () => {
		const wrapper = await mountList()
		await wrapper
			.findAll('[data-testid="scheduled-export-runs"]')[0]
			.trigger('click')
		await flushPromises()

		await wrapper.findAll('tr.row')[0].trigger('click')
		await flushPromises()
		expect(
			wrapper.findComponent({ name: 'RunDetailModal' }).props('run').id,
		).toBe('run-2')
	})
})
