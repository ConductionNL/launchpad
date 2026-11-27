/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * SharingScreenLink: the "Show on a screen" block of the Sharing tab
 * (dashboard-show-on-a-screen REQ-KIOSK-006, REQ-KIOSK-007), in each state.
 *
 * @spec openspec/changes/archive/2026-11-27-dashboard-show-on-a-screen/tasks.md#task-8
 */

import axios from '@nextcloud/axios'
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import SharingScreenLink from '../SharingScreenLink.vue'

const policy = vi.hoisted(() => ({ value: null }))

vi.mock('@nextcloud/axios', () => ({
	default: { get: vi.fn(), post: vi.fn(), delete: vi.fn() },
}))
vi.mock('@nextcloud/router', () => ({
	generateUrl: (path, vars = {}) => `/index.php${path.replace(/\{(\w+)\}/g, (_m, k) => vars[k])}`,
}))
vi.mock('@nextcloud/l10n', () => ({
	t: (_app, text, vars = {}) =>
		text.replace(/\{(\w+)\}/g, (_m, k) => vars[k] ?? `{${k}}`),
	n: (_app, one, many, count) => (count === 1 ? one : many).replace('%n', String(count)),
}))
vi.mock('../../../../utils/loadInitialState.js', () => ({
	readSharingPolicy: () => policy.value,
}))
vi.mock('../../../../utils/logger.js', () => ({ logger: { error: vi.fn() } }))
vi.mock('@conduction/nextcloud-vue', () => ({
	NcButton: {
		name: 'NcButton',
		props: ['disabled', 'variant'],
		emits: ['click'],
		template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
	},
	NcLoadingIcon: { name: 'NcLoadingIcon', template: '<span class="loading" />' },
	NcDialog: { name: 'NcDialog', template: '<div><slot /><slot name="actions" /></div>' },
}))

const kantine = { uuid: 'dash-kantine', name: 'Kantine' }
const link = { token: 'kan123', url: 'https://cloud.example/index.php/apps/launchpad/kiosk/kan123', screenDashboardUuid: 'dash-kantine', refreshSeconds: 300, createdAt: '2026-11-27 10:58:00' }

function mountBlock() {
	return mount(SharingScreenLink, { props: { dashboard: kantine } })
}

beforeEach(() => {
	vi.clearAllMocks()
	policy.value = { mayShare: true, publicLinksAllowed: true, screenLinksAllowed: true }
	axios.get.mockResolvedValue({ status: 204, data: '' })
})

describe('SharingScreenLink (REQ-KIOSK-006, REQ-KIOSK-007)', () => {
	it('offers Create screen link when the dashboard has none', async () => {
		const wrapper = mountBlock()
		await flushPromises()

		expect(axios.get).toHaveBeenCalledWith('/index.php/apps/launchpad/api/dashboards/dash-kantine/screen-link')
		expect(wrapper.find('[data-test="sharing-screen-link-create"]').exists()).toBe(true)
		expect(wrapper.find('[data-test="sharing-screen-link-row"]').exists()).toBe(false)
	})

	it('creates the link and shows it with Copy and Revoke', async () => {
		axios.post.mockResolvedValue({ status: 201, data: link })
		const wrapper = mountBlock()
		await flushPromises()

		await wrapper.find('[data-test="sharing-screen-link-create"]').trigger('click')
		await flushPromises()

		expect(axios.post).toHaveBeenCalledWith('/index.php/apps/launchpad/api/dashboards/dash-kantine/screen-link')
		expect(wrapper.find('[data-test="sharing-screen-link-url"]').element.value).toBe(link.url)
		expect(wrapper.find('[data-test="sharing-screen-link-copy"]').exists()).toBe(true)
		expect(wrapper.find('[data-test="sharing-screen-link-revoke"]').exists()).toBe(true)
		expect(wrapper.find('[data-test="sharing-screen-link-create"]').exists()).toBe(false)
	})

	it('says under the link that it is read only and how often it refreshes (board LpDelen)', async () => {
		axios.get.mockResolvedValue({ status: 200, data: link })
		const wrapper = mountBlock()
		await flushPromises()

		const meta = wrapper.find('[data-test="sharing-screen-link-meta"]').text()
		expect(meta).toMatch(/^Read only · refreshes every 5 minutes · created /)
	})

	it('copies the link and says so in a live region', async () => {
		axios.get.mockResolvedValue({ status: 200, data: link })
		const writeText = vi.fn().mockResolvedValue()
		Object.assign(navigator, { clipboard: { writeText } })
		const wrapper = mountBlock()
		await flushPromises()

		await wrapper.find('[data-test="sharing-screen-link-copy"]').trigger('click')
		await flushPromises()

		expect(writeText).toHaveBeenCalledWith(link.url)
		expect(wrapper.find('[aria-live="polite"]').text()).toBe('Link copied')
	})

	it('revokes only after the confirmation', async () => {
		axios.get.mockResolvedValue({ status: 200, data: link })
		axios.delete.mockResolvedValue({ status: 204 })
		const wrapper = mountBlock()
		await flushPromises()

		await wrapper.find('[data-test="sharing-screen-link-revoke"]').trigger('click')
		expect(axios.delete).not.toHaveBeenCalled()
		await wrapper.find('[data-test="revoke-screen-link-confirm"]').trigger('click')
		await flushPromises()

		expect(axios.delete).toHaveBeenCalledWith('/index.php/apps/launchpad/api/dashboards/dash-kantine/screen-link')
		expect(wrapper.find('[data-test="sharing-screen-link-create"]').exists()).toBe(true)
	})

	it('shows nothing to someone who may not share', async () => {
		policy.value = { mayShare: false, publicLinksAllowed: true, screenLinksAllowed: true }
		const wrapper = mountBlock()
		await flushPromises()

		expect(axios.get).not.toHaveBeenCalled()
		expect(wrapper.find('[data-test="sharing-screen-link"]').exists()).toBe(false)
	})

	it('with the switch off and no link, offers nothing', async () => {
		policy.value = { mayShare: true, publicLinksAllowed: true, screenLinksAllowed: false }
		const wrapper = mountBlock()
		await flushPromises()

		expect(wrapper.find('[data-test="sharing-screen-link"]').exists()).toBe(false)
	})

	it('with the switch off, keeps an existing link with Revoke and says new ones are off', async () => {
		policy.value = { mayShare: true, publicLinksAllowed: true, screenLinksAllowed: false }
		axios.get.mockResolvedValue({ status: 200, data: link })
		const wrapper = mountBlock()
		await flushPromises()

		expect(wrapper.find('[data-test="sharing-screen-link-revoke"]').exists()).toBe(true)
		expect(wrapper.find('[data-test="sharing-screen-link-off"]').text()).toBe('New screen links are switched off.')
		expect(wrapper.find('[data-test="sharing-screen-link-create"]').exists()).toBe(false)
	})

	it('says so when creating fails', async () => {
		axios.post.mockRejectedValue(new Error('403'))
		const wrapper = mountBlock()
		await flushPromises()

		await wrapper.find('[data-test="sharing-screen-link-create"]').trigger('click')
		await flushPromises()

		expect(wrapper.find('[data-test="sharing-screen-link-error"]').text()).toBe('The screen link could not be created.')
	})
})
