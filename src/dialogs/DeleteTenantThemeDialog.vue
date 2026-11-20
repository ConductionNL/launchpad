<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<NcDialog
		:name="t('launchpad', 'Delete theme')"
		:open="open"
		@update:open="$emit('update:open', $event)">
		<template #default>
			<p>{{ t('launchpad', 'Delete {name}? This cannot be undone.', { name }) }}</p>
			<p v-if="tokenCount > 0" data-test="delete-theme-in-use">
				{{ n('launchpad', '%n embed token uses this theme and falls back to the default theme.', '%n embed tokens use this theme and fall back to the default theme.', tokenCount) }}
			</p>
		</template>
		<template #actions>
			<NcButton variant="tertiary" @click="$emit('update:open', false)">
				{{ t('launchpad', 'Cancel') }}
			</NcButton>
			<NcButton
				variant="error"
				data-test="delete-theme-confirm"
				@click="$emit('confirm')">
				{{ t('launchpad', 'Delete') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { NcButton, NcDialog } from '@conduction/nextcloud-vue'
import { n, t } from '@nextcloud/l10n'
import { REQUIRED_FLAG_PROP, TEXT_PROP } from '../constants/propDefinitions.js'

/**
 * DeleteTenantThemeDialog: the confirmation before a tenant theme is
 * deleted, warning when embed tokens still use it (embedded-analytics task 16).
 *
 * @spec openspec/changes/embedded-analytics/tasks.md#task-16
 */
export default {
	name: 'DeleteTenantThemeDialog',

	components: {
		NcButton,
		NcDialog,
	},

	props: {
		open: REQUIRED_FLAG_PROP,

		/** The theme's name. */
		name: TEXT_PROP,

		/** How many embed tokens use the theme. */
		tokenCount: {
			type: Number,
			default: 0,
		},
	},

	emits: ['update:open', 'confirm'],

	methods: {
		t,
		n,
	},
}
</script>
