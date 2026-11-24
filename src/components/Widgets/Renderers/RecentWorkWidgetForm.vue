<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<fieldset class="recent-work-widget-form">
		<NcTextField
			:modelValue="String(limit)"
			type="number"
			:label="t('launchpad', 'How many items')"
			data-testid="recent-work-form-limit"
			@update:modelValue="update($event)" />
	</fieldset>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcTextField } from '@nextcloud/vue'
import { objectProp } from '../../../constants/propDefinitions.js'

/**
 * RecentWorkWidgetForm: how many items of the reader's own recent work the
 * `recent-work` widget lists.
 *
 * @spec openspec/specs/widgets/spec.md
 * @spec openspec/changes/archive/2026-11-24-recent-work-and-web-search-widgets/specs/widgets/spec.md#requirement-recent-work-shows-where-the-reader-left-off-req-wdg-032
 */
export default {
	name: 'RecentWorkWidgetForm',

	components: { NcTextField },

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
		value: objectProp({ limit: 6 }),
	},

	emits: ['update:content'],

	data() {
		const initial = this.editingWidget?.content || this.value || {}
		return { limit: Number(initial.limit) > 0 ? Number(initial.limit) : 6 }
	},

	methods: {
		t,

		/**
		 * Keep the new number, between 1 and 20, and tell the modal.
		 *
		 * @param {string|number} value The typed number.
		 * @return {void}
		 * @spec openspec/specs/widgets/spec.md
		 * @spec openspec/changes/archive/2026-11-24-recent-work-and-web-search-widgets/specs/widgets/spec.md#requirement-recent-work-shows-where-the-reader-left-off-req-wdg-032
		 */
		update(value) {
			const number = Math.round(Number(value))
			this.limit = Number.isFinite(number) ? Math.min(Math.max(number, 1), 20) : 6
			this.$emit('update:content', { limit: this.limit })
		},
	},
}
</script>
