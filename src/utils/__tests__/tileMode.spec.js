// SPDX-License-Identifier: EUPL-1.2
// SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>

/**
 * The App or link tile's mode, the retired app tile read as one, and the
 * brand defaults (app-link-tile-widget REQ-APPTILE-001, -004, -006).
 */

import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/router', () => ({
	imagePath: (app, file) => `/apps/${app}/img/${file}`,
}))

const { appTileAsTileContent, appTileColourKey, appTileContent, asAppOrLinkTile, brandDefaults, tileMode } = await import('../tileMode.js')

describe('tileMode', () => {
	it('is app only when the tile says so', () => {
		expect(tileMode({ mode: 'app' })).toBe('app')
		expect(tileMode({ mode: 'link' })).toBe('link')
		expect(tileMode({})).toBe('link')
		expect(tileMode(null)).toBe('link')
	})

	it('builds the content of a new app tile, white text by default', () => {
		expect(appTileContent('files')).toEqual({
			mode: 'app', appId: 'files', colourToken: '', textColour: 'white', showCount: true, pinned: false,
		})
		expect(appTileContent('deck', { colourToken: '--sc-accent', textColour: 'black', showCount: false, pinned: true })).toMatchObject({
			colourToken: '--sc-accent', textColour: 'black', showCount: false, pinned: true,
		})
	})

	it('maps a colour token onto the shared palette key', () => {
		expect(appTileColourKey({ colourToken: '--sc-accent-deep' })).toBe('accent-deep')
		expect(appTileColourKey({ colour: 'primary' })).toBe('primary')
		expect(appTileColourKey({})).toBe('')
	})
})

describe('the retired app tile', () => {
	it('becomes a tile in app mode with the same id and everything else', () => {
		const placement = { id: 7, widgetId: 'app-tile', gridX: 3, gridY: 2, content: { appId: 'files', colour: 'accent', showCount: false } }
		const tile = asAppOrLinkTile(placement)
		expect(tile).toEqual({
			id: 7,
			widgetId: 'tile',
			gridX: 3,
			gridY: 2,
			content: { appId: 'files', colour: 'accent', colourToken: '--sc-accent', showCount: false, mode: 'app' },
		})
		expect(placement.widgetId).toBe('app-tile')
	})

	it('converts a group child and leaves other items alone', () => {
		expect(asAppOrLinkTile({ type: 'app-tile', content: { appId: 'deck' } })).toEqual({ type: 'tile', content: { appId: 'deck', mode: 'app' } })
		const link = { widgetId: 'tile', content: { title: 'Intranet' } }
		expect(asAppOrLinkTile(link)).toBe(link)
		expect(asAppOrLinkTile(null)).toBeNull()
		expect(appTileAsTileContent(null)).toEqual({ mode: 'app' })
		expect(appTileAsTileContent({ colour: 'primary', colourToken: '--sc-accent' }).colourToken).toBe('--sc-accent')
	})
})

describe('brandDefaults', () => {
	it('gives the Nextcloud site the white logo on #0082C9 with white text', () => {
		expect(brandDefaults('https://nextcloud.com')).toEqual({
			brand: 'Nextcloud',
			icon: '/apps/launchpad/img/nextcloud-white.svg',
			iconType: 'url',
			backgroundColor: '#0082C9',
			colourToken: '',
			textColour: 'white',
		})
	})

	it('knows Conduction and sendent by host or name', () => {
		expect(brandDefaults('www.conduction.nl/over')?.brand).toBe('Conduction')
		expect(brandDefaults('https://www.sendent.com')?.brand).toBe('Sendent')
		expect(brandDefaults('Sendent')?.brand).toBe('Sendent')
	})

	it('has none for any other address', () => {
		expect(brandDefaults('https://intranet.zuiddrecht.nl')).toBeNull()
		expect(brandDefaults('https://notnextcloud.com')).toBeNull()
		expect(brandDefaults('')).toBeNull()
		expect(brandDefaults('http://[bad')).toBeNull()
	})
})
