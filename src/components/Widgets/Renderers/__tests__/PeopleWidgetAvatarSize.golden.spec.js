/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Golden test for PeopleWidget's avatar size: the computed value for every
 * layout, and the rendered markup of the widget in each layout, compared with
 * fixtures/PeopleWidgetAvatarSize.golden.json. Set RECORD_GOLDEN=1 to rewrite it.
 */

import axios from '@nextcloud/axios'
import { mount } from '@vue/test-utils'
import fs from 'node:fs'
import path from 'node:path'
import { describe, expect, it, vi } from 'vitest'
import PeopleWidget from '../PeopleWidget.vue'

vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn() } }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (p) => p }))

const FIXTURE = path.resolve(
	__dirname,
	'fixtures/PeopleWidgetAvatarSize.golden.json',
)

const LAYOUTS = ['card', 'list', 'grid', 'other', '', undefined, null, 'CARD']

const USERS = [
	{ uid: 'anna', displayName: 'Anna', avatarUrl: '/a', customFields: [] },
	{ uid: 'bo', displayName: 'Bo', avatarUrl: '/b', customFields: [] },
]

/**
 * Let the widget's fetch promise settle.
 */
async function settle() {
	await new Promise((resolve) => setTimeout(resolve, 0))
	await new Promise((resolve) => setTimeout(resolve, 0))
}

describe('PeopleWidget avatar size (golden)', () => {
	it('matches the recorded values and markup', async () => {
		axios.get.mockImplementation(() =>
			Promise.resolve({ data: { users: USERS, total: 2, hasMore: false } }),
		)
		const actual = { computed: {}, rendered: {} }
		for (const layout of LAYOUTS) {
			actual.computed[layout === '' ? '(empty string)' : String(layout)] =
				PeopleWidget.computed.avatarSize.call({
					layout,
				})
		}
		for (const layout of ['card', 'list', 'grid', 'other']) {
			const wrapper = mount(PeopleWidget, { props: { content: { layout } } })
			await settle()
			actual.rendered[layout] = wrapper.html()
			wrapper.unmount()
		}
		if (process.env.RECORD_GOLDEN === '1') {
			fs.writeFileSync(FIXTURE, JSON.stringify(actual, null, '\t') + '\n')
		}
		expect(actual).toEqual(JSON.parse(fs.readFileSync(FIXTURE, 'utf8')))
	})
})
