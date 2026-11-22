/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A dashboard without a greeting row opens with its name, its action and the
 * slot the "Meer" menu docks into (LpWerkplek, LpIntranet, LpAfdeling).
 *
 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-009-no-navigation-panel-the-destinations-are-in-the-more-menu
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import DashboardPageHeader, { lastChangedText } from '../DashboardPageHeader.vue'
import { PAGE_HEADER_DOCK_CLASS } from '../../../utils/headerActionsDock.js'

vi.mock('@nextcloud/l10n', () => ({
	getLanguage: () => 'nl',
	t: (_app, text, vars = {}) => text.replace(/\{(\w+)\}/g, (_m, key) => vars[key] ?? ''),
}))

const NOW = new Date(2026, 9, 5, 10, 47)

function mountHeader(props) {
	return mount(DashboardPageHeader, {
		props: { dashboard: { name: 'Mijn werkplek', description: '', updatedAt: null }, ...props },
	})
}

describe('DashboardPageHeader', () => {
	it('shows the dashboard name as the page title and a dock slot for the menu', () => {
		const wrapper = mountHeader({})
		expect(wrapper.find('h1').text()).toBe('Mijn werkplek')
		expect(wrapper.find('.' + PAGE_HEADER_DOCK_CLASS).exists()).toBe(true)
	})

	it('offers Edit with full rights, Add widget with add-only rights, nothing read-only or while editing', async () => {
		const full = mountHeader({ permissionLevel: 'full' })
		expect(full.find('[data-testid="dashboard-page-header-action"]').text()).toBe('Edit')
		await full.find('[data-testid="dashboard-page-header-action"]').trigger('click')
		expect(full.emitted('edit')).toHaveLength(1)

		const add = mountHeader({ permissionLevel: 'add_only' })
		await add.find('[data-testid="dashboard-page-header-action"]').trigger('click')
		expect(add.emitted('addWidget')).toHaveLength(1)

		expect(mountHeader({ permissionLevel: 'view_only' }).find('[data-testid="dashboard-page-header-action"]').exists()).toBe(false)
		expect(mountHeader({ permissionLevel: 'full', isEditMode: true }).find('[data-testid="dashboard-page-header-action"]').exists()).toBe(false)
	})

	it('names a personal dashboard, the one that opens first, and a shared one; a group dashboard has no chip', () => {
		expect(mountHeader({ source: 'user' }).find('[data-testid="dashboard-page-header-kind"]').text()).toBe('Personal dashboard')
		expect(mountHeader({ source: 'user', isDefault: true }).find('[data-testid="dashboard-page-header-kind"]').text()).toBe('Personal dashboard · opens first')
		expect(mountHeader({ source: 'shared' }).find('[data-testid="dashboard-page-header-kind"]').text()).toBe('Shared with you')
		expect(mountHeader({ source: 'group' }).find('[data-testid="dashboard-page-header-kind"]').exists()).toBe(false)
	})

	it('joins the description and the last change into the meta line', () => {
		const wrapper = mountHeader({
			source: 'group',
			dashboard: { name: 'Intranet Zuiddrecht', description: 'Groepsdashboard van Communicatie', updatedAt: new Date().toISOString() },
		})
		expect(wrapper.find('.launchpad-page-header__meta').text()).toMatch(/^Groepsdashboard van Communicatie · Last changed today \d\d:\d\d$/)
	})
})

describe('lastChangedText', () => {
	it('says the time today, the weekday within a week and the date before that', () => {
		expect(lastChangedText(new Date(2026, 9, 5, 9, 10).toISOString(), NOW, 'nl')).toBe('Last changed today 09:10')
		expect(lastChangedText(new Date(2026, 9, 2, 16, 0).toISOString(), NOW, 'nl')).toBe('Last changed vrijdag')
		expect(lastChangedText(new Date(2026, 8, 28, 12, 0).toISOString(), NOW, 'nl')).toBe('Last changed 28 september')
	})

	it('says nothing without a usable date', () => {
		expect(lastChangedText(null, NOW, 'nl')).toBe('')
		expect(lastChangedText('not a date', NOW, 'nl')).toBe('')
	})
})
