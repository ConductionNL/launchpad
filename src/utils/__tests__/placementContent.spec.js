/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

import { describe, expect, it } from 'vitest'
import { placementContent } from '../placementContent.js'

describe('placementContent', () => {
	it('reads the placement content as an object, empty when unset', () => {
		expect(placementContent({ widgetId: 'text', content: { a: 1 } })).toEqual({ a: 1 })
		expect(placementContent({ widgetId: 'text', content: null })).toEqual({})
		expect(placementContent(undefined)).toEqual({})
	})

	it('turns files-widget upload off when the placement never set it', () => {
		expect(placementContent({ widgetId: 'files', content: { folderPath: '/a' } })).toEqual({ folderPath: '/a', allowUpload: false })
		expect(placementContent({ widgetId: 'files', content: null })).toEqual({ allowUpload: false })
	})

	it('keeps an upload choice the author made', () => {
		expect(placementContent({ widgetId: 'files', content: { allowUpload: true } })).toEqual({ allowUpload: true })
		expect(placementContent({ widgetId: 'files', content: { allowUpload: false } })).toEqual({ allowUpload: false })
	})
})
