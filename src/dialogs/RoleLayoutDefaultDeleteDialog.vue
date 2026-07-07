<!--
  - SPDX-FileCopyrightText: 2026 LaunchPad Contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog
		:name="t('launchpad','Delete layout default')"
		:open="open"
		@update:open="$emit('update:open', $event)">
		<template #default>
			<p>{{ t('launchpad','Delete layout default for "{group}" / "{widget}"?', { group: groupId, widget: widgetId }) }}</p>
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
 * RoleLayoutDefaultDeleteDialog — confirmation extracted from
 * `RoleLayoutDefaultsSection.vue` per ADR-004 modal-isolation rule.
 *
 * @spec openspec/specs/admin-roles/spec.md
 */
export default {
	name: 'RoleLayoutDefaultDeleteDialog',

	components: {
		NcButton,
		NcDialog,
	},

	props: {
		open: { type: Boolean, required: true },
		groupId: { type: String, default: '' },
		widgetId: { type: String, default: '' },
	},

	emits: ['update:open', 'confirm'],
}
</script>
