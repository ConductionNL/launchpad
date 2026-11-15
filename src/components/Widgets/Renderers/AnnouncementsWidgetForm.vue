<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="announcements-widget-form">
		<NcTextField
			:modelValue="category"
			:label="t('launchpad', 'Show one category (empty for all)')"
			@update:modelValue="set('category', $event)" />
		<NcTextField
			:modelValue="String(limit)"
			type="number"
			:label="t('launchpad', 'Number of announcements (1–50)')"
			@update:modelValue="setLimit($event)" />
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcTextField } from '@nextcloud/vue'
import { NULLABLE_OBJECT_PROP, objectProp } from '../../../constants/propDefinitions.js'

export const DEFAULT_CONTENT = Object.freeze({ category: '', limit: 10 })

/**
 * AnnouncementsWidgetForm — the initial category filter and the number of
 * announcements of an Announcements widget (engagement-announcements task 7).
 * Emits `update:content` on every change.
 *
 * @spec openspec/changes/engagement-announcements/tasks.md#task-7
 */
export default {
	name: 'AnnouncementsWidgetForm',

	components: { NcTextField },

	props: {
		/** The placement being edited, or `null` in create mode. */
		editingWidget: NULLABLE_OBJECT_PROP,

		/** Initial content values (registry defaults when not editing). */
		value: objectProp(DEFAULT_CONTENT),
	},

	emits: ['update:content'],

	data() {
		const initial = this.editingWidget?.content || this.value || {}
		return {
			category: typeof initial.category === 'string' ? initial.category : '',
			limit: Number(initial.limit) > 0 ? Math.min(50, Number(initial.limit)) : DEFAULT_CONTENT.limit,
		}
	},

	methods: {
		t,

		/**
		 * @param {string} field The field.
		 * @param {string} value The value.
		 * @spec openspec/changes/engagement-announcements/tasks.md#task-7
		 */
		set(field, value) {
			this[field] = String(value ?? '').trim()
			this.emitUpdate()
		},

		/**
		 * @param {string} value The typed number.
		 * @spec openspec/changes/engagement-announcements/tasks.md#task-7
		 */
		setLimit(value) {
			const parsed = parseInt(value, 10)
			this.limit = Number.isNaN(parsed) ? DEFAULT_CONTENT.limit : Math.min(50, Math.max(1, parsed))
			this.emitUpdate()
		},

		/** @spec openspec/changes/engagement-announcements/tasks.md#task-7 */
		emitUpdate() {
			this.$emit('update:content', { category: this.category, limit: this.limit })
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
</style>
