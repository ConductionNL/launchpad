<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="assigned-cases-widget" data-testid="assigned-cases-widget">
		<p v-if="!caseApp" class="assigned-cases-widget__state" data-testid="assigned-cases-no-app">
			{{ t('launchpad', 'My cases needs dossiq. Ask your administrator to enable it.') }}
		</p>
		<template v-else>
			<CnStatusList
				:items="items"
				:counts="state === 'ok' ? counts : []"
				:loading="state === 'loading'"
				:error="state === 'error' ? t('launchpad', 'The cases could not be loaded. Try again later.') : ''"
				:emptyLabel="t('launchpad', 'No open cases are assigned to you')" />
			<a
				v-if="state === 'ok' && total > items.length"
				:href="allCasesUrl"
				class="assigned-cases-widget__all"
				data-testid="assigned-cases-all">
				{{ n('launchpad', 'All %n open case', 'All %n open cases', total) }}
			</a>
		</template>
	</div>
</template>

<script>
import { CnStatusList } from '@conduction/nextcloud-vue'
import { getCurrentUser } from '@nextcloud/auth'
import { translatePlural as n, translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { OBJECT_PROP } from '../../../constants/propDefinitions.js'
import { loadAssignedCases } from '../../../services/assignedCases.js'

/**
 * AssignedCasesWidget: the `assigned-cases` widget (board LpWerkplek "Mijn
 * zaken"). The open cases assigned to the viewer, deadline first, each with
 * its deadline and status, under the counts per status. The cases come from
 * OpenRegister with the viewer's session; each opens in the case app.
 *
 * The case app is the one the initial state's `integrationApps.dossiq` names,
 * the id dossiq is installed under, so a renamed install keeps working.
 *
 * `content`: `{ register, schema, limit }`, by default dossiq's `dossiq`
 * register and `case` schema and 4 cases.
 *
 * @spec openspec/changes/assigned-cases-widget/specs/assigned-cases-widget/spec.md
 */
export default {
	name: 'AssignedCasesWidget',

	components: { CnStatusList },

	inject: {
		/** Which apps behind the integration widgets are enabled for the viewer. */
		integrationApps: { from: 'integrationApps', default: () => ({}) },
	},

	props: {
		/** Persisted widget content: `{ register, schema, limit }`, see the component description. */
		content: OBJECT_PROP,
		// The placement row is bound on every renderer; declared so it stays out of $attrs.
		// eslint-disable-next-line vue/no-unused-properties
		placement: OBJECT_PROP,
	},

	data() {
		return {
			state: 'loading',
			cases: [],
			counts: [],
			total: 0,
		}
	},

	computed: {
		/**
		 * @return {string} The id the case app is installed under, '' without it.
		 * @spec openspec/changes/assigned-cases-widget/specs/assigned-cases-widget/spec.md#requirement-the-widget-is-offered-only-with-dossiq-req-cases-004
		 */
		caseApp() {
			const app = this.integrationApps?.dossiq
			return typeof app === 'string' ? app : ''
		},

		/**
		 * @return {number} How many cases show.
		 * @spec openspec/changes/assigned-cases-widget/specs/assigned-cases-widget/spec.md#requirement-the-widget-lists-the-viewers-open-cases-deadline-first-req-cases-001
		 */
		limit() {
			const limit = Number(this.content?.limit)
			return Number.isInteger(limit) && limit > 0 ? Math.min(limit, 20) : 4
		},

		/**
		 * The first cases as list items: title as a link into the case app,
		 * the case number as detail, the deadline and the status.
		 *
		 * @return {Array<object>}
		 * @spec openspec/changes/assigned-cases-widget/specs/assigned-cases-widget/spec.md#requirement-the-widget-lists-the-viewers-open-cases-deadline-first-req-cases-001
		 */
		items() {
			return this.cases.slice(0, this.limit).map((record) => ({
				id: record.id,
				title: record.title,
				href: generateUrl('/apps/{app}/cases/{id}', { app: this.caseApp, id: record.id }),
				meta: record.identifier,
				due: record.deadline,
				status: record.status.label ? { label: record.status.label, variant: record.status.variant } : null,
			}))
		},

		/**
		 * @return {string} The case app's list of cases.
		 * @spec openspec/changes/assigned-cases-widget/specs/assigned-cases-widget/spec.md#requirement-the-widget-lists-the-viewers-open-cases-deadline-first-req-cases-001
		 */
		allCasesUrl() {
			return generateUrl('/apps/{app}/cases', { app: this.caseApp })
		},
	},

	/** @spec openspec/changes/assigned-cases-widget/specs/assigned-cases-widget/spec.md#requirement-the-widget-lists-the-viewers-open-cases-deadline-first-req-cases-001 */
	async created() {
		if (!this.caseApp) {
			return
		}
		const result = await loadAssignedCases({
			register: String(this.content?.register || 'dossiq'),
			schema: String(this.content?.schema || 'case'),
			userId: getCurrentUser()?.uid || '',
		})
		this.cases = result.cases
		this.counts = result.counts
		this.total = result.total
		this.state = result.state
	},

	methods: { t, n },
}
</script>

<style scoped>
.assigned-cases-widget {
	display: flex;
	flex-direction: column;
	gap: 8px;
	height: 100%;
	min-height: 0;
	overflow-y: auto;
}

.assigned-cases-widget__state {
	margin: 0;
	color: var(--color-text-maxcontrast);
}

.assigned-cases-widget__all {
	color: var(--color-primary-element);
	text-decoration: underline;
}
</style>
