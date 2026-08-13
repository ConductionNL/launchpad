<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<NcDialog
		:name="t('launchpad', 'Delete dashboard')"
		:open="open"
		@update:open="$emit('update:open', $event)">
		<template #default>
			<p>
				{{ t('launchpad', 'Delete this dashboard? This cannot be undone.') }}
			</p>
		</template>
		<template #actions>
			<NcButton type="tertiary" @click="$emit('update:open', false)">
				{{ t('launchpad', 'Cancel') }}
			</NcButton>
			<NcButton type="error" @click="$emit('confirm')">
				{{ t('launchpad', 'Delete') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { NcButton, NcDialog } from '@conduction/nextcloud-vue'

/**
 * GroupDashboardDeleteDialog — confirmation extracted from
 * `ManageGroupDashboardsModal.vue` per ADR-004 modal-isolation.
 *
 * Replaces a `window.confirm()`, which is unthemeable, blocks the browser
 * thread, and therefore cannot be exercised by the e2e suite.
 *
 * @spec openspec/specs/admin-templates/spec.md
 */
export default {
	name: 'GroupDashboardDeleteDialog',

	components: {
		NcButton,
		NcDialog,
	},

	props: {
		open: {
			type: Boolean,
			required: true,
		},
	},

	emits: ['update:open', 'confirm'],
}
</script>
