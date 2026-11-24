/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

import { describe, expect, it, vi } from 'vitest'
import { ICON_CATALOGUE, LAZY_ICON_CATALOGUE } from '../iconCatalogue.js'

describe('LAZY_ICON_CATALOGUE', () => {
	it('loads nothing until it is read', () => {
		expect(ICON_CATALOGUE).toHaveLength(0)
	})

	it('starts the catalogue load on its first read and then shows the full set', async () => {
		expect(LAZY_ICON_CATALOGUE.length).toBe(0)
		await vi.waitFor(() => expect(ICON_CATALOGUE.length).toBeGreaterThan(1000), { timeout: 10000 })
		expect(LAZY_ICON_CATALOGUE.length).toBe(ICON_CATALOGUE.length)
		expect(LAZY_ICON_CATALOGUE[0]).toBe(ICON_CATALOGUE[0])
	})
})
