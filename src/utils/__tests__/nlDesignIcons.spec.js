/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for the NL Design icon-pack reshapers. The pack comes
 * bundled from @conduction/nextcloud-vue as `NL_DESIGN_ICON_GROUPS` (the three
 * CC0/EUPL NL-government sets, each a list of self-contained data URIs), so it
 * is always available regardless of whether the `nldesign` app is enabled.
 * `nlDesignIconGroups()` passes the groups straight through; the tile/style
 * builders flatten them into the picker shapes.
 */

import { describe, it, expect, vi } from 'vitest'

vi.mock('@conduction/nextcloud-vue', () => ({
	NL_DESIGN_ICON_GROUPS: [
		{ key: 'rvo', label: 'RVO', icons: [{ id: 'rvo-star', label: 'Star', url: 'data:image/svg+xml,STAR' }] },
		{ key: 'den-haag', label: 'Den Haag', icons: [{ id: 'dh-bell', label: 'Bell', url: 'data:image/svg+xml,BELL' }] },
	],
}))

const { nlDesignIconGroups, nlDesignTileIcons, nlDesignStyleIcons } = await import('../nlDesignIcons.js')

describe('nlDesignIcons — bundled grouped pack (no nldesign app needed)', () => {
	it('passes the groups through for CnIconBrowser url-icon-groups', () => {
		const groups = nlDesignIconGroups()
		expect(groups).toHaveLength(2)
		expect(groups[0]).toMatchObject({ key: 'rvo', label: 'RVO' })
		expect(groups[0].icons[0].url).toBe('data:image/svg+xml,STAR')
	})

	it('flattens all sets into tile icons {label,url}', () => {
		const icons = nlDesignTileIcons()
		expect(icons).toEqual([
			{ label: 'Star', url: 'data:image/svg+xml,STAR' },
			{ label: 'Bell', url: 'data:image/svg+xml,BELL' },
		])
	})

	it('flattens all sets into style icons {id,label,icon}', () => {
		const icons = nlDesignStyleIcons()
		expect(icons[0]).toEqual({ id: 'rvo-star', label: 'Star', icon: 'data:image/svg+xml,STAR' })
		expect(icons).toHaveLength(2)
	})
})
