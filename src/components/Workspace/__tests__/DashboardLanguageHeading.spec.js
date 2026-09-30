/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The workspace page shows the reader's language version, and the primary
 * with a note when none matches (REQ-LANGUI-002). The response is
 * DashboardTranslationApiController::resolved's.
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import DashboardLanguageHeading from '../DashboardLanguageHeading.vue'
import { api } from '../../../services/api.js'

vi.mock('@nextcloud/l10n', () => ({ t: (_app, text) => text }))
vi.mock('../../../services/api.js', () => ({
	api: { getResolvedDashboard: vi.fn() },
}))

function resolved(lang, name, description, isFallback) {
	return {
		data: {
			dashboard: { uuid: 'intra', name: 'Intranet' },
			translation: {
				languageCode: lang,
				name,
				description,
				isPrimary: isFallback ? 1 : 0,
			},
			availableLanguages: ['en', 'nl'],
			currentLanguage: lang,
			isFallback,
		},
	}
}

beforeEach(() => vi.clearAllMocks())

describe('DashboardLanguageHeading', () => {
	it('REQ-LANGUI-002: a Dutch reader sees the Dutch name and description', async () => {
		api.getResolvedDashboard.mockResolvedValue(
			resolved('nl', 'Intranet NL', 'Nieuws van vandaag', false),
		)
		const wrapper = mount(DashboardLanguageHeading, {
			props: {
				dashboard: { uuid: 'intra', name: 'Intranet', hasVariants: true },
			},
		})
		await flushPromises()
		expect(wrapper.text()).toContain('Intranet NL')
		expect(wrapper.text()).toContain('Nieuws van vandaag')
		expect(
			wrapper.find('[data-testid="dashboard-language-fallback"]').exists(),
		).toBe(false)
	})

	it('REQ-LANGUI-002: a German reader sees the primary with a note', async () => {
		api.getResolvedDashboard.mockResolvedValue(
			resolved('en', 'Intranet', 'News of the day', true),
		)
		const wrapper = mount(DashboardLanguageHeading, {
			props: {
				dashboard: { uuid: 'intra', name: 'Intranet', hasVariants: true },
			},
		})
		await flushPromises()
		expect(
			wrapper.find('[data-testid="dashboard-language-fallback"]').text(),
		).toBe('Shown in the primary language')
	})

	it('a dashboard with one language renders nothing and asks nothing', async () => {
		const wrapper = mount(DashboardLanguageHeading, {
			props: {
				dashboard: { uuid: 'intra', name: 'Intranet', hasVariants: false },
			},
		})
		await flushPromises()
		expect(wrapper.find('header').exists()).toBe(false)
		expect(api.getResolvedDashboard).not.toHaveBeenCalled()
	})
})
