<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<NcDialog
		:name="t('launchpad', 'Rename dashboard')"
		:open="open"
		@update:open="$emit('update:open', $event)">
		<template #default>
			<!-- `label` (not `placeholder`) is the accessible name. A
			     placeholder disappears the moment the field has content, so a
			     screen-reader user who tabs back to a filled field hears
			     nothing that identifies it. -->
			<NcTextField
				v-model="draft"
				:label="t('launchpad', 'New dashboard name')"
				@keyup.enter="submit" />
		</template>
		<template #actions>
			<NcButton type="tertiary" @click="$emit('update:open', false)">
				{{ t('launchpad', 'Cancel') }}
			</NcButton>
			<NcButton type="primary" :disabled="!canSubmit" @click="submit">
				{{ t('launchpad', 'Rename') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { NcButton, NcDialog, NcTextField } from '@conduction/nextcloud-vue'

/**
 * GroupDashboardRenameDialog — rename prompt extracted from
 * `ManageGroupDashboardsModal.vue` per ADR-004 modal-isolation.
 *
 * Replaces a `window.prompt()`. Beyond the theming and translation problems
 * a native prompt has, it is synchronous and blocks the browser thread, so
 * the rename path could not be covered by the e2e suite at all.
 *
 * The parent owns the dashboard row and the store call; this dialog owns
 * only the draft name and the validity rule, and emits the trimmed result.
 *
 * @spec openspec/specs/admin-templates/spec.md
 */
export default {
	name: 'GroupDashboardRenameDialog',

	components: {
		NcButton,
		NcDialog,
		NcTextField,
	},

	props: {
		open: {
			type: Boolean,
			required: true,
		},
		currentName: {
			type: String,
			default: '',
		},
	},

	emits: ['update:open', 'confirm'],

	data() {
		return {
			draft: this.currentName,
		}
	},

	computed: {
		/**
		 * The same three rules the replaced `window.prompt()` applied after
		 * the fact — empty, whitespace-only, or unchanged is a no-op. Here
		 * they disable the button instead, so the rule is visible before the
		 * user commits rather than silently swallowing the click.
		 *
		 * @spec openspec/specs/dashboards/spec.md#req-dash-015-admin-group-management-ui
		 * @return {boolean} whether Rename may be pressed.
		 */
		canSubmit() {
			const next = this.draft.trim()
			return next !== '' && next !== this.currentName
		},
	},

	watch: {
		/**
		 * Re-seed the draft whenever the dialog is (re)opened for a row.
		 * Without this, opening the dialog for a second dashboard would show
		 * the first one's name, because `data()` runs once.
		 *
		 * @spec openspec/specs/dashboards/spec.md#req-dash-015-admin-group-management-ui
		 * @param {boolean} isOpen the new open state.
		 * @return {void}
		 */
		open(isOpen) {
			if (isOpen) {
				this.draft = this.currentName
			}
		},
	},

	methods: {
		/**
		 * Emit the trimmed name, but only when it passes `canSubmit` — the
		 * Enter key reaches this method without going through the disabled
		 * button, so the guard has to live here too.
		 *
		 * @spec openspec/specs/dashboards/spec.md#req-dash-015-admin-group-management-ui
		 * @return {void}
		 */
		submit() {
			if (!this.canSubmit) {
				return
			}
			this.$emit('confirm', this.draft.trim())
		},
	},
}
</script>
