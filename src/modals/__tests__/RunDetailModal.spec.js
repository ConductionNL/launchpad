/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * RunDetailModal (scheduled-exports task 15): reads the run from
 * GET /api/exports/{id}/runs/{runId}, lists its deliveries and render
 * artefacts with a Files download link, and offers Retry only when a
 * delivery failed for good, POSTing .../retry and showing the answer.
 * Run-rows are AuditLog's shape. Only HTTP, the router and l10n are mocked.
 */

import axios from '@nextcloud/axios'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import RunDetailModal from '../RunDetailModal.vue'

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
vi.mock(
	'@conduction/nextcloud-vue',
	() => import('./helpers/scheduledExportStubs.js'),
)

const BASE = '/index.php/apps/launchpad/api/exports'

const exportData = {
	id: 'e1',
	name: 'Weekly bezwaren',
	recipients: [
		{
			id: 'r1',
			channel: 'email',
			channelConfig: { addresses: ['wethouder@gemeente.nl'] },
		},
		{
			id: 'r2',
			channel: 'webhook',
			channelConfig: { url: 'https://hooks.example.org/lp' },
		},
	],
}

/**
 * A run-row.
 *
 * @param {string} webhookStatus The webhook delivery's status.
 * @return {object} The run.
 */
function run(webhookStatus) {
	return {
		id: 'run-1',
		exportId: 'e1',
		startedAt: '2026-11-09T09:00:00+01:00',
		finishedAt: '2026-11-09T09:01:00+01:00',
		status: webhookStatus === 'success' ? 'success' : 'partial',
		triggeredBy: 'manual',
		triggeredByUser: 'sanne',
		renderArtefacts: [
			{
				format: 'pdf',
				sizeBytes: 2048,
				sha256: 'a'.repeat(64),
				storagePath:
					'/sanne/files/Apps/LaunchPad/Exports/e1/Weekly bezwaren.pdf',
			},
			{
				format: 'csv',
				sizeBytes: 300,
				sha256: 'b'.repeat(64),
				storagePath: null,
			},
		],
		deliveries: [
			{
				recipientId: 'r1',
				channel: 'email',
				status: 'success',
				attemptCount: 1,
				providerMessageId: 'm-1',
			},
			{
				recipientId: 'r2',
				channel: 'webhook',
				status: webhookStatus,
				attemptCount: 5,
				error:
					webhookStatus === 'success'
						? undefined
						: 'HTTP 503 from receiver',
			},
		],
	}
}

/**
 * Mount the detail with the server answering this run.
 *
 * @param {object} answer The run the GET returns.
 * @return {Promise<object>} The wrapper.
 */
async function mountDetail(answer) {
	axios.get.mockResolvedValue({ data: answer })
	const wrapper = mount(RunDetailModal, {
		props: { open: true, exportData, run: { id: 'run-1', status: 'running' } },
	})
	await flushPromises()
	return wrapper
}

describe('RunDetailModal', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		setActivePinia(createPinia())
	})

	it('reads the run and shows its deliveries and files', async () => {
		const wrapper = await mountDetail(run('failed-permanent'))

		expect(axios.get).toHaveBeenCalledWith(`${BASE}/e1/runs/run-1`)
		expect(wrapper.find('[data-testid="run-detail-export"]').text()).toBe(
			'Weekly bezwaren',
		)
		expect(wrapper.text()).toContain('By hand by sanne')

		const deliveries = wrapper.findAll('[data-testid="run-detail-delivery"]')
		expect(deliveries).toHaveLength(2)
		const cells = deliveries[1].findAll('td').map((td) => td.text())
		expect(cells).toEqual([
			'Webhook: https://hooks.example.org/lp',
			'Webhook',
			'Failed for good',
			'5',
			'HTTP 503 from receiver',
		])
		expect(deliveries[0].findAll('td')[0].text()).toBe(
			'Email: wethouder@gemeente.nl',
		)

		const artefacts = wrapper.findAll('[data-testid="run-detail-artefact"]')
		expect(artefacts).toHaveLength(2)
		expect(artefacts[0].text()).toContain('PDF document')
		expect(artefacts[0].text()).toContain('2.0 KB')
		expect(
			artefacts[0]
				.find('[data-testid="run-detail-download"]')
				.attributes('href'),
		).toBe(
			'/remote.php/dav/files/sanne/Apps/LaunchPad/Exports/e1/Weekly%20bezwaren.pdf',
		)
		expect(
			artefacts[1].find('[data-testid="run-detail-download"]').exists(),
		).toBe(false)
		expect(artefacts[1].text()).toContain('Removed after the retention period')
	})

	it('offers Retry when a delivery failed for good, and shows the retried run', async () => {
		const wrapper = await mountDetail(run('failed-permanent'))
		axios.post.mockResolvedValue({ data: run('success') })

		const retry = wrapper.find('[data-testid="run-detail-retry"]')
		expect(retry.exists()).toBe(true)
		await retry.trigger('click')
		await flushPromises()

		expect(axios.post).toHaveBeenCalledWith(`${BASE}/e1/runs/run-1/retry`)
		expect(wrapper.emitted('retried')[0][0].status).toBe('success')
		expect(wrapper.find('[data-testid="run-detail-retry"]').exists()).toBe(false)
		expect(
			wrapper.findAll('[data-testid="run-detail-delivery"]')[1].text(),
		).toContain('Succeeded')
	})

	it('offers no Retry when no delivery failed for good', async () => {
		expect(
			(await mountDetail(run('success')))
				.find('[data-testid="run-detail-retry"]')
				.exists(),
		).toBe(false)
		expect(
			(await mountDetail(run('retrying')))
				.find('[data-testid="run-detail-retry"]')
				.exists(),
		).toBe(false)
	})

	it("shows the server's reason when the retry is refused", async () => {
		const wrapper = await mountDetail(run('failed-permanent'))
		axios.post.mockRejectedValue({
			response: { status: 404, data: { error: 'Run not found' } },
		})

		await wrapper.find('[data-testid="run-detail-retry"]').trigger('click')
		await flushPromises()

		expect(wrapper.find('[data-testid="run-detail-error"]').text()).toBe(
			'Run not found',
		)
	})
})
