/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Filter telemetry (drill-down-cross-widget-filter REQ-CWF-010): rows carry
 * dimensions and operators, never values, and are written only when the
 * organisation opted in.
 *
 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-15
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createFilterTelemetry, filterDelta } from '../filterTelemetry.js'
import { useFilterBus } from '../useFilterBus.js'

const NOW = () => new Date('2026-11-15T12:00:00.000Z')

/**
 * A recorder with a recording post double.
 *
 * @param {boolean} enabled Whether telemetry is on.
 * @return {{telemetry: object, post: Function}}
 */
function recorder(enabled = true) {
	const post = vi.fn(() => Promise.resolve({}))
	const telemetry = createFilterTelemetry({ enabled: () => enabled, dashboardId: 'd-1', userId: 'alice', post, now: NOW })
	return { telemetry, post }
}

describe('filterDelta', () => {
	it('keeps dimension, operator, source, source widget and pinned, and drops the value', () => {
		expect(filterDelta([
			{ dimension: 'gemeenteCode', operator: 'eq', value: 'GM0355', source: 'widgetClick', sourceWidgetId: 'w-1', pinned: false },
			{ dimension: 'period.month', value: '2026-11', source: 'urlBootstrap', sourceWidgetId: null, pinned: true },
			{ dimension: '', value: 'x' },
			null,
		])).toEqual([
			{ dimension: 'gemeenteCode', operator: 'eq', source: 'widgetClick', sourceWidgetId: 'w-1', pinned: false },
			{ dimension: 'period.month', operator: 'eq', source: 'urlBootstrap', pinned: true },
		])
	})
})

describe('createFilterTelemetry', () => {
	it('writes a row without values when telemetry is on', async () => {
		const { telemetry, post } = recorder()

		const row = await telemetry.record('apply', [{ dimension: 'wijkCode', operator: 'eq', value: 'WK1', source: 'widgetClick', sourceWidgetId: 'w-2' }], 'w-2')

		const expected = {
			dashboardId: 'd-1',
			userId: 'alice',
			eventType: 'apply',
			filterDelta: [{ dimension: 'wijkCode', operator: 'eq', source: 'widgetClick', sourceWidgetId: 'w-2', pinned: false }],
			timestamp: '2026-11-15T12:00:00.000Z',
			widgetId: 'w-2',
		}
		expect(row).toEqual(expected)
		expect(post).toHaveBeenCalledWith(expected)
		expect(JSON.stringify(post.mock.calls)).not.toContain('WK1')
	})

	it('writes nothing when telemetry is off, the context is incomplete, the type is unknown or nothing changed', async () => {
		const clause = [{ dimension: 'a', operator: 'eq', value: 1 }]
		const off = recorder(false)
		expect(await off.telemetry.record('apply', clause)).toBeNull()
		expect(off.post).not.toHaveBeenCalled()

		const post = vi.fn()
		const anonymous = createFilterTelemetry({ enabled: () => true, dashboardId: 'd-1', userId: '', post })
		expect(await anonymous.record('apply', clause)).toBeNull()
		const noDashboard = createFilterTelemetry({ enabled: () => true, dashboardId: '', userId: 'alice', post })
		expect(await noDashboard.record('apply', clause)).toBeNull()

		const on = recorder()
		expect(await on.telemetry.record('click', clause)).toBeNull()
		expect(await on.telemetry.record('reset', [])).toBeNull()
		expect(post).not.toHaveBeenCalled()
		expect(on.post).not.toHaveBeenCalled()
	})

	it('asks whether telemetry is on at every record, so switching it on only affects later changes', async () => {
		let enabled = false
		const post = vi.fn(() => Promise.resolve())
		const telemetry = createFilterTelemetry({ enabled: () => enabled, dashboardId: 'd-1', userId: 'alice', post, now: NOW })

		await telemetry.record('apply', [{ dimension: 'a' }])
		enabled = true
		await telemetry.record('apply', [{ dimension: 'b' }])

		expect(post).toHaveBeenCalledTimes(1)
		expect(post.mock.calls[0][0].filterDelta[0].dimension).toBe('b')
	})

	it('drops a failed write instead of failing the filter change', async () => {
		const post = vi.fn(() => Promise.reject(new Error('offline')))
		const telemetry = createFilterTelemetry({ enabled: () => true, dashboardId: 'd-1', userId: 'alice', post, now: NOW })

		await expect(telemetry.record('apply', [{ dimension: 'a' }])).resolves.toBeNull()
	})
})

describe('the filter bus with telemetry', () => {
	beforeEach(() => {
		vi.useFakeTimers()
	})

	afterEach(() => {
		vi.useRealTimers()
	})

	it('records an apply for an accepted click, a reset of the cleared clauses, and nothing for an empty reset', () => {
		const record = vi.fn()
		const bus = useFilterBus({ telemetry: { record } })

		bus.publish({ dimension: 'gemeenteCode', operator: 'eq', value: 'GM0355', source: 'widgetClick', sourceWidgetId: 'w-1' })
		expect(record).toHaveBeenLastCalledWith('apply', [expect.objectContaining({ dimension: 'gemeenteCode' })], 'w-1')

		bus.hydrate([{ dimension: 'year', operator: 'eq', value: 2026, pinned: true }], 'savedView')
		bus.publish({ dimension: 'status', operator: 'eq', value: 'open', source: 'userExplicit' })
		record.mockClear()

		expect(bus.reset()).toBe(true)
		expect(record).toHaveBeenCalledTimes(1)
		const [type, cleared] = record.mock.calls[0]
		expect(type).toBe('reset')
		expect(cleared.map((clause) => clause.dimension)).toEqual(['status'])

		record.mockClear()
		expect(bus.reset()).toBe(false)
		expect(record).not.toHaveBeenCalled()
	})

	it('records nothing for a refused click', () => {
		const record = vi.fn()
		const bus = useFilterBus({ canAccess: () => false, telemetry: { record } })

		expect(bus.publish({ dimension: 'a', operator: 'eq', value: 1 }).accepted).toBe(false)
		expect(record).not.toHaveBeenCalled()
	})

	it('records a bookmark on the next tick for clauses restored from the URL only', () => {
		const record = vi.fn()
		const bus = useFilterBus({ telemetry: { record } })

		bus.hydrate([{ dimension: 'gemeenteCode', operator: 'eq', value: 'GM0355' }], 'urlBootstrap')
		expect(record).not.toHaveBeenCalled()
		vi.runAllTimers()
		expect(record).toHaveBeenCalledTimes(1)
		expect(record.mock.calls[0][0]).toBe('bookmark')
		expect(record.mock.calls[0][1].map((clause) => clause.dimension)).toEqual(['gemeenteCode'])

		record.mockClear()
		bus.hydrate([{ dimension: 'wijkCode', operator: 'eq', value: 'WK1' }], 'savedView')
		bus.hydrate([], 'urlBootstrap')
		vi.runAllTimers()
		expect(record).not.toHaveBeenCalled()
	})
})
