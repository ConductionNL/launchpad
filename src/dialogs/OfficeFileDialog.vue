<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<NcDialog
		:name="title"
		:open="open"
		@update:open="$emit('update:open', $event)">
		<template #default>
			<div class="office-file-dialog">
				<NcTextField
					v-model="name"
					:label="t('launchpad', 'File name')"
					:disabled="busy"
					@keyup.enter="submit" />
				<NcTextField
					v-model="folder"
					:label="t('launchpad', 'Folder')"
					:disabled="busy"
					@keyup.enter="submit" />
			</div>
		</template>
		<template #actions>
			<NcButton variant="tertiary" :disabled="busy" @click="$emit('update:open', false)">
				{{ t('launchpad', 'Cancel') }}
			</NcButton>
			<NcButton variant="primary" :disabled="!canSubmit" @click="submit">
				{{ t('launchpad', 'Create') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { NcButton, NcDialog, NcTextField } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import { FLAG_PROP, REQUIRED_FLAG_PROP, TEXT_PROP } from '../constants/propDefinitions.js'

/** The folder a new office file goes to unless the person picks another. */
export const DEFAULT_OFFICE_FOLDER = '/Documents'

/**
 * OfficeFileDialog — the name and folder a "New ..." office tile asks for
 * before it creates the file (REQ-OFT-002). The dialog owns only the draft
 * values; the tile does the request and emits nothing else.
 *
 * @spec openspec/changes/integrations-office-tiles/specs/office-tiles/spec.md#requirement-a-new-tile-creates-a-real-document-and-never-overwrites-req-oft-002
 */
export default {
	name: 'OfficeFileDialog',

	components: {
		NcButton,
		NcDialog,
		NcTextField,
	},

	props: {
		open: REQUIRED_FLAG_PROP,

		/** The dialog heading, the tile's own label such as "New document". */
		title: TEXT_PROP,

		/** The name the field starts with. */
		defaultName: TEXT_PROP,

		/** Whether the file is being created; the fields and buttons are then disabled. */
		busy: FLAG_PROP,
	},

	emits: ['update:open', 'confirm'],

	data() {
		return {
			name: this.defaultName,
			folder: DEFAULT_OFFICE_FOLDER,
		}
	},

	computed: {
		/**
		 * A name and a folder that are not blank, and no request under way.
		 *
		 * @return {boolean} Whether Create may be pressed.
		 * @spec openspec/changes/integrations-office-tiles/specs/office-tiles/spec.md#requirement-a-new-tile-creates-a-real-document-and-never-overwrites-req-oft-002
		 */
		canSubmit() {
			return !this.busy && this.name.trim() !== '' && this.folder.trim() !== ''
		},
	},

	watch: {
		/**
		 * Start from the default name and folder each time the dialog opens.
		 *
		 * @param {boolean} isOpen The new open state.
		 * @return {void}
		 * @spec openspec/changes/integrations-office-tiles/specs/office-tiles/spec.md#requirement-a-new-tile-creates-a-real-document-and-never-overwrites-req-oft-002
		 */
		open(isOpen) {
			if (isOpen) {
				this.name = this.defaultName
				this.folder = DEFAULT_OFFICE_FOLDER
			}
		},
	},

	methods: {
		t,

		/**
		 * Emit the trimmed name and folder when they pass canSubmit; Enter
		 * reaches this without the disabled button, so the guard lives here.
		 *
		 * @return {void}
		 * @spec openspec/changes/integrations-office-tiles/specs/office-tiles/spec.md#requirement-a-new-tile-creates-a-real-document-and-never-overwrites-req-oft-002
		 */
		submit() {
			if (!this.canSubmit) {
				return
			}
			this.$emit('confirm', { name: this.name.trim(), folder: this.folder.trim() })
		},
	},
}
</script>

<style scoped>
.office-file-dialog {
	display: flex;
	flex-direction: column;
	gap: var(--default-grid-baseline, 4px);
}
</style>
