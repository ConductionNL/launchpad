/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `parseBookmarks.js` (REQ-BMI-001, REQ-BMI-004) on the
 * shapes Firefox, Chrome and Edge export.
 */

import { describe, expect, it } from 'vitest'
import {
	BookmarkFileError,
	MAX_BOOKMARK_FILE_BYTES,
	parseBookmarksHtml,
	readBookmarksFile,
} from '../parseBookmarks.js'

const HEAD =
	'<!DOCTYPE NETSCAPE-Bookmark-file-1>\n<META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=UTF-8">\n<TITLE>Bookmarks</TITLE>\n<H1>Bookmarks</H1>\n'

const FIREFOX =
	HEAD
	+ `<DL><p>
    <DT><A HREF="https://www.mozilla.org/" ADD_DATE="1">Mozilla</A>
    <DT><H3 ADD_DATE="1" PERSONAL_TOOLBAR_FOLDER="true">Bookmarks Toolbar</H3>
    <DL><p>
        <DT><A HREF="https://intranet.gemeente.nl/">Intranet</A>
        <DT><H3>Werk</H3>
        <DL><p>
            <DT><A HREF="https://zaken.gemeente.nl/">Zaaksysteem</A>
            <DT><H3>Archief</H3>
            <DL><p>
                <DT><A HREF="https://archief.gemeente.nl/">Archief</A>
            </DL><p>
        </DL><p>
    </DL><p>
    <DT><H3 UNFILED_BOOKMARKS_FOLDER="true">Other Bookmarks</H3>
    <DL><p>
        <DT><H3>Privé</H3>
        <DL><p>
            <DT><A HREF="https://nos.nl/">NOS</A>
            <DT><A HREF="javascript:alert(1)">Bookmarklet</A>
        </DL><p>
    </DL><p>
</DL><p>`

const CHROME =
	HEAD
	+ `<DL><p>
    <DT><H3 ADD_DATE="1" LAST_MODIFIED="1" PERSONAL_TOOLBAR_FOLDER="true">Bookmarks bar</H3>
    <DL><p>
        <DT><A HREF="https://mail.google.com/" ADD_DATE="1" ICON="data:image/png;base64,AAA">Gmail</A>
        <DT><H3 ADD_DATE="1">Werk</H3>
        <DL><p>
            <DT><A HREF="https://topdesk.gemeente.nl/">TOPdesk</A>
        </DL><p>
    </DL><p>
    <DT><H3>Onderzoek</H3>
    <DL><p>
        <DT><A HREF="https://scholar.google.com/">Scholar</A>
    </DL><p>
</DL><p>`

const EDGE =
	HEAD
	+ `<DL><p>
    <DT><H3 PERSONAL_TOOLBAR_FOLDER="true">Favorites bar</H3>
    <DL><p>
        <DT><H3>Gemeente</H3>
        <DL><p>
            <DT><A HREF="https://www.gemeente.nl/">Website</A>
            <DT><A HREF="https://raad.gemeente.nl/">Raad</A>
        </DL><p>
    </DL><p>
</DL><p>`

describe('parseBookmarksHtml', () => {
	it('Firefox: toolbar and other bookmarks are transparent, nested folders flatten into their top folder', () => {
		const result = parseBookmarksHtml(FIREFOX)
		expect(result.total).toBe(6)
		expect(result.bookmarks.map((b) => b.title)).toEqual(['Mozilla', 'Intranet'])
		expect(result.folders).toEqual([
			{
				name: 'Werk',
				bookmarks: [
					{ title: 'Zaaksysteem', url: 'https://zaken.gemeente.nl/' },
					{ title: 'Archief', url: 'https://archief.gemeente.nl/' },
				],
			},
			{
				name: 'Privé',
				bookmarks: [
					{ title: 'NOS', url: 'https://nos.nl/' },
					{ title: 'Bookmarklet', url: 'javascript:alert(1)' },
				],
			},
		])
	})

	it('Chrome: the bookmarks bar is transparent and a root folder stays a folder', () => {
		const result = parseBookmarksHtml(CHROME)
		expect(result.bookmarks).toEqual([
			{ title: 'Gmail', url: 'https://mail.google.com/' },
		])
		expect(result.folders.map((f) => [f.name, f.bookmarks.length])).toEqual([
			['Werk', 1],
			['Onderzoek', 1],
		])
	})

	it('Edge: the favorites bar is transparent', () => {
		const result = parseBookmarksHtml(EDGE)
		expect(result.bookmarks).toEqual([])
		expect(result.folders).toEqual([
			{
				name: 'Gemeente',
				bookmarks: [
					{ title: 'Website', url: 'https://www.gemeente.nl/' },
					{ title: 'Raad', url: 'https://raad.gemeente.nl/' },
				],
			},
		])
	})

	it('REQ-BMI-004: more than the limit is refused', () => {
		expect(() => parseBookmarksHtml(CHROME, 2)).toThrow(BookmarkFileError)
		try {
			parseBookmarksHtml(CHROME, 2)
		} catch (error) {
			expect(error.code).toBe('too-many')
		}
	})

	it('REQ-BMI-004: a file over 5 MB is refused before it is read', async () => {
		let read = false
		const file = {
			size: MAX_BOOKMARK_FILE_BYTES + 1,
			text: () => {
				read = true
				return Promise.resolve('')
			},
		}
		await expect(readBookmarksFile(file)).rejects.toMatchObject({
			code: 'too-large',
		})
		expect(read).toBe(false)
	})

	it('an empty or odd file gives no bookmarks', () => {
		expect(parseBookmarksHtml('')).toEqual({
			folders: [],
			bookmarks: [],
			total: 0,
		})
		expect(parseBookmarksHtml('<p>not bookmarks</p>').total).toBe(0)
	})
})
