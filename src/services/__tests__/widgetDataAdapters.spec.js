/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * widgetDataAdapters: the calendar adapter's request and normalised result,
 * the provide() map the data widgets inject, and the renderer extra props.
 * fetchPeoplePage has its own golden spec.
 */

import axios from '@nextcloud/axios'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import {
	fetchFinanceSummary,
	fetchSpendNarrative,
	fetchVendorCommitments,
	resolveDeepLink,
} from '../spendAnalytics.js'
import {
	buildRendererExtraProps,
	buildWidgetDataProvide,
	fetchCalendarEvents,
} from '../widgetDataAdapters.js'

vi.mock('@nextcloud/axios', () => ({
	default: { get: vi.fn() },
}))
vi.mock('@nextcloud/router', () => ({
	generateUrl: (path, params = {}) =>
		'/index.php' + path.replace(/\{(\w+)\}/g, (_m, k) => String(params[k])),
}))

beforeEach(() => {
	axios.get.mockReset()
})

describe('fetchCalendarEvents', () => {
	it('requests the placement events for the window and returns them', async () => {
		const events = [{ id: 'e1' }]
		const failures = [{ calendar: 'c2' }]
		axios.get.mockResolvedValue({ data: { events, failures } })

		const result = await fetchCalendarEvents(17, { from: '2026-01-01', to: '2026-01-31' })

		expect(axios.get).toHaveBeenCalledWith(
			'/index.php/apps/launchpad/api/widgets/calendar/17/events',
			{ params: { from: '2026-01-01', to: '2026-01-31' } },
		)
		expect(result).toEqual({ events, failures })
	})

	it('sends an undefined window when called without arguments', async () => {
		axios.get.mockResolvedValue({ data: {} })
		await fetchCalendarEvents('p1')
		expect(axios.get).toHaveBeenCalledWith(
			'/index.php/apps/launchpad/api/widgets/calendar/p1/events',
			{ params: { from: undefined, to: undefined } },
		)
	})

	it('replaces non-array events and failures with empty lists', async () => {
		axios.get.mockResolvedValueOnce({ data: { events: 'x', failures: { a: 1 } } })
		await expect(fetchCalendarEvents(1)).resolves.toEqual({ events: [], failures: [] })

		axios.get.mockResolvedValueOnce(undefined)
		await expect(fetchCalendarEvents(1)).resolves.toEqual({ events: [], failures: [] })
	})

	it('rejects when the request fails', async () => {
		axios.get.mockRejectedValue(new Error('503'))
		await expect(fetchCalendarEvents(1)).rejects.toThrow('503')
	})
})

describe('buildWidgetDataProvide', () => {
	it('wires the people and spend adapters to their functions', () => {
		const provided = buildWidgetDataProvide(() => 1)

		expect(Object.keys(provided)).toEqual([
			'cnPeopleSource',
			'cnCalendarSource',
			'cnSpendAnalyticsSource',
		])
		expect(typeof provided.cnPeopleSource.fetchPeople).toBe('function')
		expect(provided.cnSpendAnalyticsSource).toEqual({
			fetchSummary: fetchFinanceSummary,
			fetchVendorCommitments,
			fetchNarrative: fetchSpendNarrative,
			resolveDeepLink,
		})
	})

	it('reads the placement id at call time for calendar events', async () => {
		let placementId = 'first'
		const provided = buildWidgetDataProvide(() => placementId)
		axios.get.mockResolvedValue({ data: { events: [], failures: [] } })

		placementId = 'second'
		await provided.cnCalendarSource.fetchEvents({ from: 'a', to: 'b' })

		expect(axios.get).toHaveBeenCalledWith(
			'/index.php/apps/launchpad/api/widgets/calendar/second/events',
			{ params: { from: 'a', to: 'b' } },
		)
	})
})

describe('buildWidgetDataProvide people source', () => {
	it('asks for no birthdates when the placement switches them off', async () => {
		const provided = buildWidgetDataProvide(() => 1, () => ({ showBirthdays: false }))
		axios.get.mockResolvedValue({ data: { users: [], total: 0, hasMore: false } })

		await provided.cnPeopleSource.fetchPeople({ offset: 0, limit: 50 })

		expect(axios.get.mock.calls[0][0]).toContain('showBirthdays=0')
	})

	it('asks for birthdates when the placement leaves the switch unset', async () => {
		const provided = buildWidgetDataProvide(() => 1)
		axios.get.mockResolvedValue({ data: { users: [], total: 0, hasMore: false } })

		await provided.cnPeopleSource.fetchPeople({ offset: 0, limit: 50 })

		expect(axios.get.mock.calls[0][0]).toContain('showBirthdays=1')
	})

	it('searches the whole directory with q, keeping the widget filters', async () => {
		const provided = buildWidgetDataProvide(() => 1)
		axios.get.mockResolvedValue({ data: { users: [{ uid: 'pieter' }], total: 1, hasMore: false } })
		const filters = [{ fieldName: 'group', operator: 'in', values: ['gw'] }]

		const page = await provided.cnPeopleSource.searchPeople({ q: 'subsidie', filters, offset: 0, limit: 50 })

		const query = new URLSearchParams(axios.get.mock.calls[0][0].split('?')[1])
		expect(query.get('q')).toBe('subsidie')
		expect(JSON.parse(query.get('filters'))).toEqual(filters)
		expect(page.users).toEqual([{ uid: 'pieter' }])
	})

	it('sends no q on a plain page request', async () => {
		const provided = buildWidgetDataProvide(() => 1)
		axios.get.mockResolvedValue({ data: { users: [], total: 0, hasMore: false } })

		await provided.cnPeopleSource.fetchPeople({ offset: 0, limit: 50 })

		expect(axios.get.mock.calls[0][0]).not.toContain('q=')
	})
})

describe('buildRendererExtraProps', () => {
	it('gives the files widget its api base and others nothing', () => {
		expect(buildRendererExtraProps('files')).toEqual({ apiBase: '/apps/launchpad' })
		expect(buildRendererExtraProps('news')).toEqual({})
		expect(buildRendererExtraProps(undefined)).toEqual({})
	})
})

// @spec openspec/changes/archive/2026-11-25-people-new-colleagues/specs/people-widget/spec.md#requirement-the-people-widget-can-list-new-colleagues-req-ppl-014
describe('the new colleagues source', () => {
	it('reads GET /api/people/new with the windows', async () => {
		const { fetchNewPeople } = await import('../widgetDataAdapters.js')
		axios.get.mockResolvedValue({ data: { configured: true, users: [{ id: 'yasmina' }] } })
		const answer = await fetchNewPeople({ since: 30, until: 14 })
		expect(axios.get).toHaveBeenCalledWith('/index.php/apps/launchpad/api/people/new', { params: { since: 30, until: 14 } })
		expect(answer).toEqual({ configured: true, users: [{ id: 'yasmina' }] })
	})

	it('is offered on every placement and to the forms outside one', async () => {
		const { buildPeopleSource } = await import('../widgetDataAdapters.js')
		const provided = buildWidgetDataProvide(() => 1)
		expect(typeof provided.cnPeopleSource.fetchNewPeople).toBe('function')
		expect(typeof provided.cnPeopleSource.hasStartDateField).toBe('function')
		const source = buildPeopleSource()
		expect(typeof source.fetchPeople).toBe('function')
		expect(typeof source.hasStartDateField).toBe('function')
	})

	it('answers whether a start date field exists, asking once and again after a failure', async () => {
		vi.resetModules()
		const { hasStartDateField } = await import('../widgetDataAdapters.js')
		axios.get.mockRejectedValueOnce(new Error('down'))
		expect(await hasStartDateField()).toBe(false)
		axios.get.mockResolvedValue({ data: { configured: true, users: [] } })
		expect(await hasStartDateField()).toBe(true)
		expect(await hasStartDateField()).toBe(true)
		expect(axios.get).toHaveBeenCalledTimes(2)
	})
})
