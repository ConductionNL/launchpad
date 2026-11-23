<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<fieldset class="section-heading-widget-form">
		<NcTextField
			:modelValue="title"
			:label="t('launchpad', 'Heading')"
			:required="true"
			data-testid="section-heading-form-title"
			@update:modelValue="update({ title: String($event) })" />
		<NcTextField
			:modelValue="description"
			:label="t('launchpad', 'Explanation')"
			:helperText="t('launchpad', 'One line under the heading. Leave empty for none.')"
			data-testid="section-heading-form-description"
			@update:modelValue="update({ description: String($event) })" />
	</fieldset>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcTextField } from '@nextcloud/vue'
import { objectProp } from '../../../constants/propDefinitions.js'

/**
 * SectionHeadingWidgetForm: the heading and the one line of explanation of
 * a `section-heading` widget.
 *
 * @spec openspec/specs/widgets/spec.md#requirement-req-wdg-031-a-section-heading-widget
 */
export default {
	name: 'SectionHeadingWidgetForm',

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
		value: objectProp({ title: '', description: '' }),
	},

	emits: ['update:content'],

	data() {
		const initial = this.editingWidget?.content || this.value || {}
		return {
			title: String(initial.title || ''),
			description: String(initial.description || ''),
		}
	},

	methods: {
		t,

		/**
		 * Keep a change and tell the modal the whole content.
		 *
		 * @param {object} change The changed keys.
		 * @return {void}
		 * @spec openspec/specs/widgets/spec.md#requirement-req-wdg-031-a-section-heading-widget
		 */
		update(change) {
			Object.assign(this, change)
			this.$emit('update:content', { title: this.title.trim(), description: this.description.trim() })
		},

		/**
		 * Validate the form: a heading needs a title.
		 *
		 * @return {string[]} The validation errors.
		 * @spec openspec/specs/widgets/spec.md#requirement-req-wdg-031-a-section-heading-widget
		 */
		validate() {
			return this.title.trim() === '' ? [t('launchpad', 'Give the heading a title')] : []
		},
	},
}
</script>

<style scoped>
.section-heading-widget-form {
	margin: 0;
	padding: 0;
	border: 0;
	display: flex;
	flex-direction: column;
	gap: 12px;
}
</style>
