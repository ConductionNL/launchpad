/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The hierarchy presets a dashboard owner picks from (REQ-CWF-011).
 *
 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-011-a-dashboard-stores-its-hierarchies
 */

import { describe, expect, it, vi } from 'vitest'
import { hierarchyPresets, NL_ADMINISTRATIVE, presetLevelLabels } from '../hierarchyPresets.js'

vi.mock('@nextcloud/l10n', () => ({ t: (_app, key) => `nl:${key}` }))

describe('hierarchyPresets', () => {
	it('ships nl-administrative as province > municipality > district > neighbourhood, each level under the one above', () => {
		const preset = hierarchyPresets().find((entry) => entry.id === NL_ADMINISTRATIVE)

		expect(preset.name).toBe('nl:Dutch administrative areas')
		expect(preset.descriptor).toEqual({
			id: 'nl-administrative',
			levels: [
				{ dimension: 'provincieCode', label: 'Province', parentDimension: null },
				{ dimension: 'gemeenteCode', label: 'Municipality', parentDimension: 'provincieCode' },
				{ dimension: 'wijkCode', label: 'District', parentDimension: 'gemeenteCode' },
				{ dimension: 'buurtCode', label: 'Neighbourhood', parentDimension: 'wijkCode' },
			],
		})
	})

	it('stores the English source labels and shows the translated ones', () => {
		expect(presetLevelLabels(NL_ADMINISTRATIVE)).toEqual({
			provincieCode: 'nl:Province',
			gemeenteCode: 'nl:Municipality',
			wijkCode: 'nl:District',
			buurtCode: 'nl:Neighbourhood',
		})
		expect(presetLevelLabels('own-taxonomy')).toBeNull()
	})
})
