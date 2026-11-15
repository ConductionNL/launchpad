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
	fetchPeoplePage,
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
		expect(provided.cnPeopleSource.fetchPeople).toBe(fetchPeoplePage)
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

describe('buildRendererExtraProps', () => {
	it('gives the files widget its api base and others nothing', () => {
		expect(buildRendererExtraProps('files')).toEqual({ apiBase: '/apps/launchpad' })
		expect(buildRendererExtraProps('news')).toEqual({})
		expect(buildRendererExtraProps(undefined)).toEqual({})
	})
})
