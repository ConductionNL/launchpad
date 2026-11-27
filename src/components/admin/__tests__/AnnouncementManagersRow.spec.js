/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The read-only "Manage announcements" row of the Roles tab: the delegated
 * groups, none yet, or unreadable, and always the link to Administration
 * privileges (announcements-managed-in-settings D1).
 *
 * @spec openspec/changes/announcements-managed-in-settings/tasks.md#task-3
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import AnnouncementManagersRow from '../AnnouncementManagersRow.vue'
import { announcementsApi } from '../../../services/announcementsApi.js'

vi.mock('@nextcloud/router', async (importOriginal) => ({ ...(await importOriginal()), generateUrl: (path) => `/index.php${path}` }))
vi.mock('../../../services/announcementsApi.js', () => ({ announcementsApi: { delegation: vi.fn() } }))
vi.mock('../../../utils/logger.js', () => ({ logger: { error: vi.fn() } }))

const NcButton = { props: ['href', 'variant'], template: '<a :href="href"><slot /></a>' }
const mountRow = () => mount(AnnouncementManagersRow, { global: { mocks: { t: (_app, text) => text }, stubs: { NcButton } } })

beforeEach(() => {
	vi.clearAllMocks()
})

describe('AnnouncementManagersRow', () => {
	it('shows the delegated groups and links to Administration privileges', async () => {
		announcementsApi.delegation.mockResolvedValue({ available: true, groups: [{ id: 'redactie', displayName: 'Redactie' }] })
		const wrapper = mountRow()
		await flushPromises()

		expect(wrapper.find('[data-testid="announcement-managers-groups"]').text()).toBe('Redactie')
		expect(wrapper.find('[data-testid="announcement-managers-link"]').attributes('href')).toBe('/index.php/settings/admin/admindelegation')
	})

	it('says when no group holds it, and when it cannot be read', async () => {
		announcementsApi.delegation.mockResolvedValue({ available: true, groups: [] })
		const none = mountRow()
		await flushPromises()
		expect(none.find('[data-testid="announcement-managers-none"]').exists()).toBe(true)

		announcementsApi.delegation.mockResolvedValue({ available: false, groups: [] })
		const unavailable = mountRow()
		await flushPromises()
		expect(unavailable.find('[data-testid="announcement-managers-unavailable"]').exists()).toBe(true)

		announcementsApi.delegation.mockRejectedValue(new Error('403'))
		const failed = mountRow()
		await flushPromises()
		expect(failed.find('[data-testid="announcement-managers-unavailable"]').exists()).toBe(true)
	})
})
