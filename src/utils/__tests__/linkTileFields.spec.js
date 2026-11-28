// SPDX-License-Identifier: EUPL-1.2
// SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>

/**
 * A link tile made with the App or link tile form keeps its fields in content;
 * a tile made before keeps them in its flat columns (REQ-TILE-PLACEMENT).
 *
 * @spec openspec/specs/tiles/spec.md#requirement-req-tile-placement-inline-content-placement-model-promoted-to-canonical
 */

import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/router', () => ({
	imagePath: (app, file) => `/apps/${app}/img/${file}`,
}))

const { isContentLinkTile, LINK_TILE_FIELDS, linkTileFieldContent, linkTileFields } = await import('../linkTileFields.js')

describe('isContentLinkTile', () => {
	it('is a tile widget without the legacy tile type, not in app mode', () => {
		expect(isContentLinkTile({ widgetId: 'tile', content: { mode: 'link' } })).toBe(true)
		expect(isContentLinkTile({ widgetId: 'tile', content: {} })).toBe(true)
		expect(isContentLinkTile({ widgetId: 'tile', content: { mode: 'app', appId: 'files' } })).toBe(false)
		expect(isContentLinkTile({ widgetId: 'tile', tileType: 'preset', content: {} })).toBe(false)
		expect(isContentLinkTile({ widgetId: 'tile-abc', tileType: 'custom' })).toBe(false)
		expect(isContentLinkTile({ widgetId: 'notes' })).toBe(false)
		expect(isContentLinkTile(null)).toBe(false)
	})
})

describe('linkTileFields', () => {
	it('reads a content link tile from its content, defaulting what is missing', () => {
		expect(linkTileFields({ widgetId: 'tile', content: { mode: 'link', title: 'Wiki', linkValue: 'https://w.test' } })).toEqual({
			title: 'Wiki',
			icon: 'icon-link',
			iconType: 'class',
			backgroundColor: '#0082c9',
			textColor: '#ffffff',
			linkType: 'url',
			linkValue: 'https://w.test',
		})
	})

	it('reads a tile made before from its flat columns', () => {
		expect(linkTileFields({
			widgetId: 'tile-1',
			tileType: 'custom',
			tileTitle: 'Mail',
			tileIcon: 'mail',
			tileIconType: 'mdi',
			tileBackgroundColor: '#000',
			tileTextColor: '#fff',
			tileLinkType: 'app',
			tileLinkValue: 'mail',
			content: { title: 'ignored' },
		})).toEqual({ title: 'Mail', icon: 'mail', iconType: 'mdi', backgroundColor: '#000', textColor: '#fff', linkType: 'app', linkValue: 'mail' })
	})
})

describe('linkTileFieldContent', () => {
	it('takes exactly the link fields of the form result', () => {
		const content = linkTileFieldContent({ title: 'Wiki', icon: 'Web', iconType: 'mdi', backgroundColor: '#111', textColor: '#eee', linkType: 'url', linkValue: 'https://w.test', healthPingEnabled: true, id: 4 })
		expect(Object.keys(content)).toEqual([...LINK_TILE_FIELDS])
		expect(content).toEqual({ title: 'Wiki', icon: 'Web', iconType: 'mdi', backgroundColor: '#111', textColor: '#eee', linkType: 'url', linkValue: 'https://w.test' })
	})

	it('defaults the fields the form left empty', () => {
		expect(linkTileFieldContent({ title: 'X', icon: '', linkType: null })).toMatchObject({ title: 'X', icon: 'icon-link', iconType: 'class', linkType: 'url', linkValue: '' })
	})
})
