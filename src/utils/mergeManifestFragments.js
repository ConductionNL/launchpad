/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * ADR-037: modular manifest fragment merge helper.
 *
 * Each OpenSpec change drops its own fragment (pages/menu) under src/manifest.d/
 * instead of editing the monolith src/manifest.json, so concurrent same-app
 * builds touch disjoint files and never conflict on the shared manifest.
 *
 * The pure `applyManifestFragments` performs the array concatenation and is
 * unit-testable; `mergeManifestFragments` wires it to webpack's build-time
 * `require.context` and is the function the entry point calls.
 */

/**
 * Apply a list of manifest fragments onto the bundled base manifest.
 *
 * `pages` and `menu` arrays are concatenated; fragments are applied in the given
 * order. The base is shallow-cloned so the input object is never mutated.
 *
 * @param {object} base The bundled base manifest.
 * @param {Array<object>} fragments Parsed fragment objects (each may have pages/menu).
 * @return {object} The manifest with all fragment pages/menu appended.
 */
export function applyManifestFragments(base, fragments) {
	const merged = { ...base, pages: [...(base.pages || [])], menu: [...(base.menu || [])] }
	fragments.forEach((frag) => {
		if (frag && Array.isArray(frag.pages)) {
			merged.pages.push(...frag.pages)
		}
		if (frag && Array.isArray(frag.menu)) {
			merged.menu.push(...frag.menu)
		}
	})
	return merged
}

/**
 * Merge modular manifest fragments from src/manifest.d/*.json onto the bundled
 * base manifest via webpack's build-time `require.context`.
 *
 * The directory always exists (it ships with a `_placeholder.json` whose empty
 * pages/menu make this a no-op until a real fragment is added).
 *
 * @param {object} base The bundled base manifest.
 * @return {object} The manifest with all fragment pages/menu appended.
 */
export function mergeManifestFragments(base) {
	const ctx = require.context('./../manifest.d/', false, /\.json$/)
	const fragments = ctx.keys().sort().map((key) => ctx(key))
	return applyManifestFragments(base, fragments)
}
