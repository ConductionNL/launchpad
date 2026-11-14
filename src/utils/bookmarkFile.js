/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Reads the bookmarks file a browser exports (the Netscape bookmark HTML that
 * Firefox, Chrome and Edge all write) in the browser, so the raw file never
 * reaches the server (launcher-bookmark-import D1).
 *
 * The result is `{folders: [{name, bookmarks: [{title, url}]}], bookmarks:
 * [{title, url}], total}`: each top-level folder with every bookmark under it,
 * nested folders flattened in (D4), and the loose top-level bookmarks. Nothing
 * is filtered here; the server decides which addresses may become tiles and
 * lists the rest back.
 *
 * @spec openspec/changes/launcher-bookmark-import/specs/tiles/spec.md
 */

/** The largest file read, in bytes (REQ-BMI-004). */
export const MAX_FILE_BYTES = 5 * 1024 * 1024

/** The most bookmarks one import may carry (REQ-BMI-004). */
export const MAX_BOOKMARKS = 2000

/**
 * The element children of a node with the given tag name.
 *
 * @param {Element} node - the parent.
 * @param {string} tag - the upper-case tag name.
 * @return {Element[]}
 */
function childrenNamed(node, tag) {
	return Array.from(node.children).filter((child) => child.tagName === tag)
}

/**
 * One bookmark from an anchor.
 *
 * @param {Element} anchor - an `<A HREF>` element.
 * @return {{title: string, url: string}}
 */
function bookmarkOf(anchor) {
	const url = (anchor.getAttribute('href') || '').trim()
	const title = (anchor.textContent || '').trim()
	return { title: title || url, url }
}

/**
 * The list of a folder entry: the `<DL>` inside its `<DT>`, or the one right
 * after it when the parser left it as a sibling.
 *
 * @param {Element} entry - the folder's `<DT>`.
 * @return {Element|null}
 */
function listOf(entry) {
	const inside = childrenNamed(entry, 'DL')[0]
	if (inside) {
		return inside
	}
	let next = entry.nextElementSibling
	while (next && next.tagName === 'P') {
		next = next.nextElementSibling
	}
	return next && next.tagName === 'DL' ? next : null
}

/**
 * The entries of a list. The HTML parser keeps `<DT>` under its `<DL>`, but a
 * stray `<P>` can wrap one; those are looked into as well.
 *
 * @param {Element} list - a `<DL>`.
 * @return {Element[]}
 */
function entriesOf(list) {
	const entries = []
	for (const child of Array.from(list.children)) {
		if (child.tagName === 'DT') {
			entries.push(child)
		} else if (child.tagName === 'P') {
			entries.push(...childrenNamed(child, 'DT'))
		}
	}
	return entries
}

/**
 * Parse a bookmarks file into its top-level folders and loose bookmarks.
 *
 * @param {string} html - the file's text.
 * @param {typeof DOMParser} [Parser] - the parser (the browser's by default).
 * @return {{folders: Array<{name: string, bookmarks: Array<{title: string, url: string}>}>, bookmarks: Array<{title: string, url: string}>, total: number}}
 * @spec openspec/changes/launcher-bookmark-import/specs/tiles/spec.md#requirement-users-can-import-a-browser-bookmarks-file-as-tiles-req-bmi-001
 */
export function parseBookmarks(html, Parser = globalThis.DOMParser) {
	const doc = new Parser().parseFromString(String(html || ''), 'text/html')
	const root = doc.querySelector('dl')
	const folders = []
	const bookmarks = []
	if (!root) {
		return { folders, bookmarks, total: 0 }
	}

	for (const entry of entriesOf(root)) {
		const heading = childrenNamed(entry, 'H3')[0]
		if (heading) {
			const list = listOf(entry)
			const inner = list ? Array.from(list.querySelectorAll('a[href]')).map(bookmarkOf) : []
			folders.push({ name: (heading.textContent || '').trim(), bookmarks: inner })
			continue
		}
		const anchor = childrenNamed(entry, 'A')[0]
		if (anchor && anchor.hasAttribute('href')) {
			bookmarks.push(bookmarkOf(anchor))
		}
	}

	const total = bookmarks.length + folders.reduce((sum, folder) => sum + folder.bookmarks.length, 0)
	return { folders, bookmarks, total }
}

/**
 * Read a chosen file, refusing one over 5 MB or over 2,000 bookmarks before
 * anything is sent (REQ-BMI-004). The refusal is an Error whose `code` is
 * `too-large` or `too-many`.
 *
 * @param {File|Blob} file - the chosen file.
 * @param {typeof DOMParser} [Parser] - the parser (the browser's by default).
 * @return {Promise<ReturnType<typeof parseBookmarks>>}
 * @spec openspec/changes/launcher-bookmark-import/specs/tiles/spec.md#requirement-large-files-are-refused-in-the-browser-req-bmi-004
 */
export async function readBookmarkFile(file, Parser = globalThis.DOMParser) {
	if (file.size > MAX_FILE_BYTES) {
		throw Object.assign(new Error('too-large'), { code: 'too-large' })
	}
	const parsed = parseBookmarks(await file.text(), Parser)
	if (parsed.total > MAX_BOOKMARKS) {
		throw Object.assign(new Error('too-many'), { code: 'too-many' })
	}
	return parsed
}

/**
 * The request body for the chosen folders and loose bookmarks.
 *
 * @param {ReturnType<typeof parseBookmarks>} parsed - the parsed file.
 * @param {Set<number>} folderIndexes - the chosen folders.
 * @param {Set<number>} bookmarkIndexes - the chosen loose bookmarks.
 * @return {{folders: Array<{name: string, bookmarks: Array<{title: string, url: string}>}>, bookmarks: Array<{title: string, url: string}>}}
 * @spec openspec/changes/launcher-bookmark-import/specs/tiles/spec.md#requirement-users-can-import-a-browser-bookmarks-file-as-tiles-req-bmi-001
 */
export function chosenBookmarks(parsed, folderIndexes, bookmarkIndexes) {
	return {
		folders: parsed.folders.filter((_folder, index) => folderIndexes.has(index)),
		bookmarks: parsed.bookmarks.filter((_bookmark, index) => bookmarkIndexes.has(index)),
	}
}
