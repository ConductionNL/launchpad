/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * ScheduledExportModal (scheduled-exports task 12): a new export is POSTed
 * and an existing one PATCHed to the export API in the shape
 * ScheduledExportService::validate() and RecurrenceResolver::validate()
 * accept; a 400 shows the server's reason. Only HTTP, the router and l10n
 * are mocked; the store is the real one.
 */

import axios from '@nextcloud/axios'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import ScheduledExportModal from '../ScheduledExportModal.vue'

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

const subjects = [
	{ type: 'dashboard', id: 'd-1', label: 'Bezwaren' },
	{ type: 'widget', id: '7', label: 'KPI' },
]

/**
 * Mount the editor.
 *
 * @param {object} props Extra props.
 * @return {object} The wrapper.
 */
function mountEditor(props = {}) {
	return mount(ScheduledExportModal, {
		props: {
			open: true,
			subjects,
			defaultSubject: { type: 'dashboard', id: 'd-1' },
			...props,
		},
	})
}

const byTestId = (wrapper, id) => wrapper.find(`[data-testid="${id}"]`)

describe('ScheduledExportModal', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		setActivePinia(createPinia())
	})

	it('creates a weekly PDF-by-email export with a POST in the validated shape', async () => {
		axios.post.mockResolvedValue({ data: { id: 'e1', name: 'Weekly bezwaren' } })
		const wrapper = mountEditor()

		await byTestId(wrapper, 'export-name').setValue('Weekly bezwaren')
		await byTestId(wrapper, 'export-day-WE').find('input').setValue(true)
		await byTestId(wrapper, 'export-time').setValue('08:30')
		await byTestId(wrapper, 'export-timezone').setValue('Europe/Amsterdam')
		await byTestId(wrapper, 'export-recipient-addresses').setValue(
			'wethouder@gemeente.nl, griffie@gemeente.nl',
		)
		expect(byTestId(wrapper, 'export-recurrence-summary').text()).toBe(
			'Runs every Monday, Wednesday at 08:30.',
		)

		await byTestId(wrapper, 'export-save').trigger('click')
		await flushPromises()

		expect(axios.post).toHaveBeenCalledTimes(1)
		const [url, body] = axios.post.mock.calls[0]
		expect(url).toBe(BASE)
		expect(body).toEqual({
			name: 'Weekly bezwaren',
			description: '',
			enabled: true,
			subjectType: 'dashboard',
			subjectId: 'd-1',
			runTimezone: 'Europe/Amsterdam',
			recurrence: {
				type: 'weekly',
				daysOfWeek: ['MO', 'WE'],
				timeOfDay: '08:30',
			},
			renderTargets: [
				{
					format: 'pdf',
					formatOptions: {
						pageSize: 'A4',
						orientation: 'portrait',
						header: true,
						footer: true,
					},
					filenamePattern: '{name}-{date}.{ext}',
				},
			],
			recipients: [
				{
					enabled: true,
					channel: 'email',
					channelConfig: {
						addresses: ['wethouder@gemeente.nl', 'griffie@gemeente.nl'],
					},
				},
			],
		})
		expect(wrapper.emitted('saved')[0][0]).toEqual({
			id: 'e1',
			name: 'Weekly bezwaren',
		})
		expect(wrapper.emitted('close')).toHaveLength(1)
	})

	it("shows the server's validation error and stays open", async () => {
		axios.post.mockRejectedValue({
			response: {
				status: 400,
				data: {
					error: 'invalid_argument',
					message: 'The name is required and at most 255 characters.',
				},
			},
		})
		const wrapper = mountEditor()

		await byTestId(wrapper, 'export-save').trigger('click')
		await flushPromises()

		const alert = byTestId(wrapper, 'export-editor-error')
		expect(alert.exists()).toBe(true)
		expect(alert.attributes('role')).toBe('alert')
		expect(alert.text()).toBe('The name is required and at most 255 characters.')
		expect(wrapper.emitted('close')).toBeUndefined()
		expect(wrapper.emitted('saved')).toBeUndefined()
	})

	it('edits an export with a PATCH, keeping redacted secrets and the filter context', async () => {
		axios.patch.mockResolvedValue({ data: { id: 'e9', name: 'CBS return' } })
		const exportData = {
			id: 'e9',
			name: 'CBS return',
			description: 'Maandelijks',
			enabled: true,
			subjectType: 'widget',
			subjectId: '7',
			runTimezone: 'Europe/Amsterdam',
			recurrence: { type: 'monthly', dayOfMonth: 1, timeOfDay: '08:00' },
			nextRunAt: '2026-12-01T08:00:00+01:00',
			renderTargets: [
				{
					format: 'xlsx',
					formatOptions: { sheetPerWidget: true },
					filenamePattern: 'cbs-{date}.{ext}',
				},
			],
			recipients: [
				{
					id: 'r1',
					enabled: true,
					channel: 'sftp',
					filterContext: { gemeenteCode: 'GM0355' },
					channelConfig: {
						host: 'sftp.cbs.nl',
						port: 22,
						username: 'gm0355',
						password: '********',
						remotePath: '/dropbox',
					},
					retryPolicy: { maxAttempts: 3 },
				},
			],
		}
		const wrapper = mountEditor({ exportData })

		expect(byTestId(wrapper, 'export-name').element.value).toBe('CBS return')
		expect(
			wrapper.findComponent({ name: 'NcPasswordField' }).props('helperText'),
		).toBe('Saved. Leave it as it is to keep it, or type a new one.')

		await byTestId(wrapper, 'export-last-day').find('input').setValue(true)
		await byTestId(wrapper, 'field-host').setValue('sftp2.cbs.nl')
		await byTestId(wrapper, 'export-filter-add').trigger('click')
		const filters = wrapper.findAll('[data-testid="export-filter"]')
		expect(filters).toHaveLength(2)
		await filters[1].find('[data-testid="export-filter-field"]').setValue('jaar')
		await filters[1].find('[data-testid="export-filter-value"]').setValue('2026')

		await byTestId(wrapper, 'export-save').trigger('click')
		await flushPromises()

		const [url, body] = axios.patch.mock.calls[0]
		expect(url).toBe(`${BASE}/e9`)
		expect(body.id).toBe('e9')
		expect(body.nextRunAt).toBeUndefined()
		expect(body.subjectType).toBe('widget')
		expect(body.subjectId).toBe('7')
		expect(body.recurrence).toEqual({
			type: 'monthly',
			lastDay: true,
			timeOfDay: '08:00',
		})
		expect(body.renderTargets).toEqual([
			{
				format: 'xlsx',
				formatOptions: { sheetPerWidget: true },
				filenamePattern: 'cbs-{date}.{ext}',
			},
		])
		expect(body.recipients).toEqual([
			{
				id: 'r1',
				enabled: true,
				channel: 'sftp',
				retryPolicy: { maxAttempts: 3 },
				filterContext: { gemeenteCode: 'GM0355', jaar: '2026' },
				channelConfig: {
					host: 'sftp2.cbs.nl',
					port: 22,
					username: 'gm0355',
					password: '********',
					remotePath: '/dropbox',
				},
			},
		])
	})

	it('sends a cron recurrence with only its expression', async () => {
		axios.post.mockResolvedValue({ data: { id: 'e2' } })
		const wrapper = mountEditor()

		await byTestId(wrapper, 'export-recurrence-type').setValue(
			'Custom (cron expression)',
		)
		expect(byTestId(wrapper, 'export-time').exists()).toBe(false)
		await byTestId(wrapper, 'export-cron').setValue('0 2 * * 1-5')
		await byTestId(wrapper, 'export-save').trigger('click')
		await flushPromises()

		expect(axios.post.mock.calls[0][1].recurrence).toEqual({
			type: 'cron',
			expression: '0 2 * * 1-5',
		})
	})

	it('adds a render target with its own format options and a webhook recipient', async () => {
		axios.post.mockResolvedValue({ data: { id: 'e3' } })
		const wrapper = mountEditor()

		expect(
			byTestId(wrapper, 'export-target-remove').attributes('disabled'),
		).toBeDefined()
		await byTestId(wrapper, 'export-target-add').trigger('click')
		const targets = wrapper.findAll('[data-testid="export-target"]')
		expect(targets).toHaveLength(2)
		await targets[1]
			.find('[data-testid="export-target-format"]')
			.setValue('CSV table')
		await targets[1]
			.find('[data-testid="field-delimiter"]')
			.setValue('Semicolon (;)')

		await byTestId(wrapper, 'export-recipient-channel').setValue('Webhook')
		expect(byTestId(wrapper, 'export-recipient-addresses').exists()).toBe(false)
		await byTestId(wrapper, 'field-url').setValue('https://hooks.example.org/lp')
		await byTestId(wrapper, 'export-save').trigger('click')
		await flushPromises()

		const body = axios.post.mock.calls[0][1]
		expect(body.renderTargets.map((target) => target.format)).toEqual([
			'pdf',
			'csv',
		])
		expect(body.renderTargets[1].formatOptions).toEqual({
			delimiter: ';',
			quoteChar: '"',
		})
		// The empty signing secret is not sent, so nothing is stored for it.
		expect(body.recipients).toEqual([
			{
				enabled: true,
				channel: 'webhook',
				channelConfig: { url: 'https://hooks.example.org/lp' },
			},
		])
	})
})
