/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A dashboard without a greeting row opens with its name and the slot the
 * "Meer" menu docks into (LpWerkplek, LpIntranet, LpAfdeling).
 *
 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-009-no-navigation-panel-the-destinations-are-in-the-more-menu
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import DashboardPageHeader, { lastChangedText, rightsText } from '../DashboardPageHeader.vue'
import { PAGE_HEADER_DOCK_CLASS } from '../../../utils/headerActionsDock.js'
import CnSegmentedControl from '@conduction/nextcloud-vue/src/components/CnSegmentedControl/CnSegmentedControl.vue'

vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...(await importOriginal()),
	getCanonicalLocale: () => 'nl',
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

	it('offers no action of its own: Edit and Add widget are in the More menu (LpWerkplek)', () => {
		for (const props of [{}, { source: 'group' }, { isDefault: true }]) {
			expect(mountHeader(props).find('button').exists()).toBe(false)
		}
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

// @spec openspec/changes/dashboard-period-and-download/specs/dashboards/spec.md#requirement-the-header-says-what-dashboard-this-is-and-what-the-reader-may-do-req-dpd-001
describe('the reader\'s rights in the meta line', () => {
	it('says view only, you may add widgets, and nothing with full rights', () => {
		expect(rightsText('view_only')).toBe('view only')
		expect(rightsText('add_only')).toBe('you may add widgets')
		expect(rightsText('full')).toBe('')
		expect(rightsText(undefined)).toBe('')
	})

	it('reads "Groepsdashboard van Bedrijfsvoering · view only · Last changed today 10:45" for a view-only reader', () => {
		const changed = new Date()
		changed.setHours(10, 45, 0, 0)
		const wrapper = mountHeader({
			source: 'group',
			permissionLevel: 'view_only',
			dashboard: { name: 'Managementinformatie', description: 'Groepsdashboard van Bedrijfsvoering', updatedAt: changed.toISOString() },
		})
		expect(wrapper.find('.launchpad-page-header__meta').text()).toBe('Groepsdashboard van Bedrijfsvoering · view only · Last changed today 10:45')
	})

	it('names adding widgets for an add-only reader', () => {
		const wrapper = mountHeader({ source: 'shared', permissionLevel: 'add_only', dashboard: { name: 'Team', description: '', updatedAt: null } })
		expect(wrapper.find('.launchpad-page-header__meta').text()).toContain('you may add widgets')
	})

	it('adds nothing for the owner with full rights', () => {
		const wrapper = mountHeader({ source: 'user', dashboard: { name: 'Mijn werkplek', description: '', updatedAt: null } })
		expect(wrapper.find('.launchpad-page-header__meta').text()).toBe('Personal dashboard')
	})
})

// @spec openspec/changes/dashboard-period-and-download/specs/dashboards/spec.md#requirement-a-dashboard-can-have-a-period-that-its-figures-follow-req-dpd-002
describe('the period choice (REQ-DPD-002)', () => {
	it('is absent on a dashboard without dated sources', () => {
		expect(mountHeader({}).find('[data-testid="dashboard-period"]').exists()).toBe(false)
	})

	it('offers This month, Quarter and Year and asks the page for the chosen one', async () => {
		const wrapper = mount(DashboardPageHeader, {
			props: { dashboard: { name: 'Managementinformatie', description: '', updatedAt: null }, showsPeriod: true, period: 'month' },
			global: { components: { CnSegmentedControl } },
		})
		const control = wrapper.find('[data-testid="dashboard-period"]')
		expect(control.exists()).toBe(true)
		const options = control.findAll('button')
		expect(options.map((button) => button.text())).toEqual(['This month', 'Quarter', 'Year'])
		expect(options[0].attributes('aria-checked')).toBe('true')
		await options[1].trigger('click')
		expect(wrapper.emitted('update:period')).toEqual([['quarter']])
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
