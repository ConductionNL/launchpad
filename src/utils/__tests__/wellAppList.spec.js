/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A well's app tiles from the viewer's app menu (REQ-CONT-008, REQ-CONT-009).
 *
 * @spec openspec/changes/archive/2026-11-27-layout-widgets-replace-multi-tile/tasks.md#task-3
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import {
	appTilePlacements,
	childAppId,
	childLabel,
	dynamicTileKey,
	listedApps,
	normaliseAppList,
	openableChildren,
	viewerAppMenu,
} from '../wellAppList.js'

const state = vi.hoisted(() => ({ apps: null }))
vi.mock('@nextcloud/initial-state', () => ({
	loadState: (app, key, fallback) => (app === 'core' && key === 'apps' && state.apps !== null ? state.apps : fallback),
}))

const menu = [
	{ id: 'launchpad', label: 'Launchpad', href: '/l', icon: '' },
	{ id: 'files', label: 'Files', href: '/f', icon: '' },
	{ id: 'zaaksysteem', label: 'Zaaksysteem', href: '/z', icon: '' },
	{ id: 'photos', label: 'Photos', href: '/p', icon: '' },
	{ id: 'spreed', label: 'Talk', href: '/t', icon: '' },
	{ id: 'deck', label: 'Deck', href: '/d', icon: '' },
]

beforeEach(() => {
	state.apps = null
})

describe('viewerAppMenu', () => {
	it('reads the boot app menu in menu order, and null without one', () => {
		expect(viewerAppMenu()).toBeNull()
		state.apps = [
			{ id: 'files', name: 'Files', href: '/f', icon: 'f.svg', order: 2 },
			{ id: 'launchpad', name: 'Launchpad', href: '/l', order: -5 },
			{ name: 'no id' },
		]
		expect(viewerAppMenu().map((app) => app.id)).toEqual(['launchpad', 'files'])
		expect(viewerAppMenu()[1]).toEqual({ id: 'files', label: 'Files', href: '/f', icon: 'f.svg' })
	})
})

describe('openableChildren (REQ-CONT-008)', () => {
	const children = [
		{ id: 1, type: 'tile', content: { mode: 'app', appId: 'files' } },
		{ id: 2, type: 'tile', content: { mode: 'app', appId: 'deck-not-enabled' } },
		{ id: 3, type: 'app-tile', content: { appId: 'other' } },
		{ id: 4, type: 'tile', content: { mode: 'link', url: 'https://x' } },
		{ id: 5, type: 'clock', content: {} },
	]

	it('leaves out app tiles for apps outside the menu and keeps everything else', () => {
		expect(openableChildren(children, menu).map((child) => child.id)).toEqual([1, 4, 5])
	})

	it('leaves everything when the page has no app menu', () => {
		expect(openableChildren(children, null)).toBe(children)
	})

	it('knows which children are app tiles', () => {
		expect(children.map(childAppId)).toEqual(['files', 'deck-not-enabled', 'other', '', ''])
	})
})

describe('listedApps (REQ-CONT-009)', () => {
	it('puts the pinned apps first, leaves out excluded apps and Launchpad', () => {
		const list = normaliseAppList({ pinned: ['zaaksysteem'], exclude: ['photos'] })
		expect(listedApps(menu, list).map((app) => [app.id, app.pinned])).toEqual([
			['zaaksysteem', true], ['files', false], ['spreed', false], ['deck', false],
		])
	})

	it('gives each viewer their own apps: a pinned app the viewer lacks is skipped', () => {
		const list = normaliseAppList({ pinned: ['zaaksysteem'] })
		const sanne = [{ id: 'deck', label: 'Deck' }]
		expect(listedApps(sanne, list).map((app) => app.id)).toEqual(['deck'])
	})

	it('shows only the limit after the pinned ones, most used first', () => {
		const list = normaliseAppList({ pinned: ['zaaksysteem'], mostUsedLimit: 2 })
		const use = { [dynamicTileKey(7, 'deck')]: { count: 5 }, [dynamicTileKey(7, 'photos')]: { count: 2 } }
		expect(listedApps(menu, list, { use, owner: 7 }).map((app) => app.id)).toEqual(['zaaksysteem', 'deck', 'photos'])
	})

	it('searches every app, also beyond the limit', () => {
		const list = normaliseAppList({ mostUsedLimit: 1, showFilter: true })
		expect(listedApps(menu, list, { query: 'S' }).map((app) => app.id)).toEqual(['files', 'zaaksysteem', 'photos'])
	})

	it('reads a stored list safely', () => {
		expect(normaliseAppList(null)).toBeNull()
		expect(normaliseAppList([])).toBeNull()
		expect(normaliseAppList({ mostUsedLimit: -3, pinned: 'x' })).toEqual({ pinned: [], exclude: [], mostUsedLimit: 0, showFilter: false })
	})
})

describe('appTilePlacements', () => {
	it('makes App or link tiles in app mode, keyed per app and well', () => {
		const [tile] = appTilePlacements([{ id: 'files', label: 'Files', pinned: true }], 9)
		expect(tile.type).toBe('tile')
		expect(tile.uuid).toBe('9-app-files')
		expect(tile.content.mode).toBe('app')
		expect(tile.content.appId).toBe('files')
		expect(tile.content.pinned).toBe(true)
		expect(childLabel(tile, menu)).toBe('Files')
	})
})
