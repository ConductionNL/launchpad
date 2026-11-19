/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * How a tile opens: the tile's choice, the dashboard default, then the
 * link-type rule (launcher-tile-open-behaviour D2).
 *
 * @spec openspec/changes/launcher-tile-open-behaviour/specs/tiles/spec.md
 */

import { describe, expect, it } from 'vitest'
import { isLinkTarget, isSameOrigin, LINK_TARGETS, resolveLinkTarget } from '../tileLinkTarget.js'

describe('tileLinkTarget', () => {
	it('knows the three targets', () => {
		expect(LINK_TARGETS).toEqual(['same-tab', 'new-tab', 'side-panel'])
		expect(LINK_TARGETS.map(isLinkTarget)).toEqual([true, true, true])
		expect(['', null, undefined, '_blank'].map(isLinkTarget)).toEqual([false, false, false, false])
	})

	it('resolves the tile first, then the dashboard, then the link type', () => {
		expect(resolveLinkTarget('side-panel', 'same-tab', 'url')).toBe('side-panel')
		expect(resolveLinkTarget(null, 'new-tab', 'app')).toBe('new-tab')
		expect(resolveLinkTarget('', 'bogus', 'url')).toBe('new-tab')
		expect(resolveLinkTarget(null, null, 'app')).toBe('same-tab')
		expect(resolveLinkTarget(undefined, undefined, 'internal')).toBe('same-tab')
	})

	it('tells an address on this Nextcloud from an outside one', () => {
		const origin = 'https://cloud.example'
		expect(isSameOrigin('/nc/apps/files', origin)).toBe(true)
		expect(isSameOrigin('https://cloud.example/apps/deck', origin)).toBe(true)
		expect(isSameOrigin('https://other.example/', origin)).toBe(false)
		expect(isSameOrigin('http://[bad', origin)).toBe(false)
	})
})
