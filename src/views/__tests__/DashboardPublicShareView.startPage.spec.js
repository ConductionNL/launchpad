/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * sharing-public-start-page REQ-PSP-003: as the public start page the view
 * carries a bar with the organisation's name and a "Sign in" link to the
 * login form with `direct=1`; as a plain share page it has no such bar. The
 * data still comes from the share's own `/s/{token}/data`.
 *
 * Only HTTP, the share store and child components are doubled.
 *
 * @spec openspec/changes/sharing-public-start-page/specs/dashboard-public-share/spec.md#requirement-the-start-page-offers-a-way-in-for-staff-req-psp-003
 */

import axios from '@nextcloud/axios'
import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { h } from 'vue'
import DashboardPublicShareView from '../DashboardPublicShareView.vue'

vi.mock('@nextcloud/axios', () => ({
	default: { get: vi.fn(), post: vi.fn() },
}))
vi.mock('@nextcloud/router', () => ({
	generateUrl: (path) => '/index.php' + path,
}))
vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...(await importOriginal()),
	translate: (_app, text) => text,
}))
vi.mock('../../stores/publicShares.js', () => ({
	usePublicShareStore: () => ({ isUnlocked: vi.fn(), clearUnlocked: vi.fn(), markUnlocked: vi.fn() }),
}))

/**
 * Mount the view.
 *
 * @param {object} props The props
 * @return {Promise<object>} The wrapper, after the first load settled
 */
async function mountView(props) {
	const wrapper = mount(DashboardPublicShareView, {
		props,
		global: {
			stubs: {
				PublicSharePasswordDialog: true,
				PublicDashboardGrid: { name: 'PublicDashboardGrid', render: () => h('div', { class: 'grid' }) },
				NcLoadingIcon: true,
				NcEmptyContent: { name: 'NcEmptyContent', props: ['name'], render() { return h('div', { class: 'empty' }, this.name) } },
				AlertCircleIcon: true,
			},
		},
	})
	await flushPromises()
	return wrapper
}

beforeEach(() => {
	vi.clearAllMocks()
	window.OC = { ...(window.OC || {}), theme: { name: 'Bibliotheek Noord' } }
	axios.get.mockResolvedValue({ data: { dashboard: { name: 'Bibliotheek Noord' }, share: {}, placements: [] } })
})

afterEach(() => {
	delete window.OC.theme
})

describe('DashboardPublicShareView as the public start page', () => {
	it('shows the organisation and a Sign in link to the login form with direct=1', async () => {
		const wrapper = await mountView({ token: 'Bib1Noord', startPage: true })

		const bar = wrapper.find('nav.public-share-view__start-bar')
		expect(bar.exists()).toBe(true)
		expect(bar.attributes('aria-label')).toBe('Sign in')
		expect(bar.find('.public-share-view__organisation').text()).toBe('Bibliotheek Noord')
		const link = bar.find('a.public-share-view__sign-in')
		expect(link.text()).toBe('Sign in')
		expect(link.attributes('href')).toBe('/index.php/login?direct=1')
	})

	it('reads the chosen link through the share data endpoint', async () => {
		await mountView({ token: 'Bib1Noord', startPage: true })

		expect(axios.get).toHaveBeenCalledWith('/index.php/apps/launchpad/s/Bib1Noord/data', { headers: {} })
	})

	it('keeps the way in when the link cannot be loaded', async () => {
		axios.get.mockRejectedValue(Object.assign(new Error('HTTP 404'), { response: { status: 404, data: {} } }))
		const wrapper = await mountView({ token: 'Bib1Noord', startPage: true })

		expect(wrapper.find('.empty').text()).toBe('Not found')
		expect(wrapper.find('a.public-share-view__sign-in').exists()).toBe(true)
	})

	it('has no sign-in bar on a plain share page', async () => {
		const wrapper = await mountView({ token: 'abc123' })

		expect(wrapper.find('nav.public-share-view__start-bar').exists()).toBe(false)
		expect(wrapper.find('h1').text()).toBe('Bibliotheek Noord')
	})
})
