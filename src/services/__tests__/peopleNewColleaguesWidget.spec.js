/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * People widget "New colleagues" (REQ-PPL-014): LaunchPad's people source
 * (`buildWidgetDataProvide().cnPeopleSource`) drives nextcloud-vue's
 * CnPeopleWidget in `selectionMode: 'new'`. The widget lists the colleagues
 * `GET /api/people/new` returns, with "Starts {weekday}" for a future start,
 * and says so when no profile field is marked as the start date.
 *
 * @spec openspec/changes/archive/2026-11-25-people-new-colleagues/specs/people-widget/spec.md#requirement-the-people-widget-can-list-new-colleagues-req-ppl-014
 */

import { flushPromises, mount } from '@vue/test-utils'
import fs from 'fs'
import path from 'path'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => ({
	translate: (_app, text, vars = {}) =>
		text.replace(/\{(\w+)\}/g, (_m, k) => vars[k] ?? `{${k}}`),
	translatePlural: (_app, one, many, count) =>
		(count === 1 ? one : many).replace('%n', String(count)),
	getCanonicalLocale: () => 'en-GB',
	getLanguage: () => 'en',
	getLocale: () => 'en_GB',
}))
vi.mock('@nextcloud/axios', () => ({
	default: { get: vi.fn() },
}))
vi.mock('@nextcloud/router', () => ({
	generateUrl: (path) => `/index.php${path}`,
	generateOcsUrl: (path) => `/ocs/v2.php${path}`,
	imagePath: (app, file) => `/${app}/img/${file}`,
	linkTo: (app, file) => `/${app}/${file}`,
}))

import axios from '@nextcloud/axios'
import CnPeopleWidget from '@conduction/nextcloud-vue/src/components/CnPeopleWidget/CnPeopleWidget.vue'
import { buildWidgetDataProvide } from '../widgetDataAdapters.js'

/**
 * Mount the people widget on a New colleagues placement with LaunchPad's source.
 *
 * @param {object} content Extra placement content.
 * @return {Promise<object>} The wrapper after the first fetch.
 */
async function mountNewColleagues(content = {}) {
	const wrapper = mount(CnPeopleWidget, {
		props: { content: { selectionMode: 'new', layout: 'list', newSinceDays: 30, newUntilDays: 14, ...content } },
		global: { provide: buildWidgetDataProvide(() => 7) },
	})
	await flushPromises()
	return wrapper
}

beforeEach(() => {
	axios.get.mockReset()
})

describe('People widget: New colleagues through the LaunchPad source', () => {
	it('lists the colleagues the endpoint returns, asking with the placement window', async () => {
		axios.get.mockResolvedValue({
			data: {
				configured: true,
				users: [
					{ id: 'yasmina', displayName: 'Yasmina El Amrani', role: 'Beleidsadviseur', startDate: '2026-12-07', starts: true },
					{ id: 'thomas', displayName: 'Thomas de Wit', role: 'Jurist', startDate: '2026-11-18', starts: false },
				],
			},
		})
		const wrapper = await mountNewColleagues()

		expect(axios.get).toHaveBeenCalledWith(
			'/index.php/apps/launchpad/api/people/new',
			{ params: { since: 30, until: 14 } },
		)
		const names = wrapper.findAll('.cn-people-widget__name').map((n) => n.text())
		expect(names).toEqual(['Yasmina El Amrani', 'Thomas de Wit'])
		const starts = wrapper.findAll('[data-testid="cn-people-starts"]')
		expect(starts).toHaveLength(1)
		expect(starts[0].text()).toBe('Starts Monday')
	})

	it('says so when no profile field is marked as the start date', async () => {
		axios.get.mockResolvedValue({ data: { configured: false, users: [] } })
		const wrapper = await mountNewColleagues()

		expect(wrapper.findAll('.cn-people-widget__item')).toHaveLength(0)
		expect(wrapper.find('.cn-people-widget__state').exists()).toBe(true)
	})

	it('shows the error state when the read fails', async () => {
		axios.get.mockRejectedValue(new Error('down'))
		const wrapper = await mountNewColleagues()

		expect(wrapper.find('.cn-people-widget__retry').exists()).toBe(true)
	})
})

describe('People widget: New colleagues in every LaunchPad locale', () => {
	const KEYS = [
		'The administrator has not set a start date field.',
		'Starts {weekday}',
		'Selection',
		'People by filter',
		'New colleagues',
		'Started in the last (days, 1–90)',
		'Starts within (days, 0–30)',
		'Must be between {min} and {max}',
	]
	const root = path.resolve(__dirname, '../../..')
	const ncVue = path.join(root, 'node_modules/@conduction/nextcloud-vue/l10n')
	const locales = fs.readdirSync(path.join(root, 'l10n'))
		.filter((file) => /^[a-z]{2}(_[A-Z]{2})?\.json$/.test(file) && file !== 'en.json')
		.map((file) => file.slice(0, -5))

	it.each(locales)('nextcloud-vue translates every New colleagues string in %s', (locale) => {
		const catalogue = JSON.parse(fs.readFileSync(path.join(ncVue, `${locale}.json`), 'utf8')).translations
		expect(KEYS.filter((key) => typeof catalogue[key] !== 'string' || catalogue[key] === '')).toEqual([])
	})
})
