/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * AnnouncementsWidget (openspec/specs/announcements REQ-ANN-001..003): the
 * list the server returns, its like and comment counts, the category filter,
 * liking and following. Targeting itself is the server's (PHPUnit).
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('../../../../utils/logger.js', () => ({ logger: { error: vi.fn() } }))

vi.mock('@nextcloud/axios', () => ({
	default: { get: vi.fn(), post: vi.fn(), put: vi.fn() },
}))
vi.mock('@nextcloud/router', async (importOriginal) => ({
	...(await importOriginal()),
	generateUrl: (path) => path,
}))

import axios from '@nextcloud/axios'
import AnnouncementsWidget from '../AnnouncementsWidget.vue'

function news(uuid, title, category, overrides = {}) {
	return {
		uuid,
		title,
		category,
		kind: 'news',
		body: '',
		publishAt: '2026-10-05T08:00:00Z',
		allowComments: true,
		likeCount: 0,
		commentCount: 0,
		likedByMe: false,
		...overrides,
	}
}

async function mountWith(data, content = {}) {
	axios.get.mockResolvedValue({ data })
	const wrapper = mount(AnnouncementsWidget, { props: { content } })
	await flushPromises()
	return wrapper
}

beforeEach(() => {
	axios.get.mockReset()
	axios.put.mockReset()
	axios.post.mockReset()
})

describe('AnnouncementsWidget', () => {
	it('asks for news only and shows each item with its like and comment counts', async () => {
		const wrapper = await mountWith({
			announcements: [
				news('a', 'Nieuwe werkplekken op de 3e verdieping', 'Facilitair', {
					likeCount: 1,
					commentCount: 1,
				}),
				news('b', 'Inloopspreekuur privacy', 'Privacy'),
			],
			following: [],
			canAuthor: false,
		})

		expect(axios.get).toHaveBeenCalledWith('/apps/launchpad/api/announcements', {
			params: { kind: 'news' },
		})
		const cards = wrapper.findAll('.announcement-card')
		expect(cards).toHaveLength(2)
		expect(cards[0].text()).toContain('Nieuwe werkplekken op de 3e verdieping')
		const buttons = cards[0].findAll('.announcement-card__actions button')
		expect(buttons[0].text()).toBe('1')
		expect(buttons[1].text()).toBe('1')
		expect(wrapper.text()).not.toContain('New announcement')
	})

	it('says so when nothing targets the reader', async () => {
		const wrapper = await mountWith({
			announcements: [],
			following: [],
			canAuthor: false,
		})
		expect(wrapper.text()).toContain('No announcements for you yet.')
	})

	it('offers "New announcement" to authors only', async () => {
		const wrapper = await mountWith({
			announcements: [],
			following: [],
			canAuthor: true,
		})
		expect(wrapper.text()).toContain('New announcement')
	})

	it('filters on a category', async () => {
		const wrapper = await mountWith({
			announcements: [
				news('a', 'Werkplekken', 'Facilitair'),
				news('b', 'Spreekuur', 'Privacy'),
			],
			following: [],
			canAuthor: false,
		})
		wrapper.vm.category = 'Privacy'
		await flushPromises()
		const titles = wrapper
			.findAll('.announcement-card__title')
			.map((node) => node.text())
		expect(titles).toEqual(['Spreekuur'])
	})

	it('likes an item and shows the count the server returns', async () => {
		const wrapper = await mountWith({
			announcements: [news('a', 'Werkplekken', 'Facilitair')],
			following: [],
			canAuthor: false,
		})
		axios.put.mockResolvedValue({
			data: news('a', 'Werkplekken', 'Facilitair', {
				likeCount: 1,
				likedByMe: true,
			}),
		})

		await wrapper.find('.announcement-card__actions button').trigger('click')
		await flushPromises()

		expect(axios.put).toHaveBeenCalledWith(
			'/apps/launchpad/api/announcements/a/like',
			{ liked: true },
		)
		expect(wrapper.find('.announcement-card__actions button').text()).toBe('1')
	})

	it('follows and unfollows a category', async () => {
		const wrapper = await mountWith({
			announcements: [news('b', 'Spreekuur', 'Privacy')],
			following: [],
			canAuthor: false,
		})
		axios.put.mockResolvedValue({ data: { following: ['Privacy'] } })

		const follow = wrapper.findAll('.announcement-card__actions button')[2]
		expect(follow.text()).toBe('Follow Privacy')
		await follow.trigger('click')
		await flushPromises()

		expect(axios.put).toHaveBeenCalledWith(
			'/apps/launchpad/api/announcement-follows',
			{ category: 'Privacy', follow: true },
		)
		expect(wrapper.findAll('.announcement-card__actions button')[2].text()).toBe(
			'Stop following Privacy',
		)
	})
})
