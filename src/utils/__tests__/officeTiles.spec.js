/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * integrations-office-tiles: the pack becomes one container of 2 by 2 tiles,
 * two per row, with app tiles linking to the app's own page.
 *
 * @spec openspec/changes/integrations-office-tiles/specs/office-tiles/spec.md
 */

import { describe, expect, it } from 'vitest'
import { officePackContainer } from '../officeTiles.js'

const DOC = { title: 'New document', linkType: 'create', linkValue: 'richdocuments:.docx', icon: 'icon-filetype-file', iconType: 'class' }
const SHEET = { title: 'New spreadsheet', linkType: 'create', linkValue: 'richdocuments:.xlsx', icon: 'icon-filetype-file', iconType: 'class' }
const FILES = { title: 'Files', linkType: 'app', linkValue: 'files', icon: 'icon-files', iconType: 'class' }

describe('officePackContainer', () => {
	it('places the tiles two per row in 2 by 2 cells', () => {
		const { content } = officePackContainer([DOC, SHEET, FILES], 'Office')

		expect(content.title).toBe('Office')
		expect(content.placements.map((p) => [p.gridX, p.gridY, p.gridWidth, p.gridHeight])).toEqual([
			[0, 0, 2, 2],
			[2, 0, 2, 2],
			[0, 2, 2, 2],
		])
		expect(content.placements.every((p) => p.type === 'tile')).toBe(true)
	})

	it('keeps a create tile on its creator and links an app tile to its own page', () => {
		const [doc, files] = officePackContainer([DOC, FILES], 'Office').content.placements

		expect(doc.content).toMatchObject({ title: 'New document', linkType: 'create', linkValue: 'richdocuments:.docx' })
		expect(files.content).toMatchObject({ title: 'Files', linkType: 'app', linkValue: '/apps/files/', icon: 'icon-files' })
	})

	it('sizes the container to its rows plus the title row', () => {
		expect(officePackContainer([DOC, SHEET, FILES], 'Office').size).toEqual({ w: 4, h: 5 })
		expect(officePackContainer([DOC], 'Office').size).toEqual({ w: 4, h: 3 })
		expect(officePackContainer([], 'Office').size).toEqual({ w: 4, h: 3 })
	})
})
