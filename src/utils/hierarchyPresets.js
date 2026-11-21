/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The hierarchy presets a dashboard owner picks from (drill-down-cross-widget-filter,
 * REQ-CWF-011). A preset is configuration: its id, its name and its levels from the
 * broadest to the finest. The dashboard stores the descriptor (`{id, levels}`); the
 * labels it stores are the English source strings, and a reader sees the preset's
 * labels in their own language.
 *
 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-011-a-dashboard-stores-its-hierarchies
 */

import { t } from '@nextcloud/l10n'

/** The id of the Dutch administrative areas preset. */
export const NL_ADMINISTRATIVE = 'nl-administrative'

/**
 * The preset table: per level its dimension, the stored label and the translated one.
 *
 * @return {Array<{id: string, name: string, description: string, levels: Array<{dimension: string, label: string, displayLabel: string}>}>}
 */
function presetTable() {
	return [
		{
			id: NL_ADMINISTRATIVE,
			name: t('launchpad', 'Dutch administrative areas'),
			description: t('launchpad', 'Province, municipality, district and neighbourhood'),
			levels: [
				{ dimension: 'provincieCode', label: 'Province', displayLabel: t('launchpad', 'Province') },
				{ dimension: 'gemeenteCode', label: 'Municipality', displayLabel: t('launchpad', 'Municipality') },
				{ dimension: 'wijkCode', label: 'District', displayLabel: t('launchpad', 'District') },
				{ dimension: 'buurtCode', label: 'Neighbourhood', displayLabel: t('launchpad', 'Neighbourhood') },
			],
		},
	]
}

/**
 * The presets an owner can pick, as `{id, name, description, descriptor}`.
 *
 * @return {Array<{id: string, name: string, description: string, descriptor: object}>}
 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-011-a-dashboard-stores-its-hierarchies
 */
export function hierarchyPresets() {
	return presetTable().map((preset) => ({
		id: preset.id,
		name: preset.name,
		description: preset.description,
		descriptor: {
			id: preset.id,
			levels: preset.levels.map((level, index) => ({
				dimension: level.dimension,
				label: level.label,
				parentDimension: index === 0 ? null : preset.levels[index - 1].dimension,
			})),
		},
	}))
}

/**
 * The translated level labels of a preset, by dimension, or null for no preset.
 *
 * @param {string} id A hierarchy id.
 * @return {{[dimension: string]: string}|null}
 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-011-a-dashboard-stores-its-hierarchies
 */
export function presetLevelLabels(id) {
	const preset = presetTable().find((entry) => entry.id === id)
	if (!preset) {
		return null
	}
	return Object.fromEntries(preset.levels.map((level) => [level.dimension, level.displayLabel]))
}
