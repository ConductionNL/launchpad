// SPDX-License-Identifier: EUPL-1.2
// SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>

/**
 * The choices of the Add tiles dialog (REQ-TILE-043, REQ-TILE-044).
 */

import { describe, expect, it } from 'vitest'
import {
	catalogueChoice,
	discoveredChoice,
	existingTileKeys,
	officeChoice,
	signOnKind,
	tileKey,
	tilesToAdd,
} from '../addTiles.js'

describe('tileKey', () => {
	it('keys a tile by its link, an app link by the app alone', () => {
		expect(tileKey({ linkType: 'url', linkValue: 'https://a.test' })).toBe('url:https://a.test')
		expect(tileKey({ linkType: 'app', linkValue: 'mail' })).toBe('app:mail')
		expect(tileKey({ linkType: 'app', linkValue: '/apps/mail/' })).toBe('app:mail')
		expect(tileKey({})).toBe('url:')
	})
})

describe('existingTileKeys', () => {
	it('reads tiles and app tiles of the dashboard', () => {
		const keys = existingTileKeys([
			{ tileLinkType: 'url', tileLinkValue: 'https://a.test' },
			{ widgetId: 'app-tile', content: { appId: 'deck' } },
			{ widgetId: 'clock' },
		])
		expect([...keys]).toEqual(['url:https://a.test', 'app:deck'])
		expect(existingTileKeys(null).size).toBe(0)
	})
})

describe('choices per section', () => {
	it('makes a catalogue entry an ordinary tile', () => {
		expect(catalogueChoice({ name: 'Wiki', icon: 'i', iconType: 'url', linkType: 'url', linkValue: 'https://wiki.test' })).toEqual({
			key: 'url:https://wiki.test',
			kind: 'tile',
			tile: { title: 'Wiki', icon: 'i', iconType: 'url', linkType: 'url', linkValue: 'https://wiki.test' },
		})
	})

	it('makes a found app its own app tile', () => {
		expect(discoveredChoice({ appId: 'grafana', name: 'Grafana' })).toEqual({ key: 'app:grafana', kind: 'app-tile', tile: { appId: 'grafana' } })
	})

	it('makes an office pack tile a tile of its own', () => {
		expect(officeChoice({ title: 'New document', linkType: 'create', linkValue: 'richdocuments-doc', icon: 'icon-filetype-file', iconType: 'class' })).toEqual({
			key: 'create:richdocuments-doc',
			kind: 'tile',
			tile: { title: 'New document', icon: 'icon-filetype-file', iconType: 'class', linkType: 'create', linkValue: 'richdocuments-doc' },
		})
	})
})

describe('tilesToAdd', () => {
	it('adds each tile once and none the dashboard holds', () => {
		const mail = officeChoice({ title: 'Mail', linkType: 'app', linkValue: 'mail' })
		const mailFound = discoveredChoice({ appId: 'mail' })
		const deck = discoveredChoice({ appId: 'deck' })
		const files = officeChoice({ title: 'Files', linkType: 'app', linkValue: 'files' })
		expect(tilesToAdd([mail, mailFound, deck, files], new Set(['app:deck']))).toEqual([mail, files])
		expect(tilesToAdd(null)).toEqual([])
	})
})

describe('signOnKind', () => {
	it('reads the sign-on from the link, single sign-on when the entry says so', () => {
		expect(signOnKind({ linkType: 'app' })).toBe('nextcloud')
		expect(signOnKind({ linkType: 'url' })).toBe('own')
		expect(signOnKind({ linkType: 'url', signOn: 'sso' })).toBe('sso')
	})
})
