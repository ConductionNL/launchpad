/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The grid's first layout lands in place (first-paint-skeleton REQ-FPS-003):
 * GridStack's slide stays off until two frames after the hand-over, then
 * comes back, once per page.
 *
 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-page-takes-over-without-a-layout-shift-req-fps-003
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn(), showSuccess: vi.fn() }))

let Views

beforeEach(async () => {
	Views = (await import('../Views.vue')).default
})

afterEach(() => {
	vi.unstubAllGlobals()
})

describe('Views grid settle', () => {
	it('starts settling, so the grid does not slide before it is on screen', () => {
		expect(Views.data.call({ $route: { params: {} } }).gridSettled).toBe(false)
	})

	it('lets the grid slide again two frames after the hand-over, once', () => {
		const frames = []
		vi.stubGlobal('requestAnimationFrame', (fn) => frames.push(fn))
		const host = { gridSettled: false }
		Views.methods.settleGridAfterHandOver.call(host)
		frames.shift()()
		expect(host.gridSettled).toBe(false)
		frames.shift()()
		expect(host.gridSettled).toBe(true)

		Views.methods.settleGridAfterHandOver.call(host)
		expect(frames).toHaveLength(0)
	})

	it('settles after the hand-over when the grid mounts', () => {
		const host = { startStatusLine: vi.fn(), settleGridAfterHandOver: vi.fn() }
		Views.methods.onGridMounted.call(host)
		expect(host.startStatusLine).toHaveBeenCalledOnce()
		expect(host.settleGridAfterHandOver).toHaveBeenCalledOnce()
	})
})
