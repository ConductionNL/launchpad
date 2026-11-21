/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The app prefix an address carries, so the addresses the app writes stay
 * under the base its router is mounted on.
 *
 * @spec openspec/specs/dashboard-deeplinking/spec.md
 */

import { describe, expect, it, vi } from 'vitest'
import { appBase, dashboardUuidFromHash } from '../bootPath.js'

// Pretty URLs on: generateUrl drops the front controller.
vi.mock('@nextcloud/router', () => ({
	generateUrl: (path) => path,
}))

describe('appBase', () => {
	it('keeps the front controller when the page was opened through it', () => {
		expect(appBase('/index.php/apps/launchpad/finance/q1')).toBe('/index.php/apps/launchpad')
		expect(appBase('/index.php/apps/launchpad')).toBe('/index.php/apps/launchpad')
	})

	it('has no front controller when the address had none', () => {
		expect(appBase('/apps/launchpad/finance')).toBe('/apps/launchpad')
	})

	it('keeps a subdirectory install', () => {
		expect(appBase('/cloud/index.php/apps/launchpad/x')).toBe('/cloud/index.php/apps/launchpad')
	})

	it('falls back to generateUrl off the app', () => {
		expect(appBase('/settings/user')).toBe('/apps/launchpad')
		expect(appBase('')).toBe('/apps/launchpad')
	})
})

describe('dashboardUuidFromHash', () => {
	it('reads the uuid of a unified search link, with or without a widget hint', () => {
		expect(dashboardUuidFromHash('#dashboard/abc-123')).toBe('abc-123')
		expect(dashboardUuidFromHash('#dashboard/abc-123;widget=42')).toBe('abc-123')
		expect(dashboardUuidFromHash('#dashboard/a%20b')).toBe('a b')
	})

	it('names no dashboard for any other hash', () => {
		expect(dashboardUuidFromHash('')).toBe('')
		expect(dashboardUuidFromHash('#user-settings')).toBe('')
		expect(dashboardUuidFromHash('#dashboard/')).toBe('')
		expect(dashboardUuidFromHash('#dashboard/%E0%A4%A')).toBe('')
		expect(dashboardUuidFromHash(null)).toBe('')
	})
})
