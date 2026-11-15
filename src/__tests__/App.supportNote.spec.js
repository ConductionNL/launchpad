/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The first-open support note is mounted for administrators only
 * (runtime-shell REQ-SHELL-008): `App.vue` hands `CnAppRoot` `false` for
 * every other account, which is the library's documented opt-out.
 */

import { describe, expect, it, vi } from 'vitest'

vi.mock('@conduction/nextcloud-vue', () => ({
	CnAppRoot: { name: 'CnAppRoot', template: '<div />' },
}))
vi.mock('../customComponents.js', () => ({ default: {} }))
vi.mock('../services/iconCatalogue.js', () => ({ ICON_CATALOGUE: [] }))

import App from '../App.vue'

describe('App support note', () => {
	it('is off for a member', () => {
		expect(
			App.computed.supportNoteForThisAccount.call({ permissions: ['user'] }),
		).toBe(false)
	})

	it('is off when the permissions are missing', () => {
		expect(
			App.computed.supportNoteForThisAccount.call({ permissions: undefined }),
		).toBe(false)
	})

	it('stays on for an administrator', () => {
		expect(
			App.computed.supportNoteForThisAccount.call({
				permissions: ['user', 'admin'],
			}),
		).toBe(true)
	})

	it('is what the root receives', () => {
		// The template binding is the wiring; a computed nobody binds is a
		// guard with no call site.
		expect(App.render?.toString() ?? '').toContain('supportNoteForThisAccount')
	})
})
