/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for the NL Design icon-pack reshapers. The pack now comes
 * bundled from @conduction/nextcloud-vue (`NL_DESIGN_ICONS`) as self-contained
 * data URIs, so it is always available regardless of whether the `nldesign`
 * app is enabled. These tests assert the two picker-shape mappings.
 */

import { describe, it, expect, vi } from 'vitest'

vi.mock('@conduction/nextcloud-vue', () => ({
	NL_DESIGN_ICONS: [
		{ id: 'nl-star', label: 'Star', name: 'Star', url: 'data:image/svg+xml;base64,U1RBUg==' },
		{ id: 'nl-bell', label: 'Bell', name: 'Bell', url: 'data:image/svg+xml;base64,QkVMTA==' },
	],
}))

const { nlDesignTileIcons, nlDesignStyleIcons } = await import('../nlDesignIcons.js')

describe('nlDesignIcons — bundled pack (no nldesign app needed)', () => {
	it('builds tile icons as {label,url} with data-URI urls', () => {
		const icons = nlDesignTileIcons()
		expect(icons).toEqual([
			{ label: 'Star', url: 'data:image/svg+xml;base64,U1RBUg==' },
			{ label: 'Bell', url: 'data:image/svg+xml;base64,QkVMTA==' },
		])
		expect(icons.every((i) => i.url.startsWith('data:image/svg+xml'))).toBe(true)
	})

	it('builds style icons as {id,label,icon} with the data URI as icon', () => {
		const icons = nlDesignStyleIcons()
		expect(icons[0]).toEqual({ id: 'nl-star', label: 'Star', icon: 'data:image/svg+xml;base64,U1RBUg==' })
		expect(icons).toHaveLength(2)
	})
})
