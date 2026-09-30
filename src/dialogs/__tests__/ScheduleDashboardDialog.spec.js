/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * ScheduleDashboardDialog: go-live and take-down times for a dashboard
 * (sharing-dashboard-schedule-screen REQ-SCHEDUI-001, REQ-SCHEDUI-002).
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import ScheduleDashboardDialog from '../ScheduleDashboardDialog.vue'

const scheduleDashboard = vi.fn()

vi.mock('../../stores/dashboard.js', () => ({
	useDashboardStore: () => ({ scheduleDashboard }),
}))

vi.mock('@nextcloud/l10n', () => ({
	t: (_app, text, vars = {}) =>
		text.replace(/\{(\w+)\}/g, (_m, k) => vars[k] ?? `{${k}}`),
}))

vi.mock('@conduction/nextcloud-vue', () => ({
	NcDialog: {
		name: 'NcDialog',
		props: ['name', 'open'],
		template:
			'<div v-if="open" class="dialog"><h2>{{ name }}</h2><slot /><slot name="actions" /></div>',
	},
	NcButton: {
		name: 'NcButton',
		props: ['disabled'],
		emits: ['click'],
		template:
			'<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
	},
}))

// The exact error the server answers for a past go-live time
// (DashboardApiController::schedule, 400 invalid_argument).
function pastDateError() {
	return Object.assign(new Error('Request failed with status code 400'), {
		response: {
			status: 400,
			data: {
				status: 'error',
				error: 'invalid_argument',
				message: 'publishAt must be a future timestamp',
			},
		},
	})
}

function mountDialog(dashboard = { uuid: 'd-1', name: 'Open day' }) {
	return mount(ScheduleDashboardDialog, { props: { open: true, dashboard } })
}

describe('ScheduleDashboardDialog', () => {
	beforeEach(() => {
		scheduleDashboard.mockReset()
	})

	it('schedules a go-live time and closes', async () => {
		scheduleDashboard.mockResolvedValue({
			uuid: 'd-1',
			publicationStatus: 'scheduled',
		})
		const wrapper = mountDialog()
		await wrapper
			.find('[data-testid="schedule-publish-at"]')
			.setValue('2030-05-01T09:00')
		await wrapper.find('[data-testid="schedule-save"]').trigger('click')
		await flushPromises()

		expect(scheduleDashboard).toHaveBeenCalledWith(
			'd-1',
			new Date('2030-05-01T09:00').toISOString(),
			null,
		)
		expect(wrapper.emitted('scheduled')[0][0].publicationStatus).toBe(
			'scheduled',
		)
		expect(wrapper.emitted('update:open')[0]).toEqual([false])
	})

	it('sends a take-down time with the go-live time', async () => {
		scheduleDashboard.mockResolvedValue({ uuid: 'd-1' })
		const wrapper = mountDialog()
		await wrapper
			.find('[data-testid="schedule-publish-at"]')
			.setValue('2030-05-01T09:00')
		await wrapper
			.find('[data-testid="schedule-unpublish-at"]')
			.setValue('2030-05-02T17:00')
		await wrapper.find('[data-testid="schedule-save"]').trigger('click')
		await flushPromises()

		expect(scheduleDashboard).toHaveBeenCalledWith(
			'd-1',
			new Date('2030-05-01T09:00').toISOString(),
			new Date('2030-05-02T17:00').toISOString(),
		)
	})

	it('shows the refusal for a past time and stays open', async () => {
		scheduleDashboard.mockRejectedValue(pastDateError())
		const wrapper = mountDialog()
		await wrapper
			.find('[data-testid="schedule-publish-at"]')
			.setValue('2020-01-01T09:00')
		await wrapper.find('[data-testid="schedule-save"]').trigger('click')
		await flushPromises()

		expect(wrapper.find('[data-testid="schedule-problem"]').text()).toBe(
			'The go-live time must be in the future.',
		)
		expect(wrapper.emitted('scheduled')).toBeUndefined()
		expect(wrapper.emitted('update:open')).toBeUndefined()
	})

	it('asks for at least one time before calling the server', async () => {
		const wrapper = mountDialog()
		await wrapper.find('[data-testid="schedule-save"]').trigger('click')
		await flushPromises()

		expect(scheduleDashboard).not.toHaveBeenCalled()
		expect(wrapper.find('[data-testid="schedule-problem"]').text()).toBe(
			'Enter a go-live time, a take-down time or both.',
		)
	})

	it('starts from the times the dashboard already has', () => {
		const wrapper = mountDialog({
			uuid: 'd-1',
			name: 'Open day',
			publishAt: '2030-05-01 07:00:00',
			unpublishAt: null,
		})
		const input = wrapper.find('[data-testid="schedule-publish-at"]').element
		expect(new Date(input.value).toISOString()).toBe('2030-05-01T07:00:00.000Z')
	})
})
