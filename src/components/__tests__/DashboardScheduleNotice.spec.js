/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * DashboardScheduleNotice: the header line for a scheduled go-live and a
 * take-down time (REQ-SCHEDUI-001, REQ-SCHEDUI-002).
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import DashboardScheduleNotice from '../DashboardScheduleNotice.vue'
import { formatStoredTime } from '../../utils/scheduleTime.js'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app, text, vars = {}) =>
		text.replace(/\{(\w+)\}/g, (_m, k) => vars[k] ?? `{${k}}`),
}))

describe('DashboardScheduleNotice', () => {
	it('says when a scheduled dashboard goes live', () => {
		const wrapper = mount(DashboardScheduleNotice, {
			props: {
				dashboard: {
					publicationStatus: 'scheduled',
					publishAt: '2099-05-01 07:00:00',
				},
			},
		})
		expect(wrapper.text()).toBe(
			`Goes live on ${formatStoredTime('2099-05-01 07:00:00')}`,
		)
	})

	it('says when a dashboard comes down', () => {
		const wrapper = mount(DashboardScheduleNotice, {
			props: {
				dashboard: {
					publicationStatus: 'published',
					unpublishAt: '2099-05-02 15:00:00',
				},
			},
		})
		expect(wrapper.text()).toBe(
			`Comes down on ${formatStoredTime('2099-05-02 15:00:00')}`,
		)
	})

	it('renders nothing for a plain published dashboard or a passed go-live', () => {
		const plain = mount(DashboardScheduleNotice, {
			props: { dashboard: { publicationStatus: 'published' } },
		})
		expect(
			plain.find('[data-testid="dashboard-schedule-notice"]').exists(),
		).toBe(false)
		const passed = mount(DashboardScheduleNotice, {
			props: {
				dashboard: {
					publicationStatus: 'scheduled',
					publishAt: '2000-01-01 00:00:00',
				},
			},
		})
		expect(
			passed.find('[data-testid="dashboard-schedule-notice"]').exists(),
		).toBe(false)
	})
})
