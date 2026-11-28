/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * LaunchPad runs without OpenRegister (launchpad-adopt-or-abstractions,
 * "Install on OR-less Nextcloud"). `CnAppRoot` blocks the whole shell for
 * every app in `requiresApps`, and its default is `['openregister']`, so the
 * root must be told that LaunchPad requires no app.
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@conduction/nextcloud-vue', () => ({
	CnAppRoot: {
		name: 'CnAppRoot',
		props: { requiresApps: { type: Array, default: () => ['openregister'] } },
		template: '<div><slot name="menu" /><main /></div>',
	},
	CnCredentials: { name: 'CnCredentials', template: '<div />' },
	CnNotificationPreferences: { name: 'CnNotificationPreferences', template: '<div />' },
}))
vi.mock('../customComponents.js', () => ({ default: {} }))
vi.mock('../services/iconCatalogue.js', () => ({ ICON_CATALOGUE: [], LAZY_ICON_CATALOGUE: [], loadIconCatalogue: () => Promise.resolve([]) }))

import App from '../App.vue'
import bundledStub from '../manifest.json'

describe('App without OpenRegister', () => {
	it('tells the app root that no app is required, so a missing OpenRegister does not block the shell', () => {
		const wrapper = mount(App, {
			props: { manifest: bundledStub, registry: {}, pageTypes: {}, permissions: ['user'] },
			global: { mocks: { $route: { name: 'Workspace' } } },
		})
		expect(wrapper.findComponent({ name: 'CnAppRoot' }).props('requiresApps')).toEqual([])
	})
})
