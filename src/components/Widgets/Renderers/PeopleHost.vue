<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<p
		v-if="newSelection && configured === false"
		class="people-host__unconfigured"
		role="status">
		{{
			t(
				'launchpad',
				'The administrator has not set a start date field, so new colleagues cannot be listed.',
			)
		}}
	</p>
	<CnPeopleWidget
		v-else-if="newSelection"
		:content="widgetContent"
		:dataSource="newColleaguesSource" />
	<CnPeopleWidget v-else :content="content" />
</template>

<script>
import { CnPeopleWidget } from '@conduction/nextcloud-vue'
import { getLanguage, t } from '@nextcloud/l10n'
import { OBJECT_PROP } from '../../../constants/propDefinitions.js'
import {
	fetchNewColleagues,
	isNewSelection,
	newColleaguesPage,
	utcToday,
	widgetContentFor,
	windowDays,
} from '../../../services/newColleagues.js'

/**
 * PeopleHost — LaunchPad wrapper around CnPeopleWidget. A placement with
 * `selectionMode: 'new'` lists the new colleagues of its window, newest start
 * first, with photo, role and team and the day a future colleague starts;
 * without a start date field it says the administrator has not set one
 * (REQ-PPL-014). Every other placement renders CnPeopleWidget as is, over the
 * injected people directory.
 *
 * @spec openspec/changes/people-new-colleagues/specs/people-widget/spec.md#requirement-the-people-widget-can-list-new-colleagues-req-ppl-014
 */
export default {
	name: 'PeopleHost',

	components: { CnPeopleWidget },

	props: {
		/** The placement's content blob. */
		content: OBJECT_PROP,
	},

	data() {
		return {
			// null until the first answer; false when no field is the start date.
			configured: null,
		}
	},

	computed: {
		/**
		 * Whether the placement lists new colleagues.
		 *
		 * @return {boolean}
		 * @spec openspec/changes/people-new-colleagues/specs/people-widget/spec.md#requirement-the-people-widget-can-list-new-colleagues-req-ppl-014
		 */
		newSelection() {
			return isNewSelection(this.content)
		},

		/**
		 * The content CnPeopleWidget gets: the window rides along as a filter.
		 *
		 * @return {object}
		 * @spec openspec/changes/people-new-colleagues/specs/people-widget/spec.md#requirement-the-people-widget-can-list-new-colleagues-req-ppl-014
		 */
		widgetContent() {
			return widgetContentFor(this.content)
		},

		/**
		 * The data source of the New colleagues list: one page, the whole window.
		 *
		 * @return {{fetchPeople: (args: object) => Promise<object>}}
		 * @spec openspec/changes/people-new-colleagues/specs/people-widget/spec.md#requirement-the-people-widget-can-list-new-colleagues-req-ppl-014
		 */
		newColleaguesSource() {
			return {
				fetchPeople: (args = {}) => this.fetchPage(args),
			}
		},
	},

	methods: {
		t,

		/**
		 * The first page is the whole list; later pages are empty.
		 *
		 * @param {object} args The widget's fetch arguments.
		 * @return {Promise<{users: object[], total: number, hasMore: boolean}>}
		 * @spec openspec/changes/people-new-colleagues/specs/people-widget/spec.md#requirement-the-people-widget-can-list-new-colleagues-req-ppl-014
		 */
		async fetchPage(args) {
			if ((args.offset ?? 0) > 0) {
				return { users: [], total: 0, hasMore: false }
			}
			const answer = await fetchNewColleagues({
				since: windowDays(this.content, 'since'),
				until: windowDays(this.content, 'until'),
			})
			this.configured = answer.configured
			return newColleaguesPage(answer, {
				filters: this.content.filters,
				today: utcToday(),
				locale: getLanguage() || 'en',
			})
		},
	},
}
</script>

<style scoped>
.people-host__unconfigured {
	margin: 0;
	padding: calc(var(--default-grid-baseline, 4px) * 3);
	color: var(--color-text-maxcontrast);
}
</style>
