<!--
  - SPDX-FileCopyrightText: 2026 LaunchPad Contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog
		:name="t('launchpad','Delete role permission')"
		:open="open"
		@update:open="$emit('update:open', $event)">
		<template #default>
			<p>{{ t('launchpad','Delete role permission for group "{group}"?', { group: groupId }) }}</p>
		</template>
		<template #actions>
			<NcButton type="tertiary" @click="$emit('update:open', false)">
				{{ t('launchpad','Cancel') }}
			</NcButton>
			<NcButton type="error" @click="$emit('confirm')">
				{{ t('launchpad','Delete') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import {
	NcButton,
	NcDialog,
} from '@conduction/nextcloud-vue'

/**
 * RolePermissionDeleteDialog — confirmation dialog extracted from
 * `RolePermissionsSection.vue` per ADR-004 modal-isolation rule. The
 * parent owns the target row + delete action; this dialog only renders
 * the confirmation prompt.
 *
 * @spec openspec/specs/admin-roles/spec.md
 */
export default {
	name: 'RolePermissionDeleteDialog',

	components: {
		NcButton,
		NcDialog,
	},

	props: {
		open: {
			type: Boolean,
			required: true,
		},
		groupId: {
			type: String,
			default: '',
		},
	},

	emits: ['update:open', 'confirm'],
}
</script>
