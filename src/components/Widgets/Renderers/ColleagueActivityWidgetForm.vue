<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="colleague-activity-form">
		<p class="colleague-activity-form__hint">
			{{
				t(
					'launchpad',
					'Shows what colleagues did recently that you may see.',
				)
			}}
		</p>

		<NcSelect
			:modelValue="limit"
			:options="limitOptions"
			:inputLabel="t('launchpad', 'Lines to show')"
			:clearable="false"
			@update:modelValue="updateLimit" />
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcSelect } from '@nextcloud/vue'

const DEFAULT_LIMIT = 10
const LIMIT_OPTIONS = [5, 10, 15, 20, 25]

/**
 * ColleagueActivityWidgetForm: how many lines the colleague activity widget
 * shows (dashboards-and-who-may-see-them REQ-DWMS-007).
 */
export default {
	name: 'ColleagueActivityWidgetForm',

	components: { NcSelect },

	props: {
		/** The placement being edited, or `null` in create mode. */
		editingWidget: {
			type: Object,
			default: null,
		},

		/** Initial content values, used when not editing. */
		value: {
			type: Object,
			default: () => ({ limit: DEFAULT_LIMIT }),
		},
	},

	emits: ['update:content'],

	data() {
		const initial = Number(
			(this.editingWidget?.content || this.value || {}).limit,
		)
		return {
			limitOptions: LIMIT_OPTIONS,
			limit: LIMIT_OPTIONS.includes(initial) ? initial : DEFAULT_LIMIT,
		}
	},

	methods: {
		t,

		/**
		 * Keep the new limit and tell the modal.
		 *
		 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
		 * @param {number} limit Lines to show.
		 */
		updateLimit(limit) {
			this.limit = LIMIT_OPTIONS.includes(Number(limit))
				? Number(limit)
				: DEFAULT_LIMIT
			this.$emit('update:content', { limit: this.limit })
		},
	},
}
</script>
