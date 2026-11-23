/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

import { describe, expect, it } from 'vitest'
import { parseServerStamp } from '../serverStamp.js'

describe('parseServerStamp', () => {
	it('reads a bare server stamp as UTC', () => {
		expect(parseServerStamp('2026-11-23 08:00:00')?.toISOString()).toBe('2026-11-23T08:00:00.000Z')
		expect(parseServerStamp('2026-11-23T08:00')?.toISOString()).toBe('2026-11-23T08:00:00.000Z')
	})

	it('keeps an explicit offset or Z as written', () => {
		expect(parseServerStamp('2026-11-23T09:00:00+01:00')?.toISOString()).toBe('2026-11-23T08:00:00.000Z')
		expect(parseServerStamp('2026-11-23T08:00:00Z')?.toISOString()).toBe('2026-11-23T08:00:00.000Z')
	})

	it('answers null for nothing or something unreadable', () => {
		expect(parseServerStamp(null)).toBeNull()
		expect(parseServerStamp(undefined)).toBeNull()
		expect(parseServerStamp('')).toBeNull()
		expect(parseServerStamp('never')).toBeNull()
	})
})
