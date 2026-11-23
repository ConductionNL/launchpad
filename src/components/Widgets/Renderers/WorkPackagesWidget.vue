<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="work-packages-widget" data-testid="work-packages-widget">
		<p v-if="state === 'not-installed'" class="work-packages-widget__state" data-testid="work-packages-not-installed">
			{{ t('launchpad', 'Work packages need the OpenProject integration. Ask your administrator to enable it.') }}
		</p>
		<p v-else-if="state === 'not-connected'" class="work-packages-widget__state" data-testid="work-packages-not-connected">
			{{ t('launchpad', 'Connect your OpenProject account to see your work packages.') }}
			<a :href="connectUrl" class="work-packages-widget__connect">{{ t('launchpad', 'Connect OpenProject') }}</a>
		</p>
		<CnStatusList
			v-else
			:items="items"
			:loading="state === 'loading'"
			:error="state === 'error' ? t('launchpad', 'The work packages could not be loaded. Try again later.') : ''"
			:emptyLabel="scope === 'project' ? t('launchpad', 'This project has no open work packages') : t('launchpad', 'You have no open work packages')" />
	</div>
</template>

<script>
import { CnStatusList } from '@conduction/nextcloud-vue'
import { translate as t } from '@nextcloud/l10n'
import { OBJECT_PROP } from '../../../constants/propDefinitions.js'
import { loadWorkPackages } from '../../../services/workPackages.js'

/**
 * WorkPackagesWidget: the `work-packages` widget (board LpWerkplek "Mijn
 * werkpakketten", LpAfdeling "Werkpakketten van het team"). The open
 * OpenProject work packages, due date first, each a link into OpenProject
 * with its status, project and due date. They are read through the official
 * OpenProject integration with the person's own token.
 *
 * `content`: `{ scope, project, limit }`. `scope` `mine` (default) lists the
 * person's own; `project` lists every open one of `project`. `limit` defaults to 4.
 *
 * @spec openspec/changes/work-packages-widget/specs/work-packages-widget/spec.md
 */
export default {
	name: 'WorkPackagesWidget',

	components: { CnStatusList },

	props: {
		/**
		 * The placement row, which the widget does not read; declared so the
		 * binding every renderer gets does not fall through as an attribute.
		 */
		// eslint-disable-next-line vue/no-unused-properties
		placement: OBJECT_PROP,

		/** Persisted widget content: `{ scope, project, limit }`. */
		content: OBJECT_PROP,
	},

	data() {
		return { state: 'loading', workPackages: [], connectUrl: '' }
	},

	computed: {
		/**
		 * @return {string} `mine` or `project`.
		 * @spec openspec/changes/work-packages-widget/specs/work-packages-widget/spec.md#requirement-a-team-page-lists-one-projects-work-packages-req-wp-003
		 */
		scope() {
			return this.content?.scope === 'project' ? 'project' : 'mine'
		},

		/**
		 * The work packages as list items: subject as a link, project (and the
		 * assignee on a project list) as detail, the due date and the status.
		 *
		 * @return {Array<object>}
		 * @spec openspec/changes/work-packages-widget/specs/work-packages-widget/spec.md#requirement-the-widget-lists-open-work-packages-due-date-first-req-wp-001
		 */
		items() {
			return this.workPackages.map((workPackage) => ({
				id: String(workPackage.id),
				title: workPackage.subject || `#${workPackage.id}`,
				href: workPackage.url,
				external: true,
				meta: this.scope === 'project' && workPackage.assignee
					? `${workPackage.project} · ${workPackage.assignee}`
					: workPackage.project,
				due: workPackage.dueDate,
				status: workPackage.status ? { label: workPackage.status, variant: 'info' } : null,
			}))
		},
	},

	/** @spec openspec/changes/work-packages-widget/specs/work-packages-widget/spec.md#requirement-the-widget-lists-open-work-packages-due-date-first-req-wp-001 */
	async created() {
		const limit = Number(this.content?.limit)
		const result = await loadWorkPackages({
			scope: this.scope,
			project: String(this.content?.project || ''),
			limit: Number.isInteger(limit) && limit > 0 ? limit : 4,
		})
		this.workPackages = result.workPackages
		this.connectUrl = result.connectUrl
		this.state = result.state
	},

	methods: { t },
}
</script>

<style scoped>
.work-packages-widget {
	height: 100%;
	min-height: 0;
	overflow-y: auto;
}

.work-packages-widget__state {
	margin: 0;
	color: var(--color-text-maxcontrast);
}

.work-packages-widget__connect {
	display: block;
	margin-top: 8px;
	color: var(--color-primary-element);
	text-decoration: underline;
}
</style>
