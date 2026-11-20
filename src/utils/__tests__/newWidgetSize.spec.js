/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * header-widget REQ-HDR-009: default grid sizing for a header widget.
 */

import { describe, expect, it } from 'vitest'
import { newWidgetSize } from '../newWidgetSize.js'

describe('newWidgetSize', () => {
	it('a header spans the full grid and is sized for the medium preset by default', () => {
		expect(newWidgetSize({ type: 'header', content: {} }, 12, 60)).toEqual({ w: 12, h: 4 })
	})

	it('a header follows its chosen preset and the dashboard column count', () => {
		expect(newWidgetSize({ type: 'header', content: { height: 'small' } }, 12, 60)).toEqual({ w: 12, h: 2 })
		expect(newWidgetSize({ type: 'header', content: { height: 'xlarge' } }, 24, 60)).toEqual({ w: 24, h: 8 })
	})

	it('other widget types keep the default size', () => {
		expect(newWidgetSize({ type: 'text', content: {} }, 12, 60)).toBeNull()
	})
})
