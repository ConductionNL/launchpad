/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The manifest's strings (menu labels, page titles and descriptions) are
 * translated in LaunchPad's own catalogue. `CnAppRoot` defaults its
 * `translate` prop to the identity, so without the binding every one of them
 * stayed English.
 */

import { describe, expect, it, vi } from 'vitest'

const translate = vi.fn((app, text) => `${app}:${text}`)

vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...(await importOriginal()),
	translate: (...args) => translate(...args),
}))
vi.mock('@conduction/nextcloud-vue', () => ({
	CnAppRoot: { name: 'CnAppRoot', template: '<div />' },
}))
vi.mock('../customComponents.js', () => ({ default: {} }))
vi.mock('../services/iconCatalogue.js', () => ({ ICON_CATALOGUE: [] }))

import App from '../App.vue'

describe('App manifest translator', () => {
	it('looks a manifest string up in the launchpad catalogue', () => {
		expect(App.methods.translateForApp('Documentation')).toBe(
			'launchpad:Documentation',
		)
	})

	it('passes placeholder values on', () => {
		const vars = { count: 3 }
		App.methods.translateForApp('{count} dashboards', vars)
		expect(translate).toHaveBeenLastCalledWith(
			'launchpad',
			'{count} dashboards',
			vars,
		)
	})

	it('is what the root receives', () => {
		// The template binding is the wiring; a method nobody binds is a
		// translator with no call site.
		expect(App.render?.toString() ?? '').toContain('translateForApp')
	})
})
