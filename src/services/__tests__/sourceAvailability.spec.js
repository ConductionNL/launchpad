/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * sourceAvailability (admin-templates REQ-TMPL-022): which placements may
 * hide when their register/schema is absent, and the once-per-page probe
 * that tells "not here" (404) apart from "could not tell" (any other failure).
 */

import axios from '@nextcloud/axios'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import {
	hidesWhenUnavailable,
	isSourceAvailable,
	resetSourceAvailability,
	sourceKey,
} from '../sourceAvailability.js'

vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn() } }))
vi.mock('@nextcloud/router', () => ({
	generateUrl: (path, params = {}) =>
		'/index.php' + path.replace(/\{(\w+)\}/g, (_m, k) => String(params[k])),
}))

beforeEach(() => {
	axios.get.mockReset()
	resetSourceAvailability()
})

describe('sourceKey', () => {
	it('reads an object-list source at the top of its content', () => {
		expect(sourceKey({ register: 'dossiq', schema: 'case' })).toBe('dossiq/case')
	})

	it('reads a stat widget source under `source`', () => {
		expect(sourceKey({ source: { register: 'pipelinq', schema: 'lead' } })).toBe('pipelinq/lead')
	})

	it('prefers a top-level register over `source`', () => {
		expect(sourceKey({ register: 'a', schema: 'b', source: { register: 'c', schema: 'd' } })).toBe('a/b')
	})

	it('is empty when either half or the content is missing', () => {
		expect(sourceKey({ register: 'a' })).toBe('')
		expect(sourceKey({ source: { schema: 'b' } })).toBe('')
		expect(sourceKey(null)).toBe('')
		expect(sourceKey(undefined)).toBe('')
	})
})

describe('hidesWhenUnavailable', () => {
	const content = { register: 'r', schema: 's', hideWhenUnavailable: true }

	it('is true for an object-list or stat that asks to hide and names a source', () => {
		expect(hidesWhenUnavailable({ widgetId: 'object-list', content })).toBe(true)
		expect(hidesWhenUnavailable({ widgetId: 'stat', content: { source: { register: 'r', schema: 's' }, hideWhenUnavailable: true } })).toBe(true)
	})

	it('is false for another widget type', () => {
		expect(hidesWhenUnavailable({ widgetId: 'news', content })).toBe(false)
	})

	it('is false unless the flag is exactly true', () => {
		expect(hidesWhenUnavailable({ widgetId: 'object-list', content: { ...content, hideWhenUnavailable: 'yes' } })).toBe(false)
		expect(hidesWhenUnavailable({ widgetId: 'object-list', content: { register: 'r', schema: 's' } })).toBe(false)
	})

	it('is false without content, a source, or a placement', () => {
		expect(hidesWhenUnavailable({ widgetId: 'object-list' })).toBe(false)
		expect(hidesWhenUnavailable({ widgetId: 'object-list', content: { hideWhenUnavailable: true } })).toBe(false)
		expect(hidesWhenUnavailable(null)).toBe(false)
	})
})

describe('isSourceAvailable', () => {
	it('asks OpenRegister for one object and answers true', async () => {
		axios.get.mockResolvedValue({ data: {} })

		await expect(isSourceAvailable('dossiq', 'case')).resolves.toBe(true)
		expect(axios.get).toHaveBeenCalledWith(
			'/index.php/apps/openregister/api/objects/dossiq/case',
			{ params: { _limit: 1 } },
		)
	})

	it('answers false only for a 404', async () => {
		axios.get.mockRejectedValue({ response: { status: 404 } })

		await expect(isSourceAvailable('gone', 'x')).resolves.toBe(false)
	})

	it.each([
		['a 403', { response: { status: 403 } }],
		['a 500', { response: { status: 500 } }],
		['no response', new Error('Network Error')],
		['nothing', undefined],
	])('answers null for %s', async (_label, error) => {
		axios.get.mockRejectedValue(error)

		await expect(isSourceAvailable('r', 's')).resolves.toBeNull()
	})

	it('asks once per source and shares the answer until reset', async () => {
		axios.get.mockResolvedValue({ data: {} })

		await Promise.all([isSourceAvailable('r', 's'), isSourceAvailable('r', 's')])
		await isSourceAvailable('r', 'other')
		expect(axios.get).toHaveBeenCalledTimes(2)

		resetSourceAvailability()
		await isSourceAvailable('r', 's')
		expect(axios.get).toHaveBeenCalledTimes(3)
	})
})
