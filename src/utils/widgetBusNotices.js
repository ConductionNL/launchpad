/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * widgetBusNotices — what the widget dialog says about a widget's place on
 * the dashboard's filter bus (drill-down-cross-widget-filter REQ-CWF-008).
 * The findings of `validateBusDeclarations()`, as the notices
 * CnAddWidgetModal shows above the widget's form.
 */

import { translate as t } from '@nextcloud/l10n'
import { busDeclarationsOf } from './widgetBusConsumption.js'
import { validateBusDeclarations } from './widgetBusDeclarations.js'

/**
 * The sentence for one finding.
 *
 * @param {{code: string, dimension: string, field?: string}} finding The finding.
 * @return {string}
 */
function sentence(finding) {
	if (finding.code === 'publishes-missing-field') {
		return t('launchpad', 'This widget publishes {dimension} from {field}, which its data does not have. Clicks on it do not filter the dashboard.', { dimension: finding.dimension, field: finding.field })
	}
	return t('launchpad', 'This widget consumes {dimension}, but no hierarchy on this dashboard publishes it. It shows its data unfiltered.', { dimension: finding.dimension })
}

/**
 * The notices for a widget's filter declarations on a dashboard: none when
 * the widget declares nothing or fits.
 *
 * @param {object|null} content The widget's placement content.
 * @param {Array<object>} hierarchies The dashboard's hierarchy descriptors.
 * @return {Array<{id: string, type: string, text: string}>}
 * @spec openspec/changes/archive/2026-11-25-drill-down-cross-widget-filter/tasks.md#task-9
 */
export function busNotices(content, hierarchies) {
	const { publishes, consumes } = busDeclarationsOf(content)
	if (publishes.length === 0 && consumes.length === 0) {
		return []
	}
	const { findings } = validateBusDeclarations({ widget: { publishes, consumes }, hierarchies: Array.isArray(hierarchies) ? hierarchies : [] })
	return findings.map((finding) => ({
		id: `${finding.code}:${finding.dimension}`,
		type: finding.level === 'error' ? 'error' : 'warning',
		text: sentence(finding),
	}))
}
