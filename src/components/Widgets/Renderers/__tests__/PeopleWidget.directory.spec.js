/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `PeopleWidget.vue` directory behaviour: layout and
 * column resolution, birthday badges, the request parameters, offset
 * pagination, the in-memory page cache, refresh, error state, and dropping a
 * late answer to an older search query.
 */

import axios from '@nextcloud/axios'
import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import PeopleWidget from '../PeopleWidget.vue'

vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn() } }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => path }))

const wait = (ms = 0) => new Promise((resolve) => setTimeout(resolve, ms))
async function settle() {
	await wait()
	await wait()
}

function queryOf(call) {
	return new URLSearchParams(call[0].split('?')[1])
}

function user (uid, extra = {}) {
  return {
	uid,
	displayName: uid,
	avatarUrl: `/${uid}`,
	customFields: [],
	...extra,
}
}

describe('PeopleWidget directory', () => {
	let wrapper

	beforeEach(() => {
		axios.get.mockReset()
		axios.get.mockResolvedValue({
			data: { users: [user('anna')], total: 1, hasMore: false },
		})
	})

	afterEach(() => {
		wrapper?.unmount()
		wrapper = null
		vi.useRealTimers()
	})

	async function mountWidget(content = {}) {
		wrapper = mount(PeopleWidget, { props: { content } })
		await settle()
		return wrapper
	}

	describe('layout', () => {
		it('falls back to grid with four columns and 64px avatars for an unknown layout', async () => {
			await mountWidget({ layout: 'mosaic' })
			expect(wrapper.vm.layout).toBe('grid')
			expect(wrapper.classes()).toContain('people-widget--grid')
			expect(wrapper.vm.columns).toBe(4)
			expect(wrapper.vm.avatarSize).toBe(64)
			expect(wrapper.vm.gridStyle).toEqual({
				'grid-template-columns': 'repeat(4, minmax(0, 1fr))',
			})
			expect(wrapper.find('img').attributes('width')).toBe('64')
		})

		it('uses three columns and 80px avatars for card, and honours an allowed column count', async () => {
			await mountWidget({ layout: 'card' })
			expect(wrapper.vm.columns).toBe(3)
			expect(wrapper.vm.avatarSize).toBe(80)
			await wrapper.setProps({ content: { layout: 'card', columns: 2 } })
			expect(wrapper.vm.columns).toBe(2)
			expect(wrapper.vm.gridStyle).toEqual({
				'grid-template-columns': 'repeat(2, minmax(0, 1fr))',
			})
		})

		it('ignores a column count outside 2..4', async () => {
			await mountWidget({ layout: 'card', columns: 7 })
			expect(wrapper.vm.columns).toBe(3)
		})

		it('gives the list layout no grid style and 44px avatars', async () => {
			await mountWidget({ layout: 'list' })
			expect(wrapper.vm.gridStyle).toEqual({})
			expect(wrapper.vm.avatarSize).toBe(44)
		})

		it('links each person to their profile with an encoded uid', async () => {
			axios.get.mockResolvedValue({
				data: { users: [user('jan de/wit')], hasMore: false },
			})
			await mountWidget({ layout: 'list' })
			expect(wrapper.find('a').attributes('href')).toBe('/u/jan%20de%2Fwit')
		})
	})

	describe('birthdays', () => {
		beforeEach(() => {
			vi.useFakeTimers({ toFake: ['Date'] })
			vi.setSystemTime(new Date(2026, 5, 10, 15, 30))
		})

		it('counts days to the next birthday, ignoring the year', async () => {
			await mountWidget()
			expect(wrapper.vm.daysToBirthday('1980-06-10')).toBe(0)
			expect(wrapper.vm.daysToBirthday('1990-06-13')).toBe(3)
			expect(wrapper.vm.daysToBirthday('1990-06-09')).toBe(364)
		})

		it('returns null for a missing or malformed birthdate', async () => {
			await mountWidget()
			expect(wrapper.vm.daysToBirthday('')).toBeNull()
			expect(wrapper.vm.daysToBirthday(null)).toBeNull()
			expect(wrapper.vm.daysToBirthday('06-10')).toBeNull()
			expect(wrapper.vm.daysToBirthday('1990-xx-10')).toBeNull()
		})

		it('shows a badge only inside the configured window', async () => {
			await mountWidget({ birthdayWindowDays: 3 })
			expect(wrapper.vm.showBirthdayBadge({ birthdate: '1990-06-13' })).toBe(true)
			expect(wrapper.vm.showBirthdayBadge({ birthdate: '1990-06-14' })).toBe(false)
			expect(wrapper.vm.showBirthdayBadge({})).toBe(false)
			expect(wrapper.vm.showBirthdayBadge({ birthdate: 'nope' })).toBe(false)
		})

		it('falls back to a seven-day window when the setting is out of range', async () => {
			await mountWidget({ birthdayWindowDays: 45 })
			expect(wrapper.vm.birthdayWindowDays).toBe(7)
			expect(wrapper.vm.showBirthdayBadge({ birthdate: '1990-06-17' })).toBe(true)
			expect(wrapper.vm.showBirthdayBadge({ birthdate: '1990-06-18' })).toBe(false)
		})

		it('shows no badge at all when birthdays are switched off', async () => {
			await mountWidget({ showBirthdays: false })
			expect(wrapper.vm.showBirthdayBadge({ birthdate: '1990-06-10' })).toBe(false)
			expect(queryOf(axios.get.mock.calls[0]).get('showBirthdays')).toBe('0')
		})

		it('renders "today" and "in n days" labels', async () => {
			axios.get.mockResolvedValue({
				data: {
					users: [
						user('anna', { birthdate: '1980-06-10' }),
						user('bert', { birthdate: '1980-06-12' }),
					],
					hasMore: false,
				},
			})
			const tSpy = vi.spyOn(globalThis, 't')
			await mountWidget({ layout: 'list' })
			const badges = wrapper.findAll('.people-widget__birthday')
			expect(badges.map((b) => b.text())).toEqual([
				'🎂 today',
				'🎂 in {n} days',
			])
			expect(tSpy).toHaveBeenCalledWith('launchpad', '🎂 in {n} days', { n: 2 })
			expect(tSpy).not.toHaveBeenCalledWith('launchpad', '🎂 in {n} days', { n: 0 })
			tSpy.mockRestore()
		})
	})

	describe('requests', () => {
		it('sends the default parameters with the page size and offset 0', async () => {
			await mountWidget()
			expect(axios.get).toHaveBeenCalledTimes(1)
			const [path] = axios.get.mock.calls[0][0].split('?')
			expect(path).toBe('/apps/launchpad/api/people')
			const params = queryOf(axios.get.mock.calls[0])
			expect(Object.fromEntries(params)).toEqual({
				filters: '[]',
				excludeDisabled: '1',
				showBirthdays: '1',
				sortBy: 'displayName',
				limit: '50',
				offset: '0',
			})
		})

		it('passes configured filters, disabled users and sort order', async () => {
			await mountWidget({
				filters: [{ type: 'group', value: 'staff' }],
				excludeDisabled: false,
				sortBy: 'lastLogin',
			})
			const params = queryOf(axios.get.mock.calls[0])
			expect(params.get('filters')).toBe('[{"type":"group","value":"staff"}]')
			expect(params.get('excludeDisabled')).toBe('0')
			expect(params.get('sortBy')).toBe('lastLogin')
		})

		it('refetches from offset 0 when the filter settings change', async () => {
			axios.get.mockResolvedValueOnce({
				data: { users: [user('anna')], total: 9, hasMore: true },
			})
			await mountWidget()
			axios.get.mockResolvedValueOnce({
				data: { users: [user('bert')], total: 1, hasMore: false },
			})
			await wrapper.setProps({ content: { sortBy: 'email' } })
			await settle()
			expect(axios.get).toHaveBeenCalledTimes(2)
			const params = queryOf(axios.get.mock.calls[1])
			expect(params.get('sortBy')).toBe('email')
			expect(params.get('offset')).toBe('0')
			expect(wrapper.vm.users.map((u) => u.uid)).toEqual(['bert'])
			expect(wrapper.vm.total).toBe(1)
			expect(wrapper.vm.hasMore).toBe(false)
		})
	})

	describe('pagination', () => {
		it('appends the next page at the current length and hides the button at the end', async () => {
			axios.get.mockResolvedValueOnce({
				data: { users: [user('anna'), user('bert')], total: 3, hasMore: true },
			})
			await mountWidget({ layout: 'list' })
			expect(wrapper.find('.people-widget__load-more').exists()).toBe(true)

			axios.get.mockResolvedValueOnce({
				data: { users: [user('cor')], total: 3, hasMore: false },
			})
			await wrapper.find('.people-widget__load-more').trigger('click')
			await settle()

			expect(queryOf(axios.get.mock.calls[1]).get('offset')).toBe('2')
			expect(wrapper.vm.users.map((u) => u.uid)).toEqual(['anna', 'bert', 'cor'])
			expect(wrapper.vm.total).toBe(3)
			expect(wrapper.find('.people-widget__load-more').exists()).toBe(false)
		})

		it('uses the number of users as total when the server sends none', async () => {
			axios.get.mockResolvedValueOnce({
				data: { users: [user('anna'), user('bert')], hasMore: 'yes' },
			})
			await mountWidget()
			expect(wrapper.vm.total).toBe(2)
			expect(wrapper.vm.hasMore).toBe(false)
		})

		it('treats a body without a users array as an empty page', async () => {
			axios.get.mockResolvedValueOnce({ data: { users: 'oops' } })
			await mountWidget()
			expect(wrapper.vm.users).toEqual([])
			expect(wrapper.find('.people-widget__state').text()).toBe('No matching users.')
		})

		it('does nothing on load more when there is nothing more', async () => {
			await mountWidget()
			await wrapper.vm.loadMore()
			expect(axios.get).toHaveBeenCalledTimes(1)
		})

		it('does nothing on load more while a request is in flight', async () => {
			axios.get.mockResolvedValueOnce({
				data: { users: [user('anna')], hasMore: true },
			})
			await mountWidget()
			wrapper.vm.loading = true
			await wrapper.vm.loadMore()
			expect(axios.get).toHaveBeenCalledTimes(1)
		})

		it('pages through server search results with q at the results length', async () => {
			vi.useFakeTimers()
			axios.get.mockImplementation((url) => {
				const params = new URLSearchParams(url.split('?')[1])
				if (!params.get('q')) {
					return Promise.resolve({ data: { users: [user('anna')], hasMore: false } })
				}
				return Promise.resolve({
					data: params.get('offset') === '0'
						? { users: [user('s1'), user('s2')], hasMore: true }
						: { users: [user('s3')], hasMore: false },
				})
			})
			wrapper = mount(PeopleWidget, { props: { content: { layout: 'list' } } })
			await vi.runAllTimersAsync()
			await wrapper.find('input').setValue('sub')
			await vi.advanceTimersByTimeAsync(300)
			expect(wrapper.vm.filteredUsers.map((u) => u.uid)).toEqual(['s1', 's2'])
			expect(wrapper.vm.canLoadMore).toBe(true)

			await wrapper.vm.loadMore()
			const last = queryOf(axios.get.mock.calls.at(-1))
			expect(last.get('q')).toBe('sub')
			expect(last.get('offset')).toBe('2')
			expect(wrapper.vm.filteredUsers.map((u) => u.uid)).toEqual(['s1', 's2', 's3'])
			expect(wrapper.vm.canLoadMore).toBe(false)
		})
	})

	describe('cache', () => {
		it('serves a repeated first page from the cache within the TTL', async () => {
			await mountWidget()
			await wrapper.vm.fetchPage(0)
			expect(axios.get).toHaveBeenCalledTimes(1)
		})

		it('refetches the same page once the TTL has passed', async () => {
			vi.useFakeTimers({ toFake: ['Date'] })
			vi.setSystemTime(new Date(2026, 0, 1, 12, 0, 0))
			await mountWidget()
			vi.setSystemTime(new Date(2026, 0, 1, 12, 1, 1))
			await wrapper.vm.fetchPage(0)
			expect(axios.get).toHaveBeenCalledTimes(2)
		})

		it('clears the cache and refetches on refresh', async () => {
			await mountWidget()
			axios.get.mockResolvedValueOnce({
				data: { users: [user('bert')], total: 1, hasMore: false },
			})
			await wrapper.find('.people-widget__refresh').trigger('click')
			await settle()
			expect(axios.get).toHaveBeenCalledTimes(2)
			expect(queryOf(axios.get.mock.calls[1]).get('offset')).toBe('0')
			expect(wrapper.vm.users.map((u) => u.uid)).toEqual(['bert'])
		})
	})

	describe('errors', () => {
		it('shows the error state, hides load more, and retries on demand', async () => {
			axios.get.mockRejectedValueOnce(new Error('boom'))
			await mountWidget()
			expect(wrapper.vm.error).toBeInstanceOf(Error)
			expect(wrapper.vm.loading).toBe(false)
			expect(wrapper.find('.people-widget__state--error').text()).toContain('Failed to load users')
			expect(wrapper.find('.people-widget__footer').exists()).toBe(false)

			await wrapper.find('.people-widget__retry').trigger('click')
			await settle()
			expect(axios.get).toHaveBeenCalledTimes(2)
			expect(wrapper.vm.error).toBeNull()
			expect(wrapper.vm.users.map((u) => u.uid)).toEqual(['anna'])
		})

		it('records a failed server search as an error', async () => {
			vi.useFakeTimers()
			wrapper = mount(PeopleWidget, { props: { content: {} } })
			await vi.runAllTimersAsync()
			axios.get.mockRejectedValueOnce(new Error('search down'))
			wrapper.vm.search = 'xy'
			await wrapper.vm.$nextTick()
			await vi.advanceTimersByTimeAsync(300)
			expect(wrapper.vm.error.message).toBe('search down')
			expect(wrapper.vm.loading).toBe(false)
		})
	})

	describe('search races', () => {
		it('drops a late answer to an older query', async () => {
			await mountWidget()
			let resolveOld
			axios.get.mockImplementationOnce(() => new Promise((resolve) => { resolveOld = resolve }))
			wrapper.vm.search = 'old'
			await wrapper.vm.$nextTick()
			const oldRequest = wrapper.vm.fetchSearch(0)
			await settle()

			axios.get.mockResolvedValueOnce({ data: { users: [user('fresh')], hasMore: false } })
			wrapper.vm.search = 'new'
			await wrapper.vm.$nextTick()
			await wrapper.vm.fetchSearch(0)

			resolveOld({ data: { users: [user('stale')], hasMore: true } })
			await oldRequest
			expect(wrapper.vm.searchResults.map((u) => u.uid)).toEqual(['fresh'])
			expect(wrapper.vm.searchHasMore).toBe(false)
			expect(wrapper.vm.loading).toBe(false)
		})

		it('does not search for a query shorter than two characters', async () => {
			await mountWidget()
			wrapper.vm.search = ' a '
			await wrapper.vm.fetchSearch(0)
			expect(axios.get).toHaveBeenCalledTimes(1)
		})
	})
})
