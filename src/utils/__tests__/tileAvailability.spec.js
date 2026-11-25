/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The line under the tile form's Check availability switch (REQ-TILE-042).
 *
 * @spec openspec/changes/tile-form-board-sync/specs/tiles/spec.md#requirement-the-last-availability-check-in-the-form-req-tile-042
 */

import { describe, expect, it } from 'vitest'
import { lastCheckLine } from '../tileAvailability.js'

const t = (_app, key, vars) => key.replace(/\{(\w+)\}/g, (match, name) => (vars && name in vars ? String(vars[name]) : match))
const now = new Date(2026, 10, 25, 14, 5)

describe('lastCheckLine', () => {
	it('reads a reachable tile checked today with the clock time', () => {
		const at = new Date(2026, 10, 25, 10, 40)
		expect(lastCheckLine({ state: 'online', checkedAt: at.toISOString() }, now, t))
			.toBe('Reachable at the last check, today 10:40')
	})

	it('counts a slow answer as reachable', () => {
		const at = new Date(2026, 10, 25, 9, 5)
		expect(lastCheckLine({ state: 'degraded', checkedAt: at.toISOString() }, now, t))
			.toBe('Reachable at the last check, today 09:05')
	})

	it('reads an unreachable tile, and names the date of an older check', () => {
		const at = new Date(2026, 10, 24, 23, 59)
		expect(lastCheckLine({ state: 'offline', checkedAt: at.toISOString() }, now, t))
			.toBe(`Not reachable at the last check, ${at.toLocaleDateString()} 23:59`)
	})

	it('says not checked yet without a reading, a state or a usable time', () => {
		expect(lastCheckLine(null, now, t)).toBe('Not checked yet')
		expect(lastCheckLine({ state: null, checkedAt: null }, now, t)).toBe('Not checked yet')
		expect(lastCheckLine({ state: 'online', checkedAt: 'not a time' }, now, t)).toBe('Not checked yet')
		expect(lastCheckLine({ state: 'unknown', checkedAt: now.toISOString() }, now, t)).toBe('Not checked yet')
	})
})
