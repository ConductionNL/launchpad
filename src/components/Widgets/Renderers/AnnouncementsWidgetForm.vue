<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="announcements-widget-form">
		<p class="announcements-widget-form__hint">
			{{
				t(
					'launchpad',
					'Shows the news items that are meant for the reader, newest first.',
				)
			}}
		</p>

		<NcSelect
			:modelValue="limit"
			:options="limitOptions"
			:inputLabel="t('launchpad', 'Announcements to show')"
			:clearable="false"
			@update:modelValue="updateLimit" />
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcSelect } from '@nextcloud/vue'

const DEFAULT_LIMIT = 5
const LIMIT_OPTIONS = [3, 5, 10, 20]

/**
 * AnnouncementsWidgetForm: the one setting of the announcements widget, how
 * many items it shows. Who sees which item is decided by its targeting.
 */
export default {
	name: 'AnnouncementsWidgetForm',

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
		 * @param {number} limit Announcements to show.
		 * @spec openspec/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
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
		 * @spec openspec/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
		 */
		validate() {
			return []
		},
	},
}
</script>

<style scoped>
.announcements-widget-form {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.announcements-widget-form__hint {
	color: var(--color-text-maxcontrast);
	margin: 0;
}
</style>
