/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The one panel beside the tiles (launcher-tile-open-behaviour D3).
 *
 * @spec openspec/changes/launcher-tile-open-behaviour/specs/tiles/spec.md
 */

import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useTilePanelStore } from '../tilePanel.js'

describe('tilePanel store', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
	})

	it('opening another tile replaces the panel, and closing returns focus to the last opener', () => {
		const first = { focus: vi.fn() }
		const second = { focus: vi.fn() }
		const panel = useTilePanelStore()

		panel.open({ url: '/a', title: 'A', sameOrigin: true, opener: first })
		panel.open({ url: 'https://b.example', title: 'B', opener: second })

		expect([panel.isOpen, panel.url, panel.title, panel.sameOrigin]).toEqual([true, 'https://b.example', 'B', false])

		panel.close()

		expect([panel.isOpen, panel.url, panel.title, panel.opener]).toEqual([false, null, '', null])
		expect(second.focus).toHaveBeenCalledTimes(1)
		expect(first.focus).not.toHaveBeenCalled()
	})

	it('closes without an opener', () => {
		const panel = useTilePanelStore()
		panel.open({ url: '/a' })
		panel.close()

		expect(panel.isOpen).toBe(false)
	})
})
