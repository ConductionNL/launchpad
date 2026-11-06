/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The admin page opens on Algemeen (board LpBeheerAlgemeen). The library's
 * admin shell draws the title, the path line and the cards; the defaults,
 * group-shared dashboards and the default-app note are cards of Algemeen.
 */

import { readFileSync } from 'fs'
import { join } from 'path'
import { describe, expect, it, vi } from 'vitest'
import AdminSettings from '../AdminSettings.vue'

vi.mock('@nextcloud/router', async (importOriginal) => ({
	...(await importOriginal()),
	generateUrl: (path) => `/index.php${path}`,
}))

/**
 * A stand-in for `t()` that fills `{placeholders}`.
 *
 * @param {string} _app the app id (unused)
 * @param {string} text the English source string
 * @param {object} vars the placeholder values
 * @return {string} the filled string
 */
function t(_app, text, vars = {}) {
	return text.replace(/{(\w+)}/g, (_match, key) => vars[key] ?? `{${key}}`)
}

/**
 * Build a `this` for the computed properties, on one active tab.
 *
 * @param {string} activeTab the open tab's slug
 * @return {object} the context
 */
function context(activeTab) {
	const ctx = { t, activeTab }
	ctx.beheerTabs = AdminSettings.computed.beheerTabs.call(ctx)
	return ctx
}

const source = readFileSync(join(__dirname, '../AdminSettings.vue'), 'utf8')

describe('AdminSettings tabs', () => {
	const tabs = AdminSettings.computed.beheerTabs.call({ t })
	const render = AdminSettings.render?.toString() ?? ''

	it('puts General first, the tab the shell opens on', () => {
		expect(tabs[0]).toEqual({ id: 'general', label: 'General' })
		expect(tabs[1].id).toBe('templates')
	})

	it('hands the twelve tabs to the admin shell as an underline strip', () => {
		expect(tabs.map((tab) => tab.id)).toEqual([
			'general',
			'templates',
			'operations',
			'roles-permissions',
			'versioning-audit',
			'sharing',
			'kiosk',
			'org-navigation',
			'demo-data',
			'group-dashboards',
			'detail-fields',
			'profile-fields',
		])
		expect(source).toContain(':tabs="beheerTabs"')
		expect(render).toContain('tabsVariant: "underline"')
	})

	it('remembers the last tab under the key the page used before', () => {
		expect(render).toContain('tabStorageKey: "launchpad.admin.activeTab"')
	})

	it('opens the tab named by ?tab=, the shell default query key', () => {
		expect(source).not.toContain('tabQueryKey')
	})

	it('gives every tab its own slot', () => {
		for (const tab of tabs) {
			expect(source).toContain(`<template #tab-${tab.id}>`)
		}
	})

	it('lets the shell mount a tab\'s content only while that tab is open', () => {
		expect(render).toContain('lazyTabs: ""')
		for (const tab of tabs.slice(1)) {
			const slot = new RegExp(`<template #tab-${tab.id}>\\s*<\\w+[^>]*>`)
			const panel = source.match(slot)
			expect(panel).not.toBeNull()
			expect(panel[0]).not.toContain('v-if')
		}
	})

	it('follows the tab the shell reports', () => {
		expect(source).toContain('@tabChange="onTabChange"')
		const ctx = { activeTab: 'general' }
		AdminSettings.methods.onTabChange.call(ctx, 'sharing')
		expect(ctx.activeTab).toBe('sharing')
	})

	it('shows the setup wizard banner in the General panel', () => {
		const general = source.indexOf('<template #tab-general>')
		const banner = source.indexOf('data-test="setup-wizard-banner"')
		const templates = source.indexOf('<template #tab-templates>')
		expect(general).toBeGreaterThan(-1)
		expect(banner).toBeGreaterThan(general)
		expect(banner).toBeLessThan(templates)
	})

	it('draws the defaults as a card of General only', () => {
		const general = source.indexOf('v-if="activeTab === \'general\'"')
		const defaults = source.indexOf('data-testid="admin-default-settings"')
		expect(general).toBeGreaterThan(-1)
		expect(defaults).toBeGreaterThan(general)
	})
})

describe('AdminSettings page header', () => {
	it('draws in the board look of the admin shell', () => {
		expect(source).toContain('<CnAdminSettingsShell')
		expect(source).toContain('look="board"')
		expect(source).toContain(':showVersionCard="false"')
	})

	it('names the open tab in the title', () => {
		expect(AdminSettings.computed.pageTitle.call(context('general'))).toBe(
			'Launchpad administration: general',
		)
		expect(AdminSettings.computed.pageTitle.call(context('templates'))).toBe(
			'Launchpad administration: templates',
		)
	})

	it('ends the path on the open tab, the first step a link', () => {
		const crumbs = AdminSettings.computed.breadcrumbs.call(context('general'))
		expect(crumbs).toEqual([
			{ label: 'Admin settings', href: '/index.php/settings/admin' },
			{ label: 'Launchpad' },
			{ label: 'General' },
		])
	})

	it('notes the reach of Algemeen only', () => {
		expect(AdminSettings.computed.breadcrumbNote.call(context('general'))).toBe(
			'applies to the whole organisation',
		)
		expect(AdminSettings.computed.breadcrumbNote.call(context('kiosk'))).toBe('')
	})
})
