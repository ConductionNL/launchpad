<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<NcModal
		v-if="open"
		class="launchpad-modal"
		size="small"
		:name="title"
		@close="$emit('close')">
		<form
			class="save-view"
			data-testid="save-view-modal"
			@submit.prevent="submit">
			<h2 class="save-view__title">
				{{ title }}
			</h2>
			<NcTextField
				v-model="name"
				:label="t('launchpad', 'Name')"
				:maxlength="255"
				required
				data-testid="save-view-name" />
			<NcTextField
				v-model="description"
				:label="t('launchpad', 'Description')"
				data-testid="save-view-description" />
			<NcCheckboxRadioSwitch
				v-model="isShared"
				type="switch"
				data-testid="save-view-shared">
				{{ t('launchpad', 'Share with everyone who can open this dashboard') }}
			</NcCheckboxRadioSwitch>
			<p
				v-if="error"
				class="save-view__error"
				role="alert"
				data-testid="save-view-error">
				{{ error }}
			</p>
			<div class="save-view__actions">
				<NcButton
					type="submit"
					variant="primary"
					:disabled="!canSubmit"
					data-testid="save-view-submit">
					{{ view ? t('launchpad', 'Save changes') : t('launchpad', 'Save view') }}
				</NcButton>
			</div>
		</form>
	</NcModal>
</template>

<script>
import { NcButton, NcCheckboxRadioSwitch, NcModal, NcTextField } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import { FLAG_PROP, NULLABLE_OBJECT_PROP, TEXT_PROP } from '../constants/propDefinitions.js'

/**
 * SaveViewModal: name, describe and optionally share the dashboard's current
 * filters as a saved view, or edit an existing view's name, description and
 * sharing (drill-down-cross-widget-filter REQ-CWF-009). It only collects the
 * input; the caller saves it and reports a failure through `error`.
 *
 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-11
 */
export default {
	name: 'SaveViewModal',

	components: { NcButton, NcCheckboxRadioSwitch, NcModal, NcTextField },

	props: {
		/** Whether the modal is shown. */
		open: FLAG_PROP,
		/** The view being edited; null saves a new one. */
		view: NULLABLE_OBJECT_PROP,
		/** A save failure to show. */
		error: TEXT_PROP,
	},

	emits: ['close', 'submit'],

	data() {
		return { name: '', description: '', isShared: false }
	},

	computed: {
		/**
		 * The modal's title.
		 *
		 * @return {string}
		 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-11
		 */
		title() {
			return this.view ? t('launchpad', 'Edit saved view') : t('launchpad', 'Save view')
		},

		/**
		 * Whether a name was given.
		 *
		 * @return {boolean}
		 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-11
		 */
		canSubmit() {
			return this.name.trim() !== ''
		},
	},

	watch: {
		open: {
			immediate: true,
			/**
			 * Start from the edited view, or empty, each time it opens.
			 *
			 * @param {boolean} isOpen Whether it opened.
			 * @return {void}
			 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-11
			 */
			handler(isOpen) {
				if (isOpen) {
					this.name = this.view?.name ?? ''
					this.description = this.view?.description ?? ''
					this.isShared = this.view?.isShared === true
				}
			},
		},
	},

	methods: {
		t,

		/**
		 * Hand the input to the caller.
		 *
		 * @return {void}
		 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-11
		 */
		submit() {
			if (!this.canSubmit) {
				return
			}
			this.$emit('submit', { name: this.name.trim(), description: this.description.trim(), isShared: this.isShared })
		},
	},
}
</script>

<style scoped>
.save-view {
	display: flex;
	flex-direction: column;
	gap: 12px;
	padding: 24px;
}

.save-view__title {
	margin: 0;
}

.save-view__error {
	color: var(--color-error-text);
	margin: 0;
}

.save-view__actions {
	display: flex;
	justify-content: flex-end;
}
</style>
