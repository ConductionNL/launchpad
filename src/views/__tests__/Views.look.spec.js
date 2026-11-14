/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The dashboard page draws the active dashboard's look on its region and sends
 * a changed look with the save (platform-dashboard-theme-and-css REQ-THM-001/002).
 */
import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/router', async (importOriginal) => ({ ...(await importOriginal()), generateUrl: (path) => path }))
vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...(await importOriginal()),
	t: (_app, key, vars = {}) => key.replace(/\{(\w+)\}/g, (m, k) => (k in vars ? String(vars[k]) : m)),
}))
vi.mock('../../services/api.js', () => ({ api: { updateDashboard: vi.fn() } }))

import Views from '../Views.vue'
import { api } from '../../services/api.js'

describe('Views dashboard look', () => {
	it('draws the active dashboard\'s look, and nothing for one that follows the theme', () => {
		expect(Views.computed.dashboardLookStyle.call({ activeDashboard: { themeAccent: null } })).toEqual({})
		expect(Views.computed.dashboardLookStyle.call({ activeDashboard: { themeAccent: '#1f5c3a' } })).toEqual({
			'--color-primary-element': '#1f5c3a',
			'--color-primary-element-text': '#ffffff',
		})
	})

	it('sends the changed look with the rest of the save', async () => {
		api.updateDashboard.mockResolvedValueOnce({})
		const self = { configSaveError: '', loadDashboards: vi.fn(), closeConfigModal: vi.fn(), treeSaveMessage: vi.fn() }

		await Views.methods.saveDashboardConfig.call(self, { id: 7, name: 'Team Groen', description: '', icon: 'x', parentUuid: '', look: { themeAccent: '#1f5c3a' } })

		expect(api.updateDashboard).toHaveBeenCalledWith(7, { name: 'Team Groen', description: '', icon: 'x', parentUuid: '', themeAccent: '#1f5c3a' })
		expect(self.closeConfigModal).toHaveBeenCalled()
	})

	it('names the measured ratio when the server refuses a pale accent', () => {
		const error = { response: { data: { error: 'contrast_too_low', ratio: 1.1, message: 'x' } } }

		expect(Views.methods.treeSaveMessage.call({}, error)).toBe('Buttons in this colour are hard to see (contrast 1.1 to 1, needs 3 to 1)')
	})
})
