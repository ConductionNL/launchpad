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
 * One group id or `{id, displayName}` group as a picker option.
 *
 * @param {string|object} group the group.
 * @return {{id: string, displayName: string}} the option; its id is '' when the group has none.
 * @spec openspec/specs/conditional-visibility-editor/spec.md
 */
function toOption(group) {
	if (typeof group === 'string') {
		return { id: group, displayName: group }
	}
	const id = String(group?.id ?? '')
	const name = String(group?.displayName ?? '')
	return { id, displayName: name !== '' ? name : id }
}

/**
 * Turn group ids or `{id, displayName}` groups into picker options.
 *
 * @param {Array<string|object>} groups the offered groups.
 * @param {string[]} selected the group ids already chosen.
 * @return {Array<{id: string, displayName: string}>} one option per distinct id.
 * @spec openspec/specs/conditional-visibility-editor/spec.md
 */
export function toGroupOptions(groups, selected = []) {
	const chosen = (Array.isArray(selected) ? selected : []).filter((id) => typeof id === 'string')
	const options = new Map()
	for (const option of [...(Array.isArray(groups) ? groups : []), ...chosen].map(toOption)) {
		if (option.id !== '' && !options.has(option.id)) {
			options.set(option.id, option)
		}
	}
	return [...options.values()]
}
