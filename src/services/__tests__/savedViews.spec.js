/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * savedViews: the rows and requests behind a dashboard's saved views
 * (drill-down-cross-widget-filter tasks 11-13; REQ-CWF-009).
 */

import { describe, expect, it, vi } from 'vitest'
import {
	canManageView,
	createSavedView,
	deleteSavedView,
	groupViews,
	listSavedViews,
	normaliseView,
	updateSavedView,
	viewFilters,
} from '../savedViews.js'

vi.mock('@nextcloud/router', () => ({
	generateUrl: (path, params = {}) => '/index.php' + path.replace(/{(\w+)}/g, (_m, key) => params[key]),
}))

vi.mock('@nextcloud/axios', () => ({ default: {} }))

const DASHBOARD = 'd1a2b3c4-5678-90ab-cdef-1234567890ab'
const COLLECTION = '/index.php/apps/openregister/api/objects/launchpad/saved_view'

describe('normaliseView', () => {
	it('lifts the id from @self and fills the optional fields', () => {
		expect(normaliseView({ '@self': { id: 'v1' }, dashboardId: DASHBOARD, name: 'Q2' })).toEqual({
			id: 'v1', dashboardId: DASHBOARD, name: 'Q2', description: '', filters: [], isShared: false, createdBy: '', createdAt: '',
		})
	})

	it('drops a row without id or name', () => {
		expect(normaliseView({ name: 'no id' })).toBeNull()
		expect(normaliseView({ id: 'v1', name: '' })).toBeNull()
		expect(normaliseView(null)).toBeNull()
	})
})

describe('viewFilters', () => {
	it('keeps the known clause fields and drops empty ones', () => {
		expect(viewFilters([
			{ dimension: 'gemeenteCode', operator: 'eq', value: 'GM0355', source: 'widgetClick', sourceWidgetId: null, pinned: false, extra: 1 },
			{ dimension: '', operator: 'eq' },
			{ dimension: 'period.month', operator: 'between', value: ['2026-01', '2026-03'], pinned: true },
		])).toEqual([
			{ dimension: 'gemeenteCode', operator: 'eq', value: 'GM0355', source: 'widgetClick', pinned: false },
			{ dimension: 'period.month', operator: 'between', value: ['2026-01', '2026-03'], pinned: true },
		])
	})
})

describe('groupViews and canManageView', () => {
	const mine = { id: '1', name: 'B mine', createdBy: 'eva', isShared: true }
	const mine2 = { id: '2', name: 'A mine', createdBy: 'eva', isShared: false }
	const theirsShared = { id: '3', name: 'Theirs', createdBy: 'bob', isShared: true }
	const theirsPrivate = { id: '4', name: 'Private', createdBy: 'bob', isShared: false }

	it('splits the user\'s own views from the ones others shared, by name', () => {
		expect(groupViews([mine, theirsShared, theirsPrivate, mine2], 'eva')).toEqual({
			mine: [mine2, mine],
			shared: [theirsShared],
		})
	})

	it('only the creator manages a view', () => {
		expect(canManageView(mine, 'eva')).toBe(true)
		expect(canManageView(theirsShared, 'eva')).toBe(false)
		expect(canManageView(mine, '')).toBe(false)
	})
})

describe('requests', () => {
	it('lists the dashboard\'s views from the register', async () => {
		const http = {
			get: vi.fn().mockResolvedValue({
				data: { results: [
					{ id: 'v1', dashboardId: DASHBOARD, name: 'Q2' },
					{ id: 'v2', dashboardId: 'another', name: 'Elsewhere' },
					{ id: 'v3', dashboardId: DASHBOARD },
				] },
			}),
		}

		const views = await listSavedViews(DASHBOARD, http)

		expect(http.get).toHaveBeenCalledWith(COLLECTION, { params: { dashboardId: DASHBOARD, _limit: 200 } })
		expect(views.map((view) => view.id)).toEqual(['v1'])
	})

	it('saves the current clauses with creator and time', async () => {
		const http = { post: vi.fn().mockResolvedValue({ data: { id: 'new' } }) }
		const now = () => new Date('2026-11-16T10:00:00Z')

		const view = await createSavedView({
			dashboardId: DASHBOARD,
			name: '  Q2 Trend ',
			description: ' Open cases ',
			isShared: true,
			clauses: [{ dimension: 'gemeenteCode', operator: 'eq', value: 'GM0355', source: 'widgetClick', pinned: false }],
			createdBy: 'eva',
		}, http, now)

		expect(http.post).toHaveBeenCalledWith(COLLECTION, {
			dashboardId: DASHBOARD,
			name: 'Q2 Trend',
			description: 'Open cases',
			filters: [{ dimension: 'gemeenteCode', operator: 'eq', value: 'GM0355', source: 'widgetClick', pinned: false }],
			isShared: true,
			createdBy: 'eva',
			createdAt: '2026-11-16T10:00:00.000Z',
		})
		expect(view.id).toBe('new')
		expect(view.name).toBe('Q2 Trend')
	})

	it('patches only the given fields and deletes by id', async () => {
		const http = { patch: vi.fn().mockResolvedValue({}), delete: vi.fn().mockResolvedValue({}) }

		await updateSavedView('v1', { isShared: false }, http)
		await updateSavedView('v1', { name: ' New ', description: '' }, http)
		await deleteSavedView('v1', http)

		expect(http.patch).toHaveBeenNthCalledWith(1, COLLECTION + '/v1', { isShared: false })
		expect(http.patch).toHaveBeenNthCalledWith(2, COLLECTION + '/v1', { name: 'New', description: '' })
		expect(http.delete).toHaveBeenCalledWith(COLLECTION + '/v1')
	})
})
