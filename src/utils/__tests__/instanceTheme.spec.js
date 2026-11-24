/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The instance theme name the dashboard settings show read-only (REQ-THM-004).
 *
 * @spec openspec/changes/dashboard-theme-readonly/specs/dashboards/spec.md#requirement-the-details-tab-shows-the-instance-theme-read-only-req-thm-004
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'

const capabilities = vi.fn()
vi.mock('@nextcloud/capabilities', () => ({ getCapabilities: () => capabilities() }))

const { instanceThemeName } = await import('../instanceTheme.js')

describe('instanceThemeName', () => {
	beforeEach(() => capabilities.mockReset())

	it('reads the active thematiq theme when thematiq sets one', () => {
		capabilities.mockReturnValue({
			nldesign: { tokenSet: { id: 'zuiddrecht', name: 'Huisstijl Zuiddrecht' } },
			theming: { name: 'Nextcloud' },
		})
		expect(instanceThemeName()).toBe('Huisstijl Zuiddrecht')
	})

	it('falls back to the Nextcloud theming name without thematiq', () => {
		capabilities.mockReturnValue({ theming: { name: 'Gemeente Cloud' } })
		expect(instanceThemeName()).toBe('Gemeente Cloud')
	})

	it('falls back to theming when thematiq publishes an empty name', () => {
		capabilities.mockReturnValue({ nldesign: { tokenSet: { name: ' ' } }, theming: { name: 'Nextcloud' } })
		expect(instanceThemeName()).toBe('Nextcloud')
	})

	it('is empty without capabilities', () => {
		capabilities.mockImplementation(() => {
			throw new Error('no capabilities')
		})
		expect(instanceThemeName()).toBe('')
		capabilities.mockReturnValue(null)
		expect(instanceThemeName()).toBe('')
	})
})
