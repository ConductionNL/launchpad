/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `AdminSettingsRedirect.vue`: on mount it sends the
 * browser to Launchpad's admin settings page, and shows a link to the same
 * page for when the redirect does not happen.
 */

import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import AdminSettingsRedirect from '../AdminSettingsRedirect.vue'

vi.mock('@nextcloud/router', () => ({
	generateUrl: (path) => '/index.php' + path,
}))
vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...(await importOriginal()),
	translate: (_app, text) => text,
}))

const originalLocation = window.location

beforeEach(() => {
	Object.defineProperty(window, 'location', {
		configurable: true,
		writable: true,
		value: { href: 'http://localhost/index.php/apps/launchpad/admin' },
	})
})

afterEach(() => {
	Object.defineProperty(window, 'location', {
		configurable: true,
		writable: true,
		value: originalLocation,
	})
})

describe('AdminSettingsRedirect', () => {
	it('sends the browser to the admin settings page on mount', () => {
		mount(AdminSettingsRedirect)

		expect(window.location.href).toBe('/index.php/settings/admin/launchpad')
	})

	it('shows a link to the same page as a fallback', () => {
		const wrapper = mount(AdminSettingsRedirect)

		const link = wrapper.find('[data-testid="AdminSettingsRedirect"] a')
		expect(link.attributes('href')).toBe('/index.php/settings/admin/launchpad')
		expect(link.text()).toBe('Continue to the administration settings')
		expect(wrapper.find('p').text()).toBe('Opening the administration settings…')
	})
})
