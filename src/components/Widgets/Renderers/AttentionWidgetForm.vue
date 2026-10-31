<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="attention-widget-form">
		<p class="attention-widget-form__hint">
			{{
				t(
					'launchpad',
					'Shows what needs attention today, from every app that reports it. Each line links into the app.',
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

const DEFAULT_LIMIT = 5
const LIMIT_OPTIONS = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10]

/**
 * AttentionWidgetForm — the one setting of the "First today" widget: how many
 * lines it shows (REQ-ATT-004). What it shows is decided by the apps.
 */
export default {
	name: 'AttentionWidgetForm',

	components: { NcSelect },

	props: {
		/**
		 * The placement being edited, or `null` in create mode.
		 *
		 * @type {{content: object}|null}
		 */
		editingWidget: {
			type: Object,
			default: null,
		},

		/**
		 * Initial content values, used when not editing.
		 *
		 * @type {object}
		 */
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
		 * @param {number} limit Lines to show.
		 * @spec openspec/specs/attention-feed/spec.md#req-att-004
		 */
		updateLimit(limit) {
			this.limit = LIMIT_OPTIONS.includes(Number(limit))
				? Number(limit)
				: DEFAULT_LIMIT
			this.$emit('update:content', { limit: this.limit })
		},

		/**
		 * Validate the form. The one setting always has a valid value.
		 *
		 * @return {string[]} The validation errors (always empty).
		 * @spec openspec/specs/attention-feed/spec.md#req-att-006
		 */
		validate() {
			return []
		},
	},
}
</script>

<style scoped>
.attention-widget-form {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.attention-widget-form__hint {
	color: var(--color-text-maxcontrast);
	margin: 0;
}
</style>
