// SPDX-License-Identifier: EUPL-1.2
// SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>

/**
 * The live count of an app tile (icon-only-app-tiles REQ-ICT-002).
 */

import axios from '@nextcloud/axios'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import {
	clearTileCounts,
	COUNT_TTL_MS,
	countLabel,
	registerQuery,
	registerTotal,
	resolveTileCount,
	sourceType,
	widgetItemsCount,
} from '../tileCount.js'

vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn() } }))
vi.mock('@nextcloud/router', () => ({
	generateUrl: (path, params) => path.replace(/\{(\w+)\}/g, (_, key) => params[key]),
	generateOcsUrl: (path) => '/ocs/v2.php' + path,
}))

const CASES = { type: 'register', register: 'dossiq', schema: 'case', filter: { assignee: '@me', isFinalStatus: false } }

beforeEach(() => {
	axios.get.mockReset()
	clearTileCounts()
})

describe('sourceType', () => {
	it('knows register and nc-widget sources, a typeless register source too', () => {
		expect(sourceType(CASES)).toBe('register')
		expect(sourceType({ register: 'r', schema: 's' })).toBe('register')
		expect(sourceType({ type: 'nc-widget', widgetId: 'spreed' })).toBe('nc-widget')
		expect(sourceType({ type: 'nc-widget' })).toBeNull()
		expect(sourceType({ type: 'register', register: 'r' })).toBeNull()
		expect(sourceType({ type: 'sql' })).toBeNull()
		expect(sourceType(null)).toBeNull()
	})
})

describe('Open cases on the Zaaksysteem tile (scenario)', () => {
	it('asks OpenRegister with @me as the viewer and shows the total', async () => {
		axios.get.mockResolvedValue({ data: { total: 14, results: [{}] } })
		expect(await resolveTileCount(CASES, 'pieter')).toBe(14)
		expect(axios.get).toHaveBeenCalledWith('/apps/openregister/api/objects/dossiq/case', {
			params: { _limit: 1, assignee: 'pieter', isFinalStatus: false },
		})
		expect(countLabel(14)).toBe('14')
	})

	it('shows no pill when the register is missing', async () => {
		axios.get.mockRejectedValue({ response: { status: 404 } })
		expect(await resolveTileCount(CASES, 'pieter')).toBeNull()
		expect(countLabel(null)).toBe('')
	})
})

describe('nc-widget sources', () => {
	it('counts the items the dashboard widget returns', async () => {
		axios.get.mockResolvedValue({ data: { ocs: { data: { spreed: [{}, {}, {}] } } } })
		expect(await resolveTileCount({ type: 'nc-widget', widgetId: 'spreed' }, 'pieter')).toBe(3)
		expect(axios.get).toHaveBeenCalledWith('/ocs/v2.php/apps/dashboard/api/v2/widget-items', { params: { 'widgets[]': 'spreed' } })
	})

	it('reads no count for a widget the answer does not hold', () => {
		expect(widgetItemsCount({ ocs: { data: {} } }, 'spreed')).toBeNull()
		expect(widgetItemsCount({ ocs: { data: { spreed: { items: [{}] } } } }, 'spreed')).toBe(1)
	})
})

describe('throttling', () => {
	it('asks each source at most once a minute', async () => {
		axios.get.mockResolvedValue({ data: { total: 2 } })
		await resolveTileCount(CASES, 'pieter', 1000)
		await resolveTileCount(CASES, 'pieter', 1000 + COUNT_TTL_MS - 1)
		expect(axios.get).toHaveBeenCalledTimes(1)
		await resolveTileCount(CASES, 'pieter', 1000 + COUNT_TTL_MS)
		expect(axios.get).toHaveBeenCalledTimes(2)
	})

	it('asks nothing for no source', async () => {
		expect(await resolveTileCount(undefined, 'pieter')).toBeNull()
		expect(axios.get).not.toHaveBeenCalled()
	})
})

describe('labels and parsing', () => {
	it('shows no pill on 0, 99+ above 99', () => {
		expect(countLabel(0)).toBe('')
		expect(countLabel(-1)).toBe('')
		expect(countLabel(99)).toBe('99')
		expect(countLabel(100)).toBe('99+')
		expect(countLabel(3.7)).toBe('3')
	})

	it('reads the total, else the results', () => {
		expect(registerTotal({ total: 0 })).toBe(0)
		expect(registerTotal({ results: [{}, {}] })).toBe(2)
		expect(registerTotal({})).toBeNull()
		expect(registerQuery(undefined, 'x')).toEqual({ _limit: 1 })
	})
})
