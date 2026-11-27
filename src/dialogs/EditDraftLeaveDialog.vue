<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<NcDialog
		:name="mode === 'cancel' ? t('launchpad', 'Discard changes?') : t('launchpad', 'Save changes?')"
		:open="open"
		size="small"
		data-testid="edit-draft-leave-dialog"
		@update:open="onOpen">
		<p>
			{{ n('launchpad', 'This dashboard has {count} unsaved change.', 'This dashboard has {count} unsaved changes.', count, { count }) }}
		</p>
		<template #actions>
			<NcButton
				variant="tertiary"
				data-testid="edit-draft-discard"
				@click="$emit('choose', 'discard')">
				{{ mode === 'cancel' ? t('launchpad', 'Discard') : t('launchpad', 'Don\'t save') }}
			</NcButton>
			<NcButton
				:variant="mode === 'cancel' ? 'primary' : 'secondary'"
				data-testid="edit-draft-keep"
				@click="$emit('choose', 'keep')">
				{{ t('launchpad', 'Keep editing') }}
			</NcButton>
			<NcButton
				v-if="mode !== 'cancel'"
				variant="primary"
				data-testid="edit-draft-save"
				@click="$emit('choose', 'save')">
				{{ t('launchpad', 'Save') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { NcButton, NcDialog } from '@conduction/nextcloud-vue'
import { n, t } from '@nextcloud/l10n'

/**
 * EditDraftLeaveDialog: asked when edit mode would end with unsaved
 * changes (board LpBewerken). On leaving the dashboard or edit mode another
 * way than Save: "Save changes?" with Don't save, Keep editing and Save
 * (REQ-EDIT-003); on Cancel: "Discard changes?" with Discard and Keep
 * editing (REQ-EDIT-004). Closing the dialog keeps editing.
 *
 * @spec openspec/changes/edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-leaving-with-unsaved-changes-asks-first-req-edit-003
 * @spec openspec/changes/edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-cancel-discards-the-draft-req-edit-004
 */
export default {
	name: 'EditDraftLeaveDialog',

	components: {
		NcButton,
		NcDialog,
	},

	props: {
		/** Whether the dialog is shown. */
		open: {
			type: Boolean,
			default: false,
		},

		/** 'leave' asks to save, 'cancel' asks to discard. */
		mode: {
			type: String,
			default: 'leave',
			validator: (value) => ['leave', 'cancel'].includes(value),
		},

		/** The number of unsaved changes. */
		count: {
			type: Number,
			default: 0,
		},
	},

	emits: ['choose'],

	methods: {
		t,
		n,

		/**
		 * Closing the dialog (Escape, the close button) keeps editing.
		 *
		 * @param {boolean} open Whether it stays open.
		 * @spec openspec/changes/edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-leaving-with-unsaved-changes-asks-first-req-edit-003
		 */
		onOpen(open) {
			if (!open) {
				this.$emit('choose', 'keep')
			}
		},
	},
}
</script>
