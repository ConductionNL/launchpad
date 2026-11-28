/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Which renderers the card waits for (first-paint-skeleton REQ-FPS-004).
 *
 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-each-widget-fills-its-own-frame-when-its-data-arrives-req-fps-004
 */

import { describe, expect, it } from 'vitest'
import { rendererEmitsLoaded } from '../widgetLoaded.js'

describe('rendererEmitsLoaded', () => {
	it('is true for a renderer that declares loaded, as a list or an object', () => {
		expect(rendererEmitsLoaded({ emits: ['status', 'loaded'] })).toBe(true)
		expect(rendererEmitsLoaded({ emits: { loaded: null } })).toBe(true)
		expect(rendererEmitsLoaded({ options: { emits: ['loaded'] } })).toBe(true)
	})

	it('is false for a renderer without it, or without a renderer', () => {
		expect(rendererEmitsLoaded({ emits: ['status'] })).toBe(false)
		expect(rendererEmitsLoaded({ emits: { status: null } })).toBe(false)
		expect(rendererEmitsLoaded({})).toBe(false)
		expect(rendererEmitsLoaded(null)).toBe(false)
		expect(rendererEmitsLoaded(undefined)).toBe(false)
	})
})
