/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Parse the bookmarks file browsers export (the Netscape bookmark HTML that
 * Firefox, Chrome and Edge all write) in the browser (launcher-bookmark-import,
 * REQ-BMI-001, REQ-BMI-004). Nothing is uploaded: the result is a clean list
 * the person picks from.
 *
 * Folders: every bookmark belongs to its outermost folder, so nested folders
 * are flattened into their top-level folder. The browsers' own root folders
 * (the bookmarks toolbar and "other bookmarks", marked PERSONAL_TOOLBAR_FOLDER
 * or UNFILED_BOOKMARKS_FOLDER) are transparent: the folders inside them are
 * top-level, and their loose bookmarks are loose.
 */

export const MAX_BOOKMARK_FILE_BYTES = 5 * 1024 * 1024

export const MAX_BOOKMARKS = 2000

/**
 * Error thrown when a file is over a limit; `code` is `too-large` or `too-many`.
 */
export class BookmarkFileError extends Error {
	/**
	 * @param {string} code `too-large` or `too-many`.
	 * @spec openspec/specs/tiles/spec.md
	 */
	constructor(code) {
		super(code)
		this.code = code
	}
}

/**
 * Whether an H3 folder heading is a browser root folder.
 *
 * @param {Element} heading The H3.
 * @return {boolean} True for the toolbar and unfiled roots.
 */
function isTransparentFolder(heading) {
	return (
		heading.hasAttribute('personal_toolbar_folder')
		|| heading.hasAttribute('unfiled_bookmarks_folder')
	)
}

/**
 * The folder heading a DT carries as a direct child, or null.
 *
 * @param {Element} dt A DT element.
 * @return {Element|null} Its H3.
 */
function headingOf(dt) {
	return Array.from(dt.children).find((child) => child.tagName === 'H3') || null
}

/**
 * The name of the outermost real folder that holds an anchor, or null.
 *
 * @param {Element} anchor An A element.
 * @param {Element} root The file's top DL.
 * @return {string|null} The folder name.
 */
function outermostFolder(anchor, root) {
	let folder = null
	for (
		let node = anchor.parentElement;
		node && node !== root;
		node = node.parentElement
	) {
		if (node.tagName !== 'DT') {
			continue
		}
		const heading = headingOf(node)
		if (heading && !isTransparentFolder(heading)) {
			folder = (heading.textContent || '').trim()
		}
	}
	return folder
}

/**
 * Parse a bookmarks file.
 *
 * @param {string} html The file's text.
 * @param {number} [maxBookmarks] Most bookmarks allowed.
 * @return {{folders: Array<{name: string, bookmarks: Array<{title: string, url: string}>}>, bookmarks: Array<{title: string, url: string}>, total: number}}
 *   The folders in file order, the loose bookmarks, and the count.
 * @throws {BookmarkFileError} `too-many` when over the limit.
 * @spec openspec/specs/tiles/spec.md
 */
export function parseBookmarksHtml(html, maxBookmarks = MAX_BOOKMARKS) {
	const doc = new DOMParser().parseFromString(String(html || ''), 'text/html')
	const root = doc.querySelector('dl') || doc.body
	const anchors = Array.from(root.querySelectorAll('a[href]'))
	if (anchors.length > maxBookmarks) {
		throw new BookmarkFileError('too-many')
	}

	const folders = new Map()
	const loose = []
	anchors.forEach((anchor) => {
		const bookmark = {
			title: (anchor.textContent || '').trim(),
			url: (anchor.getAttribute('href') || '').trim(),
		}
		const name = outermostFolder(anchor, root)
		if (name === null) {
			loose.push(bookmark)
			return
		}
		if (!folders.has(name)) {
			folders.set(name, [])
		}
		folders.get(name).push(bookmark)
	})

	return {
		folders: Array.from(folders, ([name, bookmarks]) => ({ name, bookmarks })),
		bookmarks: loose,
		total: anchors.length,
	}
}

/**
 * Read and parse a chosen file, refusing one over 5 MB before reading it.
 *
 * @param {File} file The chosen file.
 * @return {Promise<object>} The parsed result, see {@link parseBookmarksHtml}.
 * @throws {BookmarkFileError} `too-large` or `too-many`.
 * @spec openspec/specs/tiles/spec.md
 */
export async function readBookmarksFile(file) {
	if (file.size > MAX_BOOKMARK_FILE_BYTES) {
		throw new BookmarkFileError('too-large')
	}
	return parseBookmarksHtml(await file.text())
}
