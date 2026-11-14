<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="communities-widget-form">
		<p class="communities-widget-form__hint">
			{{
				t(
					'launchpad',
					'Lists the open Talk conversations each person may join. Talk decides who sees which conversation.',
				)
			}}
		</p>

		<NcTextField
			:modelValue="title"
			:label="t('launchpad', 'Title')"
			@update:modelValue="update({ title: $event })" />

		<NcCheckboxRadioSwitch
			type="switch"
			:modelValue="onlyPinned"
			data-testid="communities-only-pinned"
			@update:modelValue="update({ onlyPinned: $event === true })">
			{{ t('launchpad', 'Show only pinned') }}
		</NcCheckboxRadioSwitch>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcCheckboxRadioSwitch, NcTextField } from '@nextcloud/vue'
import {
	NULLABLE_OBJECT_PROP,
	objectProp,
} from '../../../constants/propDefinitions.js'

/**
 * CommunitiesWidgetForm: the title of the Communities widget and whether it
 * shows only the organisation's pinned communities
 * (openspec/changes/engagement-communities, REQ-COMM-003).
 */
export default {
	name: 'CommunitiesWidgetForm',

	components: { NcCheckboxRadioSwitch, NcTextField },

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
		value: objectProp({ title: '', onlyPinned: false }),
	},

	emits: ['update:content'],

	data() {
		const initial = this.editingWidget?.content || this.value || {}
		return {
			title: String(initial.title || ''),
			onlyPinned: initial.onlyPinned === true,
		}
	},

	methods: {
		t,

		/**
		 * Keep a change and tell the modal the whole content.
		 *
		 * @param {object} change The changed keys.
		 * @spec openspec/changes/engagement-communities/specs/communities-widget/spec.md#requirement-administrators-pin-official-communities-req-comm-003
		 */
		update(change) {
			Object.assign(this, change)
			this.$emit('update:content', {
				title: this.title,
				onlyPinned: this.onlyPinned,
			})
		},

		/**
		 * Validate the form. Both settings are optional.
		 *
		 * @return {string[]} The validation errors (always empty).
		 * @spec openspec/changes/engagement-communities/specs/communities-widget/spec.md#requirement-administrators-pin-official-communities-req-comm-003
		 */
		validate() {
			return []
		},
	},
}
</script>

<style scoped>
.communities-widget-form {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.communities-widget-form__hint {
	color: var(--color-text-maxcontrast);
	margin: 0;
}
</style>
