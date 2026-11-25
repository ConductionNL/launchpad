// SPDX-License-Identifier: EUPL-1.2
// SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>

/**
 * The theme colours of a tile (icon-only-app-tiles REQ-ICT-004).
 */

import { describe, expect, it } from 'vitest'
import { isColourToken, TILE_COLOUR_TOKENS, tileBackground } from '../tileColour.js'

describe('tileBackground', () => {
	it('renders through a known token with its fallback', () => {
		expect(tileBackground({ backgroundColor: '#123456' }, { colourToken: '--sc-primary' })).toBe('var(--sc-primary, #3669a5)')
	})

	it('falls back to the primary colour for a token the palette does not know', () => {
		expect(tileBackground(null, { colourToken: '--sc-brand-new' })).toBe('var(--sc-brand-new, var(--sc-primary, #3669a5))')
	})

	it('keeps a hex colour, and uses the primary colour without one', () => {
		expect(tileBackground({ backgroundColor: '#123456' }, {})).toBe('#123456')
		expect(tileBackground({ backgroundColor: '#123456' }, { colourToken: 'red' })).toBe('#123456')
		expect(tileBackground(null, null)).toBe('var(--sc-primary, #3669a5)')
	})

	it('has eight tokens', () => {
		expect(TILE_COLOUR_TOKENS.map((entry) => entry.token)).toHaveLength(8)
		expect(isColourToken('--sc-work-ink')).toBe(true)
		expect(isColourToken('--other')).toBe(false)
	})
})
