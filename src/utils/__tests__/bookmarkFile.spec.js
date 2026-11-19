/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The bookmarks file parser on Firefox, Chrome and Edge exports, with nested
 * folders and non-web links, and its size and count limits (REQ-BMI-001,
 * REQ-BMI-004).
 *
 * @spec openspec/changes/launcher-bookmark-import/specs/tiles/spec.md
 */
import { readFileSync } from 'fs'
import path from 'path'
import { describe, expect, it } from 'vitest'
import { chosenBookmarks, MAX_BOOKMARKS, MAX_FILE_BYTES, parseBookmarks, readBookmarkFile } from '../bookmarkFile.js'

const fixture = (name) => readFileSync(path.join(__dirname, 'fixtures', name), 'utf8')

describe('parseBookmarks', () => {
	it('reads a Firefox export: loose bookmarks, folders, nested folders flattened in', () => {
		const parsed = parseBookmarks(fixture('bookmarks-firefox.html'))
		expect(parsed.bookmarks).toEqual([
			{ title: 'Mozilla', url: 'https://www.mozilla.org/' },
			{ title: 'Bookmarklet', url: 'javascript:alert(1)' },
		])
		expect(parsed.folders).toEqual([
			{
				name: 'Werk',
				bookmarks: [
					{ title: 'Wiki', url: 'https://wiki.example.org/' },
					{ title: 'Mail & agenda', url: 'https://mail.example.org/' },
					{ title: 'Planning', url: 'https://plan.example.org/' },
				],
			},
			{ name: 'Bookmarks Toolbar', bookmarks: [{ title: 'News', url: 'https://news.example.org/' }] },
		])
		expect(parsed.total).toBe(6)
	})

	it('reads a Chrome export, keeping a file link for the server to skip and an empty folder', () => {
		const parsed = parseBookmarks(fixture('bookmarks-chrome.html'))
		expect(parsed.bookmarks).toEqual([])
		expect(parsed.folders.map((f) => [f.name, f.bookmarks.map((b) => b.url)])).toEqual([
			['Bookmarks bar', ['https://www.google.com/', 'http://bank.example.nl/', 'file:///C:/Users/pieter/notes.txt']],
			['Other bookmarks', []],
		])
		expect(parsed.total).toBe(3)
	})

	it('reads an Edge export and titles an untitled bookmark with its address', () => {
		const parsed = parseBookmarks(fixture('bookmarks-edge.html'))
		expect(parsed.folders).toEqual([
			{
				name: 'Favorites bar',
				bookmarks: [
					{ title: 'Bing', url: 'https://www.bing.com/' },
					{ title: 'https://intranet.gemeente.nl/', url: 'https://intranet.gemeente.nl/' },
				],
			},
		])
		expect(parsed.bookmarks).toEqual([{ title: 'Outlook', url: 'https://outlook.office.com/' }])
	})

	it('returns nothing for a file that is not a bookmarks file', () => {
		expect(parseBookmarks('<html><body>hello</body></html>')).toEqual({ folders: [], bookmarks: [], total: 0 })
		expect(parseBookmarks('')).toEqual({ folders: [], bookmarks: [], total: 0 })
	})
})

describe('readBookmarkFile', () => {
	const file = (text, size) => ({ size: size ?? text.length, text: async () => text })

	it('refuses a file over 5 MB before reading it', async () => {
		const big = { size: MAX_FILE_BYTES + 1, text: () => { throw new Error('read') } }
		await expect(readBookmarkFile(big)).rejects.toMatchObject({ code: 'too-large' })
	})

	it('refuses more than 2,000 bookmarks and accepts exactly 2,000', async () => {
		const many = (n) => '<DL><p>' + Array.from({ length: n }, (_v, i) => `<DT><A HREF="https://e.org/${i}">${i}</A>`).join('') + '</DL>'
		await expect(readBookmarkFile(file(many(MAX_BOOKMARKS + 1)))).rejects.toMatchObject({ code: 'too-many' })
		await expect(readBookmarkFile(file(many(MAX_BOOKMARKS)))).resolves.toMatchObject({ total: MAX_BOOKMARKS })
	})
})

describe('chosenBookmarks', () => {
	it('sends only the ticked folders and loose bookmarks', () => {
		const parsed = parseBookmarks(fixture('bookmarks-firefox.html'))
		expect(chosenBookmarks(parsed, new Set([0]), new Set([1]))).toEqual({
			folders: [parsed.folders[0]],
			bookmarks: [{ title: 'Bookmarklet', url: 'javascript:alert(1)' }],
		})
	})
})
