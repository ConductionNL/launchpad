<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="work-packages-widget-form">
		<p class="work-packages-widget-form__hint">
			{{ t('launchpad', 'Shows open OpenProject work packages, due date first. Each person sees what OpenProject lets them see.') }}
		</p>

		<fieldset class="work-packages-widget-form__scope">
			<legend>{{ t('launchpad', 'Which work packages') }}</legend>
			<NcCheckboxRadioSwitch
				:modelValue="scope"
				value="mine"
				name="work-packages-scope"
				type="radio"
				data-testid="work-packages-form-scope-mine"
				@update:modelValue="update({ scope: 'mine' })">
				{{ t('launchpad', 'Assigned to the person viewing') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch
				:modelValue="scope"
				value="project"
				name="work-packages-scope"
				type="radio"
				data-testid="work-packages-form-scope-project"
				@update:modelValue="update({ scope: 'project' })">
				{{ t('launchpad', 'Everyone in one project') }}
			</NcCheckboxRadioSwitch>
		</fieldset>

		<NcTextField
			v-if="scope === 'project'"
			:modelValue="project"
			:label="t('launchpad', 'Project identifier')"
			:helperText="t('launchpad', 'The identifier in the project address, such as havenpark.')"
			data-testid="work-packages-form-project"
			@update:modelValue="update({ project: String($event) })" />

		<NcTextField
			:modelValue="String(limit)"
			type="number"
			min="1"
			max="25"
			:label="t('launchpad', 'Number of work packages')"
			data-testid="work-packages-form-limit"
			@update:modelValue="update({ limit: Number($event) })" />
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcCheckboxRadioSwitch, NcTextField } from '@nextcloud/vue'
import { NULLABLE_OBJECT_PROP, objectProp } from '../../../constants/propDefinitions.js'

/**
 * WorkPackagesWidgetForm: whether the `work-packages` widget lists the
 * viewer's own work packages or one project's, and how many.
 *
 * @spec openspec/changes/work-packages-widget/specs/work-packages-widget/spec.md#requirement-a-team-page-lists-one-projects-work-packages-req-wp-003
 */
export default {
	name: 'WorkPackagesWidgetForm',

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
		value: objectProp({ scope: 'mine', project: '', limit: 4 }),
	},

	emits: ['update:content'],

	data() {
		const initial = this.editingWidget?.content || this.value || {}
		return {
			scope: initial.scope === 'project' ? 'project' : 'mine',
			project: String(initial.project || ''),
			limit: Number.isInteger(initial.limit) && initial.limit > 0 ? initial.limit : 4,
		}
	},

	methods: {
		t,

		/**
		 * Keep a change and tell the modal the whole content.
		 *
		 * @param {object} change The changed keys.
		 * @return {void}
		 * @spec openspec/changes/work-packages-widget/specs/work-packages-widget/spec.md#requirement-a-team-page-lists-one-projects-work-packages-req-wp-003
		 */
		update(change) {
			Object.assign(this, change)
			this.$emit('update:content', { scope: this.scope, project: this.project.trim(), limit: this.limit })
		},

		/**
		 * Validate: a project list names its project, and the number is 1 to 25.
		 *
		 * @return {string[]} The validation errors.
		 * @spec openspec/changes/work-packages-widget/specs/work-packages-widget/spec.md#requirement-a-team-page-lists-one-projects-work-packages-req-wp-003
		 */
		validate() {
			const errors = []
			if (this.scope === 'project' && this.project.trim() === '') {
				errors.push(t('launchpad', 'Name the project'))
			}
			if (!Number.isInteger(this.limit) || this.limit < 1 || this.limit > 25) {
				errors.push(t('launchpad', 'Show 1 to 25 work packages'))
			}
			return errors
		},
	},
}
</script>

<style scoped>
.work-packages-widget-form {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.work-packages-widget-form__scope {
	margin: 0;
	padding: 0;
	border: 0;
}

.work-packages-widget-form__hint {
	margin: 0;
	color: var(--color-text-maxcontrast);
}
</style>
