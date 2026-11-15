/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `DashboardPublicShareView.vue`, the anonymous
 * read-only view of a shared dashboard: the data request for its token, the
 * rendered dashboard, the password prompt on a protected share (and dropping
 * a stale unlock), not-found and server errors, and the unlock flow — wrong
 * password, throttling, a failed check, a double submit, and a successful
 * unlock that reloads with the password.
 *
 * Only HTTP, the share store and child components are doubled.
 */

import axios from '@nextcloud/axios'
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
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

const shareStore = {
	isUnlocked: vi.fn(),
	clearUnlocked: vi.fn(),
	markUnlocked: vi.fn(),
}
vi.mock('../../stores/publicShares.js', () => ({
	usePublicShareStore: () => shareStore,
}))

const TOKEN = 'ab/c d'
const DATA_URL = '/index.php/apps/launchpad/s/ab%2Fc%20d/data'
const UNLOCK_URL = '/index.php/apps/launchpad/s/ab%2Fc%20d/unlock'

/**
 * An error as axios rejects with it.
 *
 * @param {number} status The HTTP status
 * @param {object} data The response body
 * @return {Error} The error
 */
function httpError(status, data = {}) {
	return Object.assign(new Error('HTTP ' + status), { response: { status, data } })
}

/**
 * Mount the view for the test token.
 *
 * @return {Promise<object>} The wrapper, after the first load settled
 */
async function mountView() {
	const wrapper = mount(DashboardPublicShareView, {
		props: { token: TOKEN },
		global: {
			stubs: {
				PublicSharePasswordDialog: {
					name: 'PublicSharePasswordDialog',
					props: ['error', 'loading'],
					emits: ['unlock'],
					render: () => h('div', { class: 'password-dialog' }),
				},
				PublicDashboardGrid: { name: 'PublicDashboardGrid', render: () => h('div', { class: 'grid' }) },
				NcLoadingIcon: { name: 'NcLoadingIcon', render: () => h('span', { class: 'loading-icon' }) },
				NcEmptyContent: {
					name: 'NcEmptyContent',
					props: ['name', 'description'],
					render() {
						return h('div', { class: 'empty' }, [this.name, ' | ', this.description])
					},
				},
				AlertCircleIcon: true,
			},
		},
	})
	await flushPromises()
	return wrapper
}

const dialog = (wrapper) => wrapper.findComponent({ name: 'PublicSharePasswordDialog' })

beforeEach(() => {
	vi.clearAllMocks()
	shareStore.isUnlocked.mockReturnValue(false)
})

describe('DashboardPublicShareView loading', () => {
	it('loads the share for its encoded token without a password header', async () => {
		axios.get.mockResolvedValue({ data: { dashboard: { name: 'Board' }, share: {}, placements: [] } })
		await mountView()

		expect(axios.get).toHaveBeenCalledWith(DATA_URL, { headers: {} })
	})

	it('renders the dashboard read-only with its name and description', async () => {
		axios.get.mockResolvedValue({
			data: { dashboard: { name: 'Board', description: 'Weekly numbers' }, share: { token: TOKEN }, placements: [{ id: 1 }] },
		})
		const wrapper = await mountView()

		expect(wrapper.find('h1').text()).toBe('Board')
		expect(wrapper.find('.public-share-view__badge').text()).toBe('Read-only view')
		expect(wrapper.find('.public-share-view__description').text()).toBe('Weekly numbers')
		expect(wrapper.findComponent({ name: 'PublicDashboardGrid' }).exists()).toBe(true)
		expect(wrapper.vm.share).toEqual({ token: TOKEN })
	})

	it('falls back to the title and leaves out an empty description', async () => {
		axios.get.mockResolvedValue({ data: { dashboard: { title: 'Titled' }, share: {} } })
		const wrapper = await mountView()

		expect(wrapper.find('h1').text()).toBe('Titled')
		expect(wrapper.find('.public-share-view__description').exists()).toBe(false)
	})

	it('shows the loading icon while the share loads', async () => {
		axios.get.mockReturnValue(new Promise(() => {}))
		const wrapper = await mountView()

		expect(wrapper.find('.public-share-view__loading').exists()).toBe(true)
		expect(wrapper.findComponent({ name: 'NcLoadingIcon' }).exists()).toBe(true)
	})

	it('asks for the password on a protected share', async () => {
		axios.get.mockRejectedValue(httpError(401, { passwordRequired: true }))
		const wrapper = await mountView()

		expect(dialog(wrapper).exists()).toBe(true)
		expect(dialog(wrapper).props()).toEqual({ error: null, loading: false })
		expect(shareStore.clearUnlocked).not.toHaveBeenCalled()
	})

	it('drops a stored unlock the server no longer accepts', async () => {
		shareStore.isUnlocked.mockReturnValue(true)
		axios.get.mockRejectedValue(httpError(401, { passwordRequired: true }))
		await mountView()

		expect(shareStore.isUnlocked).toHaveBeenCalledWith(TOKEN)
		expect(shareStore.clearUnlocked).toHaveBeenCalledWith(TOKEN)
	})

	it('says a missing or expired share was not found', async () => {
		axios.get.mockRejectedValue(httpError(404))
		const wrapper = await mountView()

		expect(wrapper.vm.error).toBe('not_found')
		expect(wrapper.find('.empty').text()).toBe('Not found | This shared dashboard does not exist or has expired.')
		expect(dialog(wrapper).exists()).toBe(false)
	})

	it('treats any other failure, including a 401 without a password, as a server error', async () => {
		for (const failure of [httpError(500), httpError(401, {}), new Error('offline')]) {
			axios.get.mockRejectedValueOnce(failure)
			const wrapper = await mountView()

			expect(wrapper.vm.error).toBe('server_error')
			expect(wrapper.find('.empty').text()).toBe('Error | An error occurred loading the shared dashboard.')
			wrapper.unmount()
		}
	})
})

describe('DashboardPublicShareView unlock', () => {
	/**
	 * Mount on a protected share.
	 *
	 * @return {Promise<object>} The wrapper showing the password dialog
	 */
	async function protectedView() {
		axios.get.mockRejectedValueOnce(httpError(401, { passwordRequired: true }))
		return mountView()
	}

	it('unlocks, remembers the token and reloads with the password header', async () => {
		const wrapper = await protectedView()
		axios.post.mockResolvedValue({ data: { access: true } })
		axios.get.mockResolvedValueOnce({ data: { dashboard: { name: 'Secret board' }, share: {}, placements: [] } })

		await dialog(wrapper).vm.$emit('unlock', 'hunter2')
		await flushPromises()

		expect(axios.post).toHaveBeenCalledWith(UNLOCK_URL, { password: 'hunter2' })
		expect(shareStore.markUnlocked).toHaveBeenCalledWith(TOKEN)
		expect(axios.get).toHaveBeenLastCalledWith(DATA_URL, { headers: { 'X-Share-Password': 'hunter2' } })
		expect(dialog(wrapper).exists()).toBe(false)
		expect(wrapper.find('h1').text()).toBe('Secret board')
		expect(wrapper.vm.unlocking).toBe(false)
	})

	it('reports a wrong password without reloading', async () => {
		const wrapper = await protectedView()
		axios.post.mockResolvedValue({ data: { access: false } })

		await dialog(wrapper).vm.$emit('unlock', 'nope')
		await flushPromises()

		expect(shareStore.markUnlocked).not.toHaveBeenCalled()
		expect(axios.get).toHaveBeenCalledTimes(1)
		expect(dialog(wrapper).props()).toEqual({ error: 'Incorrect password. Please try again.', loading: false })
	})

	it('reports a throttled unlock', async () => {
		const wrapper = await protectedView()
		axios.post.mockRejectedValue(httpError(429))

		await dialog(wrapper).vm.$emit('unlock', 'again')
		await flushPromises()

		expect(dialog(wrapper).props('error')).toBe('unlock_throttled')
	})

	it('reports an unlock request that fails otherwise', async () => {
		const wrapper = await protectedView()
		axios.post.mockRejectedValue(httpError(500))

		await dialog(wrapper).vm.$emit('unlock', 'x')
		await flushPromises()

		expect(dialog(wrapper).props('error')).toBe('Failed to verify password.')
		expect(wrapper.vm.unlocking).toBe(false)
	})

	it('ignores a second submit while one is being checked, and shows it is busy', async () => {
		const wrapper = await protectedView()
		let answer
		axios.post.mockReturnValue(new Promise((resolve) => { answer = resolve }))

		await dialog(wrapper).vm.$emit('unlock', 'first')
		expect(dialog(wrapper).props('loading')).toBe(true)
		await dialog(wrapper).vm.$emit('unlock', 'second')

		expect(axios.post).toHaveBeenCalledTimes(1)
		answer({ data: { access: false } })
		await flushPromises()
		expect(dialog(wrapper).props('loading')).toBe(false)
	})

	it('clears an earlier unlock error when trying again', async () => {
		const wrapper = await protectedView()
		axios.post.mockResolvedValueOnce({ data: { access: false } })
		await dialog(wrapper).vm.$emit('unlock', 'wrong')
		await flushPromises()
		expect(dialog(wrapper).props('error')).not.toBeNull()

		let answer
		axios.post.mockReturnValueOnce(new Promise((resolve) => { answer = resolve }))
		await dialog(wrapper).vm.$emit('unlock', 'retry')
		expect(dialog(wrapper).props('error')).toBeNull()
		answer({ data: { access: false } })
		await flushPromises()
	})
})
