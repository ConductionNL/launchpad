<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<NcDialog
		:name="t('launchpad', 'Delete role permission')"
		:open="open"
		@update:open="$emit('update:open', $event)">
		<template #default>
			<p>
				{{
					t('launchpad', 'Delete role permission for group "{group}"?', {
						group: groupId,
					})
				}}
			</p>
		</template>
		<template #actions>
			<NcButton variant="tertiary" @click="$emit('update:open', false)">
				{{ t('launchpad', 'Cancel') }}
			</NcButton>
			<NcButton variant="error" @click="$emit('confirm')">
				{{ t('launchpad', 'Delete') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { NcButton, NcDialog } from '@conduction/nextcloud-vue'

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
