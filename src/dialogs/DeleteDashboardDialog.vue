<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<NcDialog
		:name="t('launchpad', 'Delete dashboard')"
		:open="open"
		@update:open="$emit('update:open', $event)">
		<template #default>
			<p>
				{{
					t('launchpad', 'Delete {name}? This cannot be undone.', { name })
				}}
			</p>
			<p v-if="childCount > 0" data-testid="delete-dashboard-children">
				{{
					t(
						'launchpad',
						'Dashboards under {name}: {count}. They are deleted with it.',
						{ name, count: childCount },
					)
				}}
			</p>
		</template>
		<template #actions>
			<NcButton variant="tertiary" @click="$emit('update:open', false)">
				{{ t('launchpad', 'Cancel') }}
			</NcButton>
			<NcButton
				variant="error"
				data-testid="delete-dashboard-confirm"
				@click="$emit('confirm')">
				{{ t('launchpad', 'Delete') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { NcButton, NcDialog } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'

/**
 * DeleteDashboardDialog: the confirmation before a dashboard is deleted.
 * When the server said it has dashboards under it (409 with `childCount`),
 * it says how many go with it (ADR-004 modal isolation; replaces a
 * `window.confirm`).
 *
 * @spec openspec/specs/dashboards/spec.md
 */
export default {
	name: 'DeleteDashboardDialog',

	components: {
		NcButton,
		NcDialog,
	},

	props: {
		open: {
			type: Boolean,
			required: true,
		},

		name: {
			type: String,
			default: '',
		},

		/** Dashboards under this one, once the server has told us. */
		childCount: {
			type: Number,
			default: 0,
		},
	},

	emits: ['update:open', 'confirm'],

	methods: {
		t,
	},
}
</script>
