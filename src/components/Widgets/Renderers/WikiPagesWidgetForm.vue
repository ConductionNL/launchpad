<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="wiki-pages-widget-form">
		<NcTextField
			:modelValue="String(limit)"
			type="number"
			min="1"
			max="20"
			:label="t('launchpad', 'Pages per list')"
			data-testid="wiki-pages-form-limit"
			@update:modelValue="update({ limit: Number($event) })" />
		<NcTextField
			:modelValue="space"
			:label="t('launchpad', 'Wiki space')"
			:helperText="t('launchpad', 'Leave empty to show every space.')"
			data-testid="wiki-pages-form-space"
			@update:modelValue="update({ space: String($event) })" />

		<p class="wiki-pages-widget-form__hint">
			{{ t('launchpad', 'Shows search, favourites and recent pages from XWiki, through the connection your administrator set up.') }}
		</p>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcTextField } from '@nextcloud/vue'
import { NULLABLE_OBJECT_PROP, objectProp } from '../../../constants/propDefinitions.js'

/**
 * WikiPagesWidgetForm: which wiki space the `wiki-pages` widget shows and
 * how many favourites and recent pages it lists.
 *
 * @spec openspec/changes/wiki-widget/specs/wiki-widget/spec.md#requirement-a-team-wiki-shows-one-space-req-wiki-003
 */
export default {
	name: 'WikiPagesWidgetForm',

	components: { NcTextField },

	props: {
		/**
		 * The placement being edited, or `null` in create mode.
		 *
		 * @type {{content: object}|null}
		 */
		editingWidget: NULLABLE_OBJECT_PROP,

		/**
		 * Initial content values, used when not editing.
		 *
		 * @type {object}
		 */
		value: objectProp({ space: '', limit: 3 }),
	},

	emits: ['update:content'],

	data() {
		const initial = this.editingWidget?.content || this.value || {}
		return {
			space: String(initial.space || ''),
			limit: Number.isInteger(initial.limit) && initial.limit > 0 ? initial.limit : 3,
		}
	},

	methods: {
		t,

		/**
		 * Keep a change and tell the modal the whole content.
		 *
		 * @param {object} change The changed keys.
		 * @return {void}
		 * @spec openspec/changes/wiki-widget/specs/wiki-widget/spec.md#requirement-a-team-wiki-shows-one-space-req-wiki-003
		 */
		update(change) {
			Object.assign(this, change)
			this.$emit('update:content', { space: this.space.trim(), limit: this.limit })
		},

		/**
		 * Validate the form: the list length is 1 to 20.
		 *
		 * @return {string[]} The validation errors.
		 * @spec openspec/changes/wiki-widget/specs/wiki-widget/spec.md#requirement-a-team-wiki-shows-one-space-req-wiki-003
		 */
		validate() {
			return Number.isInteger(this.limit) && this.limit >= 1 && this.limit <= 20
				? []
				: [t('launchpad', 'Show 1 to 20 pages per list')]
		},
	},
}
</script>

<style scoped>
.wiki-pages-widget-form {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.wiki-pages-widget-form__hint {
	margin: 0;
	color: var(--color-text-maxcontrast);
}
</style>
