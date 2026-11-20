/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Options for a group picker built on `NcSelectTags` with `fetchTags` off.
 * `NcSelectTags` labels an option by `displayName` and stores its `id`, so
 * plain group ids and `{id, displayName}` groups are both turned into that
 * shape. Selected ids missing from the list are kept as options, so a stored
 * rule still shows its groups when the full list cannot be read.
 */

/**
 * Turn group ids or `{id, displayName}` groups into picker options.
 *
 * @param {Array<string|object>} groups the offered groups.
 * @param {string[]} selected the group ids already chosen.
 * @return {Array<{id: string, displayName: string}>} one option per distinct id.
 * @spec openspec/specs/conditional-visibility-editor/spec.md
 */
export function toGroupOptions(groups, selected = []) {
	const options = new Map()
	for (const group of Array.isArray(groups) ? groups : []) {
		const id = typeof group === 'string' ? group : String(group?.id ?? '')
		if (id !== '' && !options.has(id)) {
			const name = typeof group === 'string' ? '' : String(group?.displayName ?? '')
			options.set(id, { id, displayName: name !== '' ? name : id })
		}
	}
	for (const id of Array.isArray(selected) ? selected : []) {
		if (typeof id === 'string' && id !== '' && !options.has(id)) {
			options.set(id, { id, displayName: id })
		}
	}
	return [...options.values()]
}
