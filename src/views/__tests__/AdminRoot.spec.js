/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The admin page in Nextcloud's settings framework: the open tab is the URL
 * fragment, a former tab's fragment opens its host tab at that section, and
 * only the held tabs show (admin-settings REQ-ADM-032, REQ-ADM-034).
 *
 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-the-admin-page-lives-in-nextclouds-settings-framework-req-adm-032
 */

import { describe, expect, it, vi } from 'vitest'
import {
	ADMIN_TABS,
	adminPageUrl,
	adminTab,
	FOLDED_ADMIN_TABS,
	heldTab,
	heldTabs,
	parseAdminFragment,
} from '../../utils/adminPage.js'

vi.mock('@nextcloud/router', async (importOriginal) => ({
	...(await importOriginal()),
	generateUrl: (path) => `/index.php${path}`,
}))
vi.mock('../../components/admin/AdminSettings.vue', () => ({ default: { name: 'AdminSettings', render: () => null } }))

const { default: AdminRoot } = await import('../AdminRoot.vue')

describe('admin page address', () => {
	it('lists the thirteen tabs in the boards\' order, Announcements second', () => {
		expect(ADMIN_TABS).toHaveLength(13)
		expect(ADMIN_TABS.slice(0, 3)).toEqual(['general', 'announcements', 'templates'])
		for (const host of Object.values(FOLDED_ADMIN_TABS)) {
			expect(ADMIN_TABS).toContain(host)
		}
	})

	it('reads the tab and section from the fragment', () => {
		expect(parseAdminFragment('#sharing')).toEqual({ tab: 'sharing', section: '' })
		expect(parseAdminFragment('#embeds')).toEqual({ tab: 'general', section: 'embeds' })
		expect(parseAdminFragment('#app-catalogue')).toEqual({ tab: 'operations', section: 'app-catalogue' })
		expect(parseAdminFragment('#announcements/abc/receipts')).toEqual({ tab: 'announcements', section: '' })
		expect(parseAdminFragment('')).toEqual({ tab: 'general', section: '' })
		expect(parseAdminFragment('#constructor')).toEqual({ tab: 'general', section: '' })
		expect(adminTab('toString')).toBe('general')
	})

	it('links to the settings page with the tab in the fragment and a query before it', () => {
		expect(adminPageUrl()).toBe('/index.php/settings/admin/launchpad#general')
		expect(adminPageUrl('templates')).toBe('/index.php/settings/admin/launchpad#templates')
		expect(adminPageUrl('embeds', 'embedType=widget&embedId=5')).toBe('/index.php/settings/admin/launchpad?embedType=widget&embedId=5#embeds')
		expect(adminPageUrl('nonsense')).toBe('/index.php/settings/admin/launchpad#general')
	})

	it('keeps the held tabs in the page order, every tab when none is sent', () => {
		expect(heldTabs(['templates', 'general'])).toEqual(['general', 'templates'])
		expect(heldTabs([])).toEqual(ADMIN_TABS)
		expect(heldTabs(undefined)).toEqual(ADMIN_TABS)
		expect(heldTab('sharing', ['announcements'])).toBe('announcements')
		expect(heldTab('announcements', ['announcements'])).toBe('announcements')
		expect(heldTab('general', [])).toBe('')
	})
})

describe('AdminRoot', () => {
	const ctx = (hash, adminTabs = []) => {
		const c = { hash, adminTabs }
		c.tabs = AdminRoot.computed.tabs.call(c)
		c.currentTab = AdminRoot.computed.currentTab.call(c)
		c.currentSection = AdminRoot.computed.currentSection.call(c)
		return c
	}

	it('opens the fragment\'s tab, and a folded tab at its section', () => {
		expect(ctx('#sharing').currentTab).toBe('sharing')
		const embeds = ctx('#embeds')
		expect(embeds.currentTab).toBe('general')
		expect(embeds.currentSection).toBe('embeds')
	})

	it('shows an announcements manager only the Announcements tab', () => {
		const manager = ctx('#sharing', ['announcements'])
		expect(manager.tabs).toEqual(['announcements'])
		expect(manager.currentTab).toBe('announcements')
	})

	it('puts an opened tab in the fragment as a new history entry', () => {
		const pushState = vi.spyOn(globalThis.history, 'pushState')
		const c = ctx('#general')
		AdminRoot.methods.openTab.call(c, 'kiosk')
		expect(c.hash).toBe('#kiosk')
		expect(pushState).toHaveBeenCalledWith(null, '', '#kiosk')
		pushState.mockClear()
		const same = ctx('#kiosk')
		AdminRoot.methods.openTab.call(same, 'kiosk')
		expect(pushState).not.toHaveBeenCalled()
	})

	it('follows the fragment on Back and Forward', () => {
		const c = { hash: '' }
		globalThis.location.hash = '#templates'
		AdminRoot.methods.onHashChange.call(c)
		expect(c.hash).toBe('#templates')
		globalThis.location.hash = ''
	})
})
