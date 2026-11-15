/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `PeopleWidget.vue`, widgets-people-expertise-and-fields:
 * a query of 2 or more characters asks the server (REQ-PEX-003), a shorter
 * one filters the current page (REQ-PPL-011), custom fields and tags render,
 * a matching tag is highlighted, and pressing a tag searches for it.
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

const ANNA = { uid: 'anna', displayName: 'Anna', avatarUrl: '/a', customFields: [] }
const PIETER = {
	uid: 'pieter',
	displayName: 'Pieter de Vries',
	avatarUrl: '/p',
	customFields: [
		{
			key: 'office',
			label: 'Kantoorlocatie',
			type: 'text',
			values: ['Stadhuis, 3e verdieping'],
		},
		{
			key: 'expertise',
			label: 'Expertise',
			type: 'tags',
			values: ['subsidies', 'Omgevingswet'],
		},
	],
}

function queryOf(call) {
	return new URLSearchParams(call[0].split('?')[1])
}

describe('PeopleWidget search and profile fields', () => {
	let wrapper

	beforeEach(() => {
		axios.get.mockReset()
		axios.get.mockImplementation((url) => {
			const q = new URLSearchParams(url.split('?')[1]).get('q')
			if (q) {
				return Promise.resolve({
					data: { users: [PIETER], total: 1, hasMore: false },
				})
			}
			return Promise.resolve({
				data: { users: [ANNA], total: 60, hasMore: true },
			})
		})
	})

	afterEach(() => {
		wrapper?.unmount()
	})

	async function mountWidget(content = { layout: 'card' }) {
		wrapper = mount(PeopleWidget, { props: { content } })
		await settle()
		return wrapper
	}

	it('asks the server for a query of two or more characters, with q and the page size', async () => {
		await mountWidget()
		expect(axios.get).toHaveBeenCalledTimes(1)

		await wrapper.find('input[type="search"]').setValue('subsidie')
		await wait(350)
		await settle()

		expect(axios.get).toHaveBeenCalledTimes(2)
		const params = queryOf(axios.get.mock.calls[1])
		expect(params.get('q')).toBe('subsidie')
		expect(params.get('offset')).toBe('0')
		expect(params.get('limit')).toBe('50')
		expect(wrapper.text()).toContain('Pieter de Vries')
		expect(wrapper.text()).not.toContain('Anna')
	})

	it('keeps a one-character query on the current page without a request', async () => {
		await mountWidget()
		await wrapper.find('input[type="search"]').setValue('a')
		await wait(350)
		await settle()

		expect(axios.get).toHaveBeenCalledTimes(1)
		expect(wrapper.text()).toContain('Anna')
	})

	it('shows text fields and tags, highlights the matching tag, and searches a pressed tag', async () => {
		await mountWidget()
		await wrapper.find('input[type="search"]').setValue('subsidie')
		await wait(350)
		await settle()

		expect(wrapper.text()).toContain('Kantoorlocatie: Stadhuis, 3e verdieping')
		const tags = wrapper.findAll('button.people-widget__tag')
		expect(tags.map((tag) => tag.text())).toEqual(['subsidies', 'Omgevingswet'])
		expect(tags[0].classes()).toContain('people-widget__tag--match')
		expect(tags[1].classes()).not.toContain('people-widget__tag--match')
		expect(wrapper.find('ul.people-widget__tags').attributes('aria-label')).toBe(
			'Expertise',
		)

		await tags[1].trigger('click')
		expect(wrapper.find('input[type="search"]').element.value).toBe(
			'Omgevingswet',
		)
		await wait(350)
		await settle()
		expect(queryOf(axios.get.mock.calls.at(-1)).get('q')).toBe('Omgevingswet')
	})

	it('keeps the tag buttons outside the profile link', async () => {
		await mountWidget()
		await wrapper.find('input[type="search"]').setValue('subsidie')
		await wait(350)
		await settle()

		expect(wrapper.find('a.people-widget__link button').exists()).toBe(false)
		expect(wrapper.find('a.people-widget__link').attributes('href')).toBe(
			'/u/pieter',
		)
	})

	it('drops the server results when the query is cleared', async () => {
		await mountWidget()
		await wrapper.find('input[type="search"]').setValue('subsidie')
		await wait(350)
		await settle()
		await wrapper.find('input[type="search"]').setValue('')
		await settle()

		expect(wrapper.text()).toContain('Anna')
		expect(wrapper.text()).not.toContain('Pieter de Vries')
	})
})
